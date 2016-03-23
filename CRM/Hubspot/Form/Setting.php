<?php

require_once 'CRM/Core/Form.php';

class CRM_Hubspot_Form_Setting extends CRM_Core_Form {

  const 
    HS_SETTING_GROUP = 'Hubspot Preferences';
  
   /**
   * Function to pre processing
   *
   * @return None
   * @access public
   */
  function preProcess() { 
    $currentVer = CRM_Core_BAO_Domain::version(TRUE);
    //if current version is less than 4.4 dont save setting
    if (version_compare($currentVer, '4.4') < 0) {
      CRM_Core_Session::setStatus("You need to upgrade to version 4.4 or above to work with extension Hubspot","Version:");
    }
  }  
  
  public static function formRule($params){
    $currentVer = CRM_Core_BAO_Domain::version(TRUE);
    $errors = array();
    if (version_compare($currentVer, '4.4') < 0) {        
      $errors['version_error'] = " You need to upgrade to version 4.4 or above to work with extension Hubspot";
    }
    return empty($errors) ? TRUE : $errors;
  }
  
  /**
   * Function to actually build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $this->addFormRule(array('CRM_Hubspot_Form_Setting', 'formRule'), $this);
    
    CRM_Core_Resources::singleton()->addStyleFile('com.civibridge.hubspot', 'css/hubspot.css');  
    
    // Add the API Key Element
    $this->addElement('text', 'api_key', ts('API Key'), array(
      'size' => 48,
    ));    
    
    // Add Enable or Disable Debugging
    $enableOptions = array(1 => ts('Yes'), 0 => ts('No'));
    $this->addRadio('enable_debugging', ts('Enable Debugging'), $enableOptions, NULL);
    
    // Create the Submit Button.
    $buttons = array(
      array(
        'type' => 'submit',
        'name' => ts('Save & Test'),
      ),
    );
    // Add the Buttons.
    $this->addButtons($buttons);
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = $details = array();

    $apiKey = CRM_Core_BAO_Setting::getItem(self::HS_SETTING_GROUP,
      'api_key', NULL, FALSE
    );
    
    $enableDebugging = CRM_Core_BAO_Setting::getItem(self::HS_SETTING_GROUP,
      'enable_debugging', NULL, FALSE
    );
    $defaults['api_key'] = $apiKey;
    $defaults['enable_debugging'] = $enableDebugging;
    
    return $defaults;
  }

  /**
   * Function to process the form
   *
   * @access public
   *
   * @return None
   */
  public function postProcess() {
    // Store the submitted values in an array.
    $params = $this->controller->exportValues($this->_name);    
      
    // Save the API Key & Save the Security Key
    if (CRM_Utils_Array::value('api_key', $params)) {
      CRM_Core_BAO_Setting::setItem($params['api_key'],
        self::HS_SETTING_GROUP,
        'api_key'
      );

      CRM_Core_BAO_Setting::setItem($params['enable_debugging'], self::HS_SETTING_GROUP, 'enable_debugging'
      );
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }  
}

