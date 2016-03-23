<?php
/**
 * @file
 * This provides the Sync Push from CiviCRM to Hubspot form.
 */

class CRM_Hubspot_Form_Sync extends CRM_Core_Form {

  const QUEUE_NAME = 'hs-sync';
  const END_URL    = 'civicrm/hubspot/sync';
  const END_PARAMS = 'state=done';

  /**
   * Function to pre processing
   *
   * @return None
   * @access public
   */
  function preProcess() {
    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = CRM_Core_BAO_Setting::getItem(CRM_Hubspot_Form_Setting::HS_SETTING_GROUP, 'push_stats');
      $groups = CRM_Hubspot_Utils::getGroupsToSync(array(), null);
      if (!$groups) {
        return;
      }
      $output_stats = array();
      foreach ($groups as $group_id => $details) {
        $list_stats = $stats[$details['list_id']];
        $output_stats[] = array(
          'name' => $details['civigroup_title'],
          'stats' => $list_stats,
        );
      }
      $this->assign('stats', $output_stats);
    }
  }

  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Sync Contacts'),
      ),
    );

    // Add the Buttons.
    $this->addButtons($buttons);
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    $runner = self::getRunner();
    if ($runner) {
      // Run Everything in the Queue via the Web.
      $runner->runAllViaWeb();
    } else {
      CRM_Core_Session::setStatus(ts('Nothing to sync. Make sure hubspot settings are configured for the groups with enough members.'));
    }
  }

  static function getRunner($skipEndUrl = FALSE) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    // reset push stats
    CRM_Core_BAO_Setting::setItem(Array(), CRM_Hubspot_Form_Setting::HS_SETTING_GROUP, 'push_stats');
    $stats = array();
   
    // We need to process one list at a time.
    $groups = CRM_Hubspot_Utils::getGroupsToSync(array(), null);
    if (!$groups) {
      // Nothing to do.
      return FALSE;
    }

    // Each list is a task.
    $listCount = 1;
    foreach ($groups as $group_id => $details) {
      $stats[$details['list_id']] = array(
        'hs_count' => 0,
        'c_count' => 0,
        'in_sync' => 0,
        'added' => 0,
        'removed' => 0,
        'group_id' => 0,
        'error_count' => 0
      );

      $identifier = "List " . $listCount++ . " " . $details['civigroup_title'];

      $task  = new CRM_Queue_Task(
        array ('CRM_Hubspot_Form_Sync', 'syncPushList'),
        array($details['list_id'], $identifier),
        "Preparing queue for $identifier"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
    }

    // Setup the Runner
		$runnerParams = array(
      'title' => ts('Hubspot Sync: CiviCRM to Hubspot'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    );
		// Skip End URL to prevent redirect
		// if calling from cron job
		if ($skipEndUrl == TRUE) {
			unset($runnerParams['onEndUrl']);
		}
    $runner = new CRM_Queue_Runner($runnerParams);

    static::updatePushStats($stats);
    return $runner;
  }

  /**
   * Set up (sub)queue for syncing a Hubspot List.
   */
  static function syncPushList(CRM_Queue_TaskContext $ctx, $listID, $identifier) {
    // Split the work into parts:
    // @todo 'force' method not implemented here.

    // Add the Hubspot collect data task to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Hubspot_Form_Sync', 'syncPushCollectHubspot'),
      array($listID),
      "$identifier: Fetched data from Hubspot"
    ));

    // Add the CiviCRM collect data task to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Hubspot_Form_Sync', 'syncPushCollectCiviCRM'),
      array($listID),
      "$identifier: Fetched data from CiviCRM"
    ));

    // Add the removals task to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Hubspot_Form_Sync', 'syncPushRemove'),
      array($listID),
      "$identifier: Removed those who should no longer be subscribed"
    ));

    // Add the batchUpdate to the queue
    $ctx->queue->createItem( new CRM_Queue_Task(
      array('CRM_Hubspot_Form_Sync', 'syncPushAdd'),
      array($listID),
      "$identifier: Added new subscribers and updating existing data changes"
    ));

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect Hubspot data into temporary working table.
   */
  static function syncPushCollectHubspot(CRM_Queue_TaskContext $ctx, $listID) {

    $stats[$listID]['hs_count'] = static::syncCollectHubspot($listID);
    static::updatePushStats($stats);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncPushCollectCiviCRM(CRM_Queue_TaskContext $ctx, $listID) {
    $stats[$listID]['c_count'] = static::syncCollectCiviCRM($listID);
    static::updatePushStats($stats);

    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Unsubscribe contacts that are subscribed at Hubspot but not in our list.
   */
  static function syncPushRemove(CRM_Queue_TaskContext $ctx, $listID) {
    // Delete records have the same hash - these do not need an update.
    static::updatePushStats(array($listID => array('in_sync'=> static::syncIdentical())));

    // Now identify those that need removing from Hubspot.
    // @todo implement the delete option, here just the unsubscribe is implemented.
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT m.email, m.vid
       FROM tmp_hubspot_push_h m
       WHERE NOT EXISTS (
         SELECT email FROM tmp_hubspot_push_c c WHERE c.email = m.email
       );");

    // Loop the $dao object to make a list of emails to unsubscribe|delete from MC
    $batch = array();
    $stats[$listID]['removed'] = 0;
    while ($dao->fetch()) {
      $batch[] = array($dao->vid);
      $stats[$listID]['removed']++;
    }
    if (!$batch) {
      // Nothing to do
      return CRM_Queue_Task::TASK_SUCCESS;
    }

    $result = CRM_Hubspot_Utils::hubspotRemoveContactFromList($listID, $batch);
    
    // Finally we can delete the emails that we just processed from the hubspot temp table.
    CRM_Core_DAO::executeQuery(
      "DELETE FROM tmp_hubspot_push_h
       WHERE NOT EXISTS (
         SELECT email FROM tmp_hubspot_push_c c WHERE c.email = tmp_hubspot_push_h.email
       );");

    static::updatePushStats($stats);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
   * Batch update Hubspot with new contacts that need to be subscribed, or have changed data.
   *
   * This also does the clean-up tasks of removing the temporary tables.
   */
  static function syncPushAdd(CRM_Queue_TaskContext $ctx, $listID) {

    // @todo take the remaining details from tmp_hubspot_push_c
    // and construct a batchUpdate (do they need to be batched into 1000s? I can't recal).

    $dao = CRM_Core_DAO::executeQuery( "SELECT * FROM tmp_hubspot_push_c;");
    $stats = array();
    // Loop the $dao object to make a list of emails to subscribe/update
    $batch = array();
    while ($dao->fetch()) {
      $properties = array(
        array('property' => 'firstname','value' => $dao->first_name),
        array('property' => 'lastname', 'value' => $dao->last_name),
      );
      $batch[$dao->email] = array('email' => $dao->email, 'properties' => $properties);
      $stats[$listID]['added']++;
    }
    if (!$batch) {
      // Nothing to do
      return CRM_Queue_Task::TASK_SUCCESS;
    }

    $batchs = array_chunk($batch, 50, true);
    $batchResult = array();
    $result = array('errors' => array());
    $hubSpot = CRM_Hubspot_Utils::hubspot();
    foreach($batchs as $id => $batch) {
      $emailList = array_keys($batch);
      $batchResult[$id] = CRM_Hubspot_Utils::hubspotCreateUpdateContact(array_values($batch));
      $contacts = $hubSpot->contacts()->getBatchByEmails($emailList, array('property'=> array('firstname', 'lastname', 'email')));
      $contactVids = array_keys($contacts);
      if (!empty($contactVids)) {
        $result = CRM_Hubspot_Utils::hubspotAddContactToList($listID, $contactVids);
      }
    }

    $get_GroupId = CRM_Hubspot_Utils::getGroupsToSync(array(), $listID);

    $stats[$listID]['group_id'] = array_keys($get_GroupId);
   
    static::updatePushStats($stats);

    // Finally, finish up by removing the two temporary tables
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_hubspot_push_h;");
    CRM_Core_DAO::executeQuery("DROP TABLE tmp_hubspot_push_c;");
    return CRM_Queue_Task::TASK_SUCCESS;
  }


  /**
   * Collect Hubspot data into temporary working table.
   */
  static function syncCollectHubspot($listID) {
    // Create a temporary table.
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.

    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_hubspot_push_h;");
    $dao = CRM_Core_DAO::executeQuery(
      "CREATE TABLE tmp_hubspot_push_h (
        email VARCHAR(200),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        vid int(10),
        hash CHAR(32),
        PRIMARY KEY (email, vid))
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");
        
    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
	  $insert = $db->prepare('INSERT INTO tmp_hubspot_push_h(email, first_name, last_name, vid, hash) VALUES(?, ?, ?, ?, ?)');
    
    $listContacts = array();
    $params = array(
      'version' => 3,
      'sequential' => 1,
      'listid' => $listID,
    );

    $listContacts = civicrm_api('Hubspot', 'getcontactsbylist', $params);
    foreach ($listContacts['values'] as $vid => $contact) {
      // run insert prepared statement
      $hash = md5($contact['email'] . $contact['firstname'] . $contact['lastname'] );
      $db->execute($insert, array($contact['email'], $contact['firstname'], $contact['lastname'], $vid, $hash));
    }

    $db->freePrepared($insert);

    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_hubspot_push_h");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Collect CiviCRM data into temporary working table.
   */
  static function syncCollectCiviCRM($listID) {
    // Nb. these are temporary tables but we don't use TEMPORARY table because they are
    // needed over multiple sessions because of queue.
    CRM_Core_DAO::executeQuery( "DROP TABLE IF EXISTS tmp_hubspot_push_c;");
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE tmp_hubspot_push_c (
        contact_id INT(10) UNSIGNED NOT NULL,
        email_id INT(10) UNSIGNED NOT NULL,
        email VARCHAR(200),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        hash CHAR(32),
        PRIMARY KEY (email_id, email, hash))
        ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ;");
    
    // Cheekily access the database directly to obtain a prepared statement.
    $db = $dao->getDatabaseConnection();
    $insert = $db->prepare('INSERT INTO tmp_hubspot_push_c VALUES(?, ?, ?, ?, ?, ?)');

    //create table for hubspot civicrm syn errors
    $dao = CRM_Core_DAO::executeQuery("CREATE TABLE IF NOT EXISTS hubspot_civicrm_syn_errors (
        id int(11) NOT NULL AUTO_INCREMENT,
        email VARCHAR(200),
        error VARCHAR(200),
        error_count int(10),
        group_id int(20),
        list_id VARCHAR(20),
        PRIMARY KEY (id)
        );");

    // We need to know what groupings we have maps to.
    // We only care about CiviCRM groups that are mapped to this MC List:
    $mapped_groups = CRM_Hubspot_Utils::getGroupsToSync(array(), $listID);
    
    // There used to be a distinction between the handling of 'normal' groups
    // and smart groups. But now the API will take care of this.
    $grouping_group_ids = array();

    foreach ($mapped_groups as $group_id => $details) {
      CRM_Contact_BAO_GroupContactCache::loadAll($group_id);
      $grouping_group_ids[$group_id] = 1;
    }

    // Use a nice API call to get the information for tmp_hubspot_push_c.
    // The API will take care of smart groups.
    $result = civicrm_api3('Contact', 'get', array(
      'is_deleted' => 0,
      'on_hold' => 0,
      'is_opt_out' => 0,
      'do_not_email' => 0,
      'group' => $grouping_group_ids,
      'return' => array('first_name', 'last_name', 'email_id', 'email', 'group'),
      'options' => array('limit' => 0),
    ));

    foreach ($result['values'] as $contact) {
      $hash = md5($contact['email'] . $contact['first_name'] . $contact['last_name'] );
      // run insert prepared statement
      $db->execute($insert, array($contact['id'], $contact['email_id'], $contact['email'], $contact['first_name'], $contact['last_name'], $hash));
    }

    // Tidy up.
    $db->freePrepared($insert);
    // count
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(*) c  FROM tmp_hubspot_push_c");
    $dao->fetch();
    return $dao->c;
  }

  /**
   * Update the push stats setting.
   */
  static function updatePushStats($updates) {
    $stats = CRM_Core_BAO_Setting::getItem(CRM_Hubspot_Form_Setting::HS_SETTING_GROUP, 'push_stats');
    
    foreach ($updates as $listId=>$settings) {
      foreach ($settings as $key=>$val) {
        // avoid error details to store in civicrm_settings table
        // create sql error "Data too long for column 'value'" (for long array)
        if ($key == 'error_details') {
          continue;
        }
        $stats[$listId][$key] = $val;
      }
    }
    CRM_Core_BAO_Setting::setItem($stats, CRM_Hubspot_Form_Setting::HS_SETTING_GROUP, 'push_stats');

    //$email = $error_count = $error = $list_id = array();

    foreach ($updates as $list => $listdetails) {
      if (isset($updates[$list]['error_count']) && !empty($updates[$list]['error_count'])) {
        $error_count = $updates[$list]['error_count'];
      }
      $list_id = $list;

      if (isset($updates[$list]['group_id']) && !empty($updates[$list]['group_id'])) {
        foreach ($updates[$list]['group_id'] as $keys => $values) {
          $group_id = $values;
          $deleteQuery = "DELETE FROM `hubspot_civicrm_syn_errors` WHERE group_id =$group_id";
          CRM_Core_DAO::executeQuery($deleteQuery);
        }
      }

      if (isset($updates[$list]['error_details']) && !empty($updates[$list]['error_details'])) {
        foreach ($updates[$list]['error_details'] as $key => $value) {
          $error = $value['error'];
          $email = $value['email']['email'];
          $insertQuery = "INSERT INTO `hubspot_civicrm_syn_errors` (`email`, `error`, `error_count`, `list_id`, `group_id`) VALUES (%1,%2, %3, %4, %5)";
          $queryParams = array(
            1 => array($email, 'String'),
            2 => array($error, 'String'),
            3 => array($error_count, 'Integer'),
            4 => array($list_id, 'String'),
            5 => array($group_id, 'Integer')
          );
          CRM_Core_DAO::executeQuery($insertQuery, $queryParams);
        }
      }
    }
  }
  
  /**
   * Removes from the temporary tables those records that do not need processing.
   */
  static function syncIdentical() {
    // Delete records have the same hash - these do not need an update.
    // count
    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(c.email) co FROM tmp_hubspot_push_h m
      INNER JOIN tmp_hubspot_push_c c ON m.email = c.email AND m.hash = c.hash;");
    $dao->fetch();
    $count = $dao->co;
    CRM_Core_DAO::executeQuery(
      "DELETE m, c
       FROM tmp_hubspot_push_h m
       INNER JOIN tmp_hubspot_push_c c ON m.email = c.email AND m.hash = c.hash;");

    return $count;
  }
}

