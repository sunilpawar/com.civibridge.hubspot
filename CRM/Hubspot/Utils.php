<?php

use Fungku\HubSpot\HubSpotService;
use Fungku\HubSpot\Api\ContactLists;
use Fungku\HubSpot\Http\Client;
require_once 'vendor/autoload.php';

class CRM_Hubspot_Utils {
  private static $_singleton = NULL;
  private static $_getListAll = NULL;
  const HS_SETTING_GROUP = 'Hubspot Preferences';

  /**
   * Construct a HelpTab
   */
  private function __construct() {
  }
    
  static function hubspot() {
    $apiKey   = CRM_Core_BAO_Setting::getItem(CRM_Hubspot_Form_Setting::HS_SETTING_GROUP, 'api_key');
    return Fungku\HubSpot\HubSpotService::make($apiKey);
  }

  static function hubspotGetList() {
    $session = CRM_Core_Session::singleton();
    if (empty($session->get('getListAll'))) {    
      $hubSpot = CRM_Hubspot_Utils::hubspot();
      $listAll = $hubSpot->contactLists()->all(array('count' => 100));
      $lists = array();
      foreach ($listAll['lists'] as $list) {
        if ( $list['listType'] == 'STATIC' ) {
          $lists[$list['listId']] = $list['name'];
        }
      }
      if (!empty($lists)) {
        $session->set("getListAll", $lists);
      }
    }
    return $session->get('getListAll');
  }  

  static function hubspotRemoveContactFromList($list, $hb_contact_ids) {
    $hubSpot = CRM_Hubspot_Utils::hubspot();
    $result = $hubSpot->contactLists()->removeContact($params['list'], $hb_contact_ids);  
    return $result;
  }

  static function hubspotAddContactToList($list, $hb_contact_ids) {
    $hubSpot = CRM_Hubspot_Utils::hubspot();
    $result = $hubSpot->contactLists()->addContact($list, $hb_contact_ids);  
    return $result;
  }  

  static function hubspotCreateUpdateContact($hb_contact_ids) {
    $hubSpot = CRM_Hubspot_Utils::hubspot();
    $result = $hubSpot->contacts()->createOrUpdateBatch($hb_contact_ids);  
    return $result;
  }

  /**
   * Look up an array of CiviCRM groups linked to Maichimp groupings.
   *
   * Indexed by CiviCRM groupId, including:
   *
   * - list_id   (HS)
   * - list_name (HS)
   * - civigroup_title
   * - civigroup_uses_cache boolean
   *
   * @param $groupIDs mixed array of CiviCRM group Ids to fetch data for; or empty to return ALL mapped groups.
   * @param $hs_list_id mixed Fetch for a specific Hubspot list only, or null.
   *
   */
  static function getGroupsToSync($groupIDs = array(), $hs_list_id = null) {

    $params = $groups = $temp = array();
    foreach ($groupIDs as $value) {
      if($value){
        $temp[] = $value;
      }
    }

    $groupIDs = $temp;

    if (!empty($groupIDs)) {
      $groupIDs = implode(',', $groupIDs);
      $whereClause = "entity_id IN ($groupIDs)";
    } else {
      $whereClause = "1 = 1";
    }

    $whereClause .= " AND hs_list_id IS NOT NULL AND hs_list_id <> ''";

    if ($hs_list_id) {
      // just want results for a particular MC list.
      $whereClause .= " AND hs_list_id = %1 ";
      $params[1] = array($hs_list_id, 'String');
    }

    $query  = "
      SELECT  entity_id, hs_list_id, cg.title as civigroup_title, cg.saved_search_id, cg.children
      FROM    civicrm_value_hubspot_settings hss
      INNER JOIN civicrm_group cg ON hss.entity_id = cg.id
      WHERE $whereClause";
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    $lists = CRM_Hubspot_Utils::hubspotGetList();
    while ($dao->fetch()) {
      $groups[$dao->entity_id] =
        array(
          'list_id'              => $dao->hs_list_id,
          'list_name'            => $lists[$dao->hs_list_id],
          'civigroup_title'      => $dao->civigroup_title,
          'civigroup_uses_cache' => (bool) (($dao->saved_search_id > 0) || (bool) $dao->children),
        );
    }

    return $groups;
  }

  static function getGroupIDsToSync() {
    $groupIDs = self::getGroupsToSync();
    return array_keys($groupIDs);
  }

  static function getMemberCountForGroupsToSync($groupIDs = array()) {
    $group = new CRM_Contact_DAO_Group();
    foreach ($groupIDs as $key => $value) {
    $group->id  = $value;      
    }
    $group->find(TRUE);
    
    if (empty($groupIDs)) {
      $groupIDs = self::getGroupIDsToSync();
    }
    if(!empty($groupIDs) && $group->saved_search_id){
      $groupIDs = implode(',', $groupIDs);
      $smartGroupQuery = " 
                  SELECT count(*)
                  FROM civicrm_group_contact_cache smartgroup_contact
                  WHERE smartgroup_contact.group_id IN ($groupIDs)";
      return CRM_Core_DAO::singleValueQuery($smartGroupQuery);
    }
    else if (!empty($groupIDs)) {
      $groupIDs = implode(',', $groupIDs);
      $query    = "
        SELECT  count(*)
        FROM    civicrm_group_contact
        WHERE   status = 'Added' AND group_id IN ($groupIDs)";
      return CRM_Core_DAO::singleValueQuery($query);
    }
    return 0;
  }

  /**
   * return the group name for given list, grouping and group
   *
   */
  static function getMCGroupName($listID, $groupingID, $groupID) {
    $info = static::getMCInterestGroupings($listID);

    // Check list, grouping, and group exist
    if (empty($info[$groupingID]['groups'][$groupID])) {
      return NULL;
    }
    return $info[$groupingID]['groups'][$groupID]['name'];
  }

  /**
   * Return the grouping name for given list, grouping MC Ids.
   */
  static function getMCGroupingName($listID, $groupingID) {

    $info = static::getMCInterestGroupings($listID);

    // Check list, grouping, and group exist
    if (empty($info[$groupingID])) {
      return NULL;
    }

    return $info[$groupingID]['name'];
  }

  /**
   * Get interest groupings for given ListID (cached).
   *
   * Nb. general API function used by several other helper functions.
   *
   * Returns an array like {
   *   [groupingId] => array(
   *     'id' => [groupingId],
   *     'name' => ...,
   *     'form_field' => ...,    (not v interesting)
   *     'display_order' => ..., (not v interesting)
   *     'groups' => array(
   *        [MC groupId] => array(
   *          'id' => [MC groupId],
   *          'bit' => ..., ?
   *          'name' => ...,
   *          'display_order' => ...,
   *          'subscribers' => ..., ?
   *          ),
   *        ...
   *        ),
   *   ...
   *   ) 
   *
   */
  static function getMCInterestGroupings($listID) {

    if (empty($listID)) {
      return NULL;
    }

    static $mapper = array();
    if (!array_key_exists($listID, $mapper)) {
      $mapper[$listID] = array();

      $mcLists = new Hubspot_Lists(CRM_Hubspot_Utils::hubspot());
      try {
        $results = $mcLists->interestGroupings($listID);
      }
      catch (Exception $e) {
        return NULL;
      }
      /*  re-map $result for quick access via grouping_id and groupId
       *
       *  Nb. keys for grouping:
       *  - id
       *  - name
       *  - form_field    (not v interesting)
       *  - display_order (not v interesting)
       *  - groups: array as follows, keyed by GroupId
       *
       *  Keys for each group
       *  - id
       *  - bit ?
       *  - name
       *  - display_order
       *  - subscribers ?
       *
       */
      foreach ($results as $grouping) {

        $mapper[$listID][$grouping['id']] = $grouping;
        unset($mapper[$listID][$grouping['id']]['groups']);
        foreach ($grouping['groups'] as $group) {
          $mapper[$listID][$grouping['id']]['groups'][$group['id']] = $group;
        }
      }
    }
    return $mapper[$listID];
  }

  /*
   * Get Hubspot group ID group name
   */
  static function getHubspotGroupIdFromName($listID, $groupName) {

    if (empty($listID) || empty($groupName)) {
      return NULL;
    }

    $mcLists = new Hubspot_Lists(CRM_Hubspot_Utils::hubspot());
    try {
      $results = $mcLists->interestGroupings($listID);
    } 
    catch (Exception $e) {
      return NULL;
    }
    
    foreach ($results as $grouping) {
      foreach ($grouping['groups'] as $group) {
        if ($group['name'] == $groupName) {
          return $group['id'];
        }
      }
    }
  }
  
  static function getGroupIdForHubspot($listID, $groupingID, $groupID) {
    if (empty($listID)) {
      return NULL;
    }
    
    if (!empty($groupingID) && !empty($groupID)) {
      $whereClause = "hs_list_id = %1 AND mc_grouping_id = %2 AND mc_group_id = %3";
    } else {
      $whereClause = "hs_list_id = %1";
    }

    $query  = "
      SELECT  entity_id
      FROM    civicrm_value_hubspot_settings hss
      WHERE   $whereClause";
    $params = 
        array(
          '1' => array($listID , 'String'),
          '2' => array($groupingID , 'String'),
          '3' => array($groupID , 'String'),
        );
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      return $dao->entity_id;
    }
  }
  
  /**
   * Try to find out already if we can find a unique contact for this e-mail
   * address.
   */
  static function guessCidsMailchimpContacts() {
    // If an address is unique, that's the one we need.
    CRM_Core_DAO::executeQuery(
        "UPDATE tmp_mailchimp_push_m m
          JOIN civicrm_email e1 ON m.email = e1.email
          LEFT OUTER JOIN civicrm_email e2 ON m.email = e2.email AND e1.id <> e2.id
          SET m.cid_guess = e1.contact_id
          WHERE e2.id IS NULL")->free();
    // In the other case, if we find a unique contact with matching
    // first name, last name and e-mail address, it is probably the one we
    // are looking for as well.
    CRM_Core_DAO::executeQuery(
       "UPDATE tmp_mailchimp_push_m m
          JOIN civicrm_email e1 ON m.email = e1.email
          JOIN civicrm_contact c1 ON e1.contact_id = c1.id AND c1.first_name = m.first_name AND c1.last_name = m.last_name 
          LEFT OUTER JOIN civicrm_email e2 ON m.email = e2.email
          LEFT OUTER JOIN civicrm_contact c2 on e2.contact_id = c2.id AND c2.first_name = m.first_name AND c2.last_name = m.last_name AND c2.id <> c1.id
          SET m.cid_guess = e1.contact_id
          WHERE m.cid_guess IS NULL AND c2.id IS NULL")->free();
  }

  /**
   * Update first name and last name of the contacts of which we already
   * know the contact id.
   */
  static function updateGuessedContactDetails() {
    // In theory I could do this with one SQL join statement, but this way
    // we would bypass user defined hooks. So I will use the API, but only
    // in the case that the names are really different. This will save
    // some expensive API calls. See issue #188.

    $dao = CRM_Core_DAO::executeQuery(
        "select c.id, m.first_name, m.last_name
          from tmp_mailchimp_push_m m
          join civicrm_contact c on m.cid_guess = c.id
          where m.first_name <> c.first_name or m.last_name <> c.last_name");

    while ($dao->fetch()) {
      civicrm_api3('Contact', 'create', array(
        'id' => $dao->id,
        'first_name' => $dao->first_name,
        'last_name' => $dao->last_name,
      ));
    }
    $dao->free();
  }
  
  /*
   * Create/Update contact details in CiviCRM, based on the data from Hubspot webhook
   */
  static function updateContactDetails(&$params, $delay = FALSE) {

    if (empty($params)) {
      return NULL;
    }
    $params['status'] = array('Added' => 0, 'Updated' => 0);
    $contactParams = 
        array(
          'version'       => 3,
          'contact_type'  => 'Individual',
          'first_name'    => $params['FNAME'],
          'last_name'     => $params['LNAME'],
          'email'         => $params['EMAIL'],
        );
     
    if($delay){
      //To avoid a new duplicate contact to be created as both profile and upemail events are happening at the same time
      sleep(20);
    }
    $contactids = CRM_Hubspot_Utils::getContactFromEmail($params['EMAIL']);
    
    if(count($contactids) > 1) {
       return NULL;
    }
    if(count($contactids) == 1) {
      $contactParams  = CRM_Hubspot_Utils::updateParamsExactMatch($contactids, $params);
      $params['status']['Updated']  = 1;
    }
    if(empty($contactids)) {
      //check for contacts with no primary email address
      $id  = CRM_Hubspot_Utils::getContactFromEmail($params['EMAIL'], FALSE);

      if(count($id) > 1) {
        return NULL;
      }
      if(count($id) == 1) {
        $contactParams  = CRM_Hubspot_Utils::updateParamsExactMatch($id, $params);
        $params['status']['Updated']  = 1;
      }
      // Else create new contact
      if(empty($id)) {
        $params['status']['Added']  = 1;
      }
      
    }
    // Create/Update Contact details
    $contactResult = civicrm_api('Contact' , 'create' , $contactParams);

    return $contactResult['id'];
  }
  
  static function getContactFromEmail($email, $primary = TRUE) {
    $primaryEmail  = 1;
    if(!$primary) {
     $primaryEmail = 0;
    }
    $contactids = array();
    $query = "
      SELECT `contact_id` FROM civicrm_email ce
      INNER JOIN civicrm_contact cc ON ce.`contact_id` = cc.id
      WHERE ce.email = %1 AND ce.is_primary = {$primaryEmail} AND cc.is_deleted = 0 ";
    $dao   = CRM_Core_DAO::executeQuery($query, array( '1' => array($email, 'String'))); 
    while($dao->fetch()) {
      $contactids[] = $dao->contact_id;
    }
    return $contactids;
  }
  
  static function updateParamsExactMatch($contactids = array(), $params) {
    $contactParams =
        array(
          'version'       => 3,
          'contact_type'  => 'Individual',
          'first_name'    => $params['FNAME'],
          'last_name'     => $params['LNAME'],
          'email'         => $params['EMAIL'],
        );
    if(count($contactids) == 1) {
        $contactParams['id'] = $contactids[0];
        unset($contactParams['contact_type']);
        // Don't update firstname/lastname if it was empty
        if(empty($params['FNAME']))
          unset($contactParams['first_name']);
        if(empty($params['LNAME']))
          unset ($contactParams['last_name']);
      }

    return $contactParams;
  }

  /*
   * Function to get CiviCRM Groups for the specific Hubspot list in which the Contact is Added to
   */
  static function getGroupSubscriptionforHubspotList($listID, $contactID) {
    if (empty($listID) || empty($contactID)) {
      return NULL;
    }
    
    $civiMcGroups = array();
    $query  = "
      SELECT  entity_id
      FROM    civicrm_value_hubspot_settings hss
      WHERE   hs_list_id = %1";
    $params = array('1' => array($listID, 'String'));
    
    $dao = CRM_Core_DAO::executeQuery($query ,$params);
    while ($dao->fetch()) {
      $groupContact = new CRM_Contact_BAO_GroupContact();
      $groupContact->group_id = $dao->entity_id;
      $groupContact->contact_id = $contactID;
      $groupContact->whereAdd("status = 'Added'");
      $groupContact->find();
      if ($groupContact->fetch()) {
        $civiMcGroups[] = $dao->entity_id;
      }
    }
    return $civiMcGroups;
  }
  
   /*
   * Function to delete Hubspot contact for given CiviCRM email ID
   */
  static function deleteHSEmail($email) {
    if (empty($email)) {
      return NULL;
    }
    crm_core_error::debug_var('deleteHSEmail $email', $email);
    // sync contacts using batchunsubscribe
    $hubSpot = CRM_Hubspot_Utils::hubspot();
    $contact = $hubSpot->contacts()->getByEmail($email);
    crm_core_error::debug_var('deleteHSEmail $getByEmail', $contact['vid']);
    if(!empty($contact) && is_array($contact)) {
      $contacts = $hubSpot->contacts()->delete($contact['vid']);
          crm_core_error::debug_var('deleteHSEmail $delete', $contacts);
    }
  }
  
   /**
   * Function to call syncontacts with smart groups and static groups
   *
   * Returns object that can iterate over a slice of the live contacts in given group.
   */
  static function getGroupContactObject($groupID, $start = null) {
    $group           = new CRM_Contact_DAO_Group();
    $group->id       = $groupID;
    $group->find();

    if($group->fetch()){
      //Check smart groups (including parent groups, which function as smart groups).
      if($group->saved_search_id || $group->children){
        $groupContactCache = new CRM_Contact_BAO_GroupContactCache();
        $groupContactCache->group_id = $groupID;
        if ($start !== null) {
          $groupContactCache->limit($start, CRM_Hubspot_Form_Sync::BATCH_COUNT);
        }
        $groupContactCache->find();
        return $groupContactCache;
      }
      else {
        $groupContact = new CRM_Contact_BAO_GroupContact();
        $groupContact->group_id = $groupID;
        $groupContact->whereAdd("status = 'Added'");
        if ($start !== null) {
          $groupContact->limit($start, CRM_Hubspot_Form_Sync::BATCH_COUNT);
        }
        $groupContact->find();
        return $groupContact;
      }
    }
    return FALSE;
  }
   /**
   * Function to call syncontacts with smart groups and static groups xxx delete
   *
   * Returns object that can iterate over a slice of the live contacts in given group.
   */
  static function getGroupMemberships($groupIDs) {

    $group           = new CRM_Contact_DAO_Group();
    $group->id       = $groupID;
    $group->find();

    if($group->fetch()){
      //Check smart groups
      if($group->saved_search_id){
        $groupContactCache = new CRM_Contact_BAO_GroupContactCache();
        $groupContactCache->group_id = $groupID;
        if ($start !== null) {
          $groupContactCache->limit($start, CRM_Hubspot_Form_Sync::BATCH_COUNT);
        }
        $groupContactCache->find();
        return $groupContactCache;
      }
      else {
        $groupContact = new CRM_Contact_BAO_GroupContact();
        $groupContact->group_id = $groupID;
        $groupContact->whereAdd("status = 'Added'");
        if ($start !== null) {
          $groupContact->limit($start, CRM_Hubspot_Form_Sync::BATCH_COUNT);
        }
        $groupContact->find();
        return $groupContact;
      }
    }

    return FALSE;
  }
  
  /*
   * Function to subscribe/unsubscribe civicrm contact in Hubspot list
	 *
	 * $groupDetails - Array
	 *	(
	 *			[list_id] => ec641f8988
	 *		  [grouping_id] => 14397
	 *			[group_id] => 35609
	 *			[is_mc_update_grouping] => 
	 *			[group_name] => 
	 *			[grouping_name] => 
	 *			[civigroup_title] => Easter Newsletter
	 *			[civigroup_uses_cache] => 
	 * )
	 * 
	 * $action - subscribe/unsubscribe
   */
  static function subscribeOrUnsubsribeToHubspotList($groupDetails, $contactID, $action) {
    if (empty($groupDetails) || empty($contactID) || empty($action)) {
      return NULL;
    }
    
    // We need to get contact's email before subscribing in Hubspot
		$contactParams = array(
			'version'       => 3,
			'id'  					=> $contactID,
		);
		$contactResult = civicrm_api('Contact' , 'get' , $contactParams);
		// This is the primary email address of the contact
		$email = $contactResult['values'][$contactID]['email'];
		
		if (empty($email)) {
			// Its possible to have contacts in CiviCRM without email address
			// and add to group offline
			return;
		}
		
		// Optional merges for the email (FNAME, LNAME)
		$merge = array(
			'FNAME' => $contactResult['values'][$contactID]['first_name'],
			'LNAME' => $contactResult['values'][$contactID]['last_name'],
		);
	
		$listID = $groupDetails['list_id'];
		$grouping_id = $groupDetails['grouping_id'];
		$group_id = $groupDetails['group_id'];
		if (!empty($grouping_id) AND !empty($group_id)) {
			$merge_groups[$grouping_id] = array('id'=> $groupDetails['grouping_id'], 'groups'=>array());
			$merge_groups[$grouping_id]['groups'][] = CRM_Hubspot_Utils::getMCGroupName($listID, $grouping_id, $group_id);
			
			// remove the significant array indexes, in case Hubspot cares.
			$merge['groupings'] = array_values($merge_groups);
		}
		
		// Send Hubspot Lists API Call.
		$list = new Hubspot_Lists(CRM_Hubspot_Utils::hubspot());
		switch ($action) {
			case "subscribe":
				// http://apidocs.hubspot.com/api/2.0/lists/subscribe.php
				try {
					$result = $list->subscribe($listID, array('email' => $email), $merge, $email_type='html', $double_optin=FALSE, $update_existing=FALSE, $replace_interests=TRUE, $send_welcome=FALSE);
				}
				catch (Exception $e) {
          // Don't display if the error is that we're already subscribed.
          $message = $e->getMessage();
          if ($message !== $email . ' is already subscribed to the list.') {
            CRM_Core_Session::setStatus($message);
          }
				}
				break;
			case "unsubscribe":
				// https://apidocs.hubspot.com/api/2.0/lists/unsubscribe.php
				try {
					$result = $list->unsubscribe($listID, array('email' => $email), $delete_member=false, $send_goodbye=false, $send_notify=false);
				}
				catch (Exception $e) {
					CRM_Core_Session::setStatus($e->getMessage());
				}
				break;
		}
  }

  static function checkDebug($class_function = 'classname', $debug) {
    $debugging = CRM_Core_BAO_Setting::getItem(self::HS_SETTING_GROUP, 'enable_debugging', NULL, FALSE
    );

    if ($debugging == 1) {
      CRM_Core_Error::debug_var($class_function, $debug);
    }
  }
}
