<?php

/**
 * Hubspot API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
 

/**
 * Hubspot Get Hubspot Lists API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_hubspot_getcontactsbylist($params) {
  if (empty($params['listid'])) {
    return array();
  }
  $hubSpot = CRM_Hubspot_Utils::hubspot();
  $listContacts = $hubSpot->contactLists()->contacts($params['listid'], array('count' => 1000000, 'property'=> array('firstname', 'lastname', 'email')));
  $contacts = array();
  foreach ($listContacts['contacts'] as $contact) {
    $contacts[$contact['vid']]['firstname'] = $contact['properties']['firstname']['value'];
    $contacts[$contact['vid']]['lastname']  = $contact['properties']['lastname']['value'];
    $contacts[$contact['vid']]['email']     = $contact['properties']['email']['value'];
    $contacts[$contact['vid']]['hash']      = $contact['identity-profiles'][0]['identities'][1]['value'];
  }

  return civicrm_api3_create_success($contacts);
}


/**
 * Hubspot Get Hubspot Membercount API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_hubspot_getmembercount($params) {
  $mcLists = new Hubspot_Lists(CRM_Hubspot_Utils::hubspot());
  
  $results = $mcLists->getList();
  $listmembercount = array();
  foreach($results['data'] as $list) {
    $listmembercount[$list['id']] = $list['stats']['member_count'];
  }

  return civicrm_api3_create_success($listmembercount);
}

/**
  * Hubspot Get CiviCRM Group Hubspot settings (Hubspot List Id and Group)
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_hubspot_getcivicrmgroupmailchimpsettings($params) {
  $groupIds = empty($params['ids']) ? array() : explode(',', $params['ids']);
  $groups  = CRM_Hubspot_Utils::getGroupsToSync($groupIds);
  return civicrm_api3_create_success($groups);
}

/**
 * CiviCRM to Hubspot Sync
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */ 
function civicrm_api3_hubspot_sync($params) {
  $groups = CRM_Hubspot_Utils::getGroupsToSync(array(), null);
  foreach ($groups as $group_id => $details) {
    $list           = new Hubspot_Lists(CRM_Hubspot_Utils::hubspot());
    $webhookoutput  = $list->webhooks($details['list_id']);
    if($webhookoutput[0]['sources']['api'] == 1) {
      return civicrm_api3_create_error('civicrm_api3_hubspot_sync -  API is set in Webhook setting for listID '.$details['list_id'].' Please uncheck API' );
    }
  }
  $result = $pullResult = array();
	
	// Do pull first from mailchimp to CiviCRM
	$pullRunner = CRM_Hubspot_Form_Pull::getRunner($skipEndUrl = TRUE);
	if ($pullRunner) {
    $pullResult = $pullRunner->runAll();
  }
	
	// Do push from CiviCRM to mailchimp 
  $runner = CRM_Hubspot_Form_Sync::getRunner($skipEndUrl = TRUE);
  if ($runner) {
    $result = $runner->runAll();
  }

  if ($pullResult['is_error'] == 0 && $result['is_error'] == 0) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error();
  }
}

