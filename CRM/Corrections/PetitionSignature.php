<?php

/**
 * Class for processing petition signatures (import on new environment)
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 7 Oct 2017
 * @license AGPL-3.0
 */
class CRM_Corrections_PetitionSignature {

  private $_logger = NULL;
  private $_email = NULL;
  private $_campaignId = NULL;
  private $_submissionDate = NULL;
  private $_petitionActivityTypeId = NULL;
  private $_targetRecordTypeId = NULL;

  /**
   * CRM_Corrections_PetitionSignature constructor.
   */
  function __construct() {
    $this->_logger = new CRM_Corrections_Logger('petition_import');
    $this->_email = NULL;
    $this->_submissionDate = NULL;
    $this->_petitionActivityTypeId = CRM_Webcontacts_Config::singleton()->getPetitionActivityTypeId();
    $this->_targetRecordTypeId = civicrm_api3('OptionValue', 'getvalue', array(
      'option_group_id' => 'activity_contacts',
      'name' => 'Activity Targets',
      'return' => 'value',
    ));
  }

  /**
   * Method to import the petition signature
   *
   * @param $sourceData
   */
  public function import($sourceData) {
    // log the petition signature opened
    $this->_logger->logMessage('Info', 'Opened submission '.$sourceData->sid);
    // if petition identifying data could be extracted
    if ($this->extractPetitionData($sourceData)) {
      // check if there is any contact with the email
      $contactCount = civicrm_api3('Contact', 'getcount', array(
        'email' => $this->_email,
      ));
      // if no contact found, petition does not exist yet so continue. Else check if already exists
      if ($contactCount == 0) {
        $this->processPetition($sourceData);
        $this->_logger->logMessage('Info', 'Imported petition '.$sourceData->sid. 'with API');
      } else {
        if ($this->petitionAlreadyExists($contactCount) == FALSE) {
          $this->processPetition($sourceData);
          $this->_logger->logMessage('Info', 'Imported petition '.$sourceData->sid. 'with API');
        }
      }
    }
  }

  /**
   * Mwthod to extract petition data so I can check if the petition already exists.
   * I need contact_id, activity_type_id and activity_date_time (submission date)
   *
   * @param $sourceData
   * @return bool
   */
  private function extractPetitionData($sourceData) {
    $this->_submissionDate = new DateTime($sourceData->submission_date);
    foreach ($sourceData->data as $fieldId => $fieldData) {
      if (trim($fieldData->field_key) == 'petition_email') {
        $this->_email = $fieldData->field_value[0];
      }
      if (trim($fieldData->field_key) == 'petition_campaign') {
        $this->_campaignId = $fieldData->field_value[0];
      }
    }
    // error if email is empty
    if (empty($this->_email)) {
      $this->_logger->logMessage(' Error', 'Submission ' . $sourceData->sid . ' has no email, ignored');
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Method to check if there already is a petition for the same email and date
   *
   * @param int $contactCount
   * @return bool
   */
  private function petitionAlreadyExists($contactCount) {
    $contactIds = array();
    // if count = 1, single contact else many
    if ($contactCount == 1) {
      $contactIds[] = civicrm_api3('Contact', 'getvalue', array(
        'email' => $this->_email,
        'return' => 'id',
      ));
    } else {
      $contacts = civicrm_api3('Contact', 'get', array(
        'email' => $this->_email,
        'return' => 'id',
        'options' => array('limit' => 0),
      ));
      foreach ($contacts['values'] as $contact) {
        $contactIds[] = $contact['id'];
      }
    }
    // check if there is a petition on the contact for the same date / campaign
    foreach ($contactIds as $contactId) {
      $petitionCount = $this->countPetitionsForContact($contactId);
      if ($petitionCount > 0) {
        $this->_logger->logMessage('Info', 'Found an existing petition activity for contact '
          .$contactId.' and campaign '.$this->_campaignId.' on date '.$this->_submissionDate->format('d-mÝ')
          .', ignored.');
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Method to count the existing petitions for contact
   *
   * @param $contactId
   * @return int
   */
  private function countPetitionsForContact($contactId) {
    if (!empty($this->_campaignId)) {
      $query = 'SELECT COUNT(*) FROM civicrm_activity ca
        JOIN civicrm_activity_contact cac ON ca.id = cac.activity_id AND cac.contact_id = %1 
        AND cac.record_type_id = %2
        WHERE ca.activity_type_id = %3 AND ca.is_deleted = %4 AND ca.is_current_revision = %5  
        AND ca.campaign_id = %6 AND ca.activity_date_time BETWEEN %7 AND %8';
      $queryParams = array(
        1 => array($contactId, 'Integer'),
        2 => array($this->_targetRecordTypeId, 'Integer'),
        3 => array($this->_petitionActivityTypeId, 'Integer'),
        4 => array(0, 'Integer'),
        5 => array(1, 'Integer'),
        6 => array($this->_campaignId, 'Integer'),
        7 =>array($this->_submissionDate->format('Y-m-d').' 00:00:00', 'String'),
        8 => array($this->_submissionDate->format('Y-m-d').' 23:59:59', 'String')
      );
    } else {
      $query = 'SELECT COUNT(*) FROM civicrm_activity ca
        JOIN civicrm_activity_contact cac ON ca.id = cac.activity_id AND cac.contact_id = %1 
        AND cac.record_type_id = %2
        WHERE ca.activity_type_id = %3 AND ca.is_deleted = %4 AND ca.is_current_revision = %5  
        AND ca.activity_date_time BETWEEN %6 AND %7';
      $queryParams = array(
        1 => array($contactId, 'Integer'),
        2 => array($this->_targetRecordTypeId, 'Integer'),
        3 => array($this->_petitionActivityTypeId, 'Integer'),
        4 => array(0, 'Integer'),
        5 => array(1, 'Integer'),
        6 =>array($this->_submissionDate->format('Y-m-d').' 00:00:00', 'String'),
        7 => array($this->_submissionDate->format('Y-m-d').' 23:59:59', 'String')
      );
    }
    return CRM_Core_DAO::singleValueQuery($query, $queryParams);
  }

  /**
   * Method to process the actual petition into CiviCRM
   *
   * @param $sourceData
   */
  private function processPetition($sourceData) {
    $params = array();
    // build array from object
    foreach ($sourceData as $key => $value) {
      if (!is_object($value)) {
        $params[$key] = $value;
      }
    }
    foreach ($sourceData->data as $dataKey => $dataValue) {
      $params['data'][$dataKey] = get_object_vars($dataValue);
    }
    $petition = new CRM_Webcontacts_Petition($params);
    $petition->processSubmission();
  }
}