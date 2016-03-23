<?php
use Fungku\HubSpot\Api\ContactLists;
use Fungku\HubSpot\Http\Client;
use Fungku\HubSpot\HubSpotService;
  
require_once 'hubspot.civix.php';
//require_once 'packages/hubspot/src/HubSpotService.php';
  
  
/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function hubspot_civicrm_config(&$config) {
  _hubspot_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function hubspot_civicrm_xmlMenu(&$files) {
  _hubspot_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function hubspot_civicrm_install() {
  _hubspot_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function hubspot_civicrm_uninstall() {
  _hubspot_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function hubspot_civicrm_enable() {
  _hubspot_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function hubspot_civicrm_disable() {
  _hubspot_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function hubspot_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _hubspot_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function hubspot_civicrm_managed(&$entities) {
  _hubspot_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function hubspot_civicrm_caseTypes(&$caseTypes) {
  _hubspot_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function hubspot_civicrm_angularModules(&$angularModules) {
_hubspot_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function hubspot_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _hubspot_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function hubspot_civicrm_preProcess($formName, &$form) {

}

*/

/**
 * Implementation of hook_civicrm_buildForm
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_buildForm
 */
function hubspot_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Group_Form_Edit' && ($form->getAction() == CRM_Core_Action::ADD OR $form->getAction() == CRM_Core_Action::UPDATE)) {
    // Get all the Hubspot lists
    $lists = CRM_Hubspot_Utils::hubspotGetList();
    if(!empty($lists)){
      foreach($form->_groupTree as $group){
        if($group['title'] == "Hubspot Settings" ){
          foreach($group['fields'] as $field){
            if($field['label'] == "Hubspot List"){
              if (array_key_exists( $field['element_name'], $form->_elementIndex)) {
                $form->removeElement($field['element_name']);
              }
              $form->add('select', $field['element_name'], ts('Hubspot List'), array('' => '- select -') + $lists);
            } 
          }
        }
      }
    }
  }
}



/**
 * Implementation of hook_civicrm_pre
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */
function hubspot_civicrm_pre( $op, $objectName, $id, &$params ) {
  $params1 = array(
    'version' => 3,
    'sequential' => 1,
    'contact_id' => $id,
    'id' => $id,
  );

  if($objectName == 'Email') {
    // If about to delete an email in CiviCRM, we must delete it from Hubspot
    // because we won't get chance to delete it once it's gone.
    //
    // The other case covered here is changing an email address's status
    // from for-bulk-mail to not-for-bulk-mail.
    // @todo Note: However, this will delete a subscriber and lose reporting
    // info, where what they might have wanted was to change their email
    // address.
        crm_core_error::debug_var('pre $email hook', $params);
    $on_hold     = CRM_Utils_Array::value('on_hold', $params);
    $is_bulkmail = CRM_Utils_Array::value('is_bulkmail', $params);
    if( ($op == 'delete') ||
        ($op == 'edit' && $on_hold == 1 && $is_bulkmail == 1)
    ) {
      $email = new CRM_Core_BAO_Email();
      $email->id = $id;
      $email->find(TRUE);
        crm_core_error::debug_var('pre $email object', $email);
      if ($op == 'delete' || $email->on_hold == 0 ) {
        CRM_Hubspot_Utils::deleteHSEmail($email->email);
      }
    }
  }

  // If deleting an individual, delete their (bulk) email address from Hubspot.
  if ($op == 'delete' && $objectName == 'Individual') {
    $result = civicrm_api('Contact', 'get', $params1);
    foreach ($result['values'] as $key => $value) {
      $email  = $value['email'];
      if ($email) {
        CRM_Hubspot_Utils::deleteHSEmail($email);
      }
    }
  }
}


/**
 * Added by Mathavan@vedaconsulting.co.uk to fix the navigation Menu URL
 * Implementation of hook_civicrm_navigationMenu
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_permission
 */
function hubspot_civicrm_navigationMenu(&$params){
  $parentId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Mailings', 'id', 'name');
  $hubspotSettings  = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Hubspot_Settings', 'id', 'name');
  $hubspotSync      = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Hubspot_Sync', 'id', 'name');
  $hubspotpPull      = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Hubspot_Pull', 'id', 'name');
  $maxId              = max(array_keys($params));
  $hubspotMaxId     = empty($hubspotSettings) ? $maxId+1         : $hubspotSettings;
  $hubspotsyncId    = empty($hubspotSync)     ? $hubspotMaxId+1  : $hubspotSync;
  $hubspotPullId    = empty($hubspotPull)     ? $hubspotsyncId+1 : $hubspotPull;


  $params[$parentId]['child'][$hubspotMaxId] = array(
        'attributes' => array(
          'label'     => ts('Hubspot Settings'),
          'name'      => 'Hubspot_Settings',
          'url'       => CRM_Utils_System::url('civicrm/hubspot/settings', 'reset=1', TRUE),
          'active'    => 1,
          'parentID'  => $parentId,
          'operator'  => NULL,
          'navID'     => $hubspotMaxId,
          'permission'=> 'administer CiviCRM',
        ),
  );
  $params[$parentId]['child'][$hubspotsyncId] = array(
        'attributes' => array(
          'label'     => ts('Sync Civi Contacts To Hubspot'),
          'name'      => 'Hubspot_Sync',
          'url'       => CRM_Utils_System::url('civicrm/hubspot/sync', 'reset=1', TRUE),
          'active'    => 1,
          'parentID'  => $parentId,
          'operator'  => NULL,
          'navID'     => $hubspotsyncId,
          'permission'=> 'administer CiviCRM',
        ),
  );
  $params[$parentId]['child'][$hubspotPullId] = array(
        'attributes' => array(
          'label'     => ts('Sync Hubspot Contacts To Civi‏'),
          'name'      => 'Hubspot_Pull',
          'url'       => CRM_Utils_System::url('civicrm/hubspot/pull', 'reset=1', TRUE),
          'active'    => 1,
          'parentID'  => $parentId,
          'operator'  => NULL,
          'navID'     => $hubspotPullId,
          'permission'=> 'administer CiviCRM',
        ),
  );
}

/**
 * Implementation of hook_civicrm_post
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_post
 */
function hubspot_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
	
	/***** NO BULK EMAILS (User Opt Out) *****/
	if ($objectName == 'Individual' || $objectName == 'Organization' || $objectName == 'Household') {
		// Contact Edited
		if ($op == 'edit' || $op == 'create') {
			if($objectRef->is_opt_out == 1) {
				$action = 'unsubscribe';
			} else {
				$action = 'subscribe';
			}
			
			// Get all groups, the contact is subscribed to
			$civiGroups = CRM_Contact_BAO_GroupContact::getGroupList($objectId);
			$civiGroups = array_keys($civiGroups);

			if (empty($civiGroups)) {
				return;
			}

			// Get Hubspot details
			$groups = CRM_Hubspot_Utils::getGroupsToSync($civiGroups);
			
			if (!empty($groups)) {
				// Loop through all groups and unsubscribe the email address from Hubspot
				foreach ($groups as $groupId => $groupDetails) {
					CRM_Hubspot_Utils::subscribeOrUnsubsribeToHubspotList($groupDetails, $objectId, $action);
				}
			}
		}
	}

	/***** Contacts added/removed/deleted from CiviCRM group *****/
	if ($objectName == 'GroupContact') {
		
    // FIXME: Dirty hack to skip hook
		require_once 'CRM/Core/Session.php';
    $session = CRM_Core_Session::singleton();
    $skipPostHook = $session->get('skipPostHook');
	
		// Added/Removed/Deleted - This works for both bulk action and individual add/remove/delete
		if (($op == 'create' || $op == 'edit' || $op == 'delete') && empty($skipPostHook)) {
			// Decide Hubspot action based on $op
			// Add / Rejoin Group
			if ($op == 'create' || $op == 'edit') {
				$action = 'subscribe';
			}
			// Remove / Delete
			elseif ($op == 'delete') {
				$action = 'unsubscribe';
			}
		
			// Get Hubspot details for the group
			$groups = CRM_Hubspot_Utils::getGroupsToSync(array($objectId));
			
			// Proceed only if the group is configured with mailing list/groups
			if (!empty($groups[$objectId])) {
				// Loop through all contacts added/removed from the group
				foreach ($objectRef as $contactId) {
					// Subscribe/Unsubscribe in Hubspot
					CRM_Hubspot_Utils::subscribeOrUnsubsribeToHubspotList($groups[$objectId], $contactId, $action);
				}
			}
		}		
	}
}
