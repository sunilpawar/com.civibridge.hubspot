<?php
// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array(
  0 => array(
    'name' => 'Hubspot Contact Sync',
    'entity' => 'Job',
    'params' => array(
    	'version' => 3,
    	'api_entity' => "job",
      'sequential' => 1,
      'run_frequency' => "Daily",
      'name' => "Hubspot Contact Sync",
      'api_action' => "hubspot_contact_sync",
    ),
  ),
);
