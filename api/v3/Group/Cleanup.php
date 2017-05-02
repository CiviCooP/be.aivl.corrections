<?php

/**
 * Group.Cleanup API
 * One Time API for AIVL to remove groups based on csv input file
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_group_Cleanup($params) {
  $returnValues = array();
  $settings = civicrm_api3('Setting', 'Getsingle', array());
  $resourcesPath = $settings['extensionsDir'].'/be.aivl.corrections/resources/';
  // initialize logger
  $logger = new CRM_Corrections_Logger('group_remove_');
  // check if folder
  if (!is_dir($resourcesPath)) {
    $message = 'The resources folder '.$resourcesPath.' is not a valid folder or you have no access rights.';
    $logger->logMessage('Error', $message);
    return civicrm_api3_create_error($message);
  } else {
    $fileName = $resourcesPath.'groups_to_be_deleted.csv';
    $sourceFile = fopen($fileName, 'r');
    while (($sourceData = fgetcsv($sourceFile, 0, ";")) !== FALSE) {
      _processGroup($sourceData, $returnValues, $logger);
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'NewEntity', 'NewAction');
}

/**
 * Function to process group
 *
 * @param $sourceData
 * @param $returnValues
 * @param $logger
 */
function _processGroup($sourceData, &$returnValues, $logger) {
  try {
    civicrm_api3('Group', 'delete', array('id' => $sourceData[0]));
    $message = 'Group ID '.$sourceData[0].' with title '.$sourceData[2].' was removed';
    $logger->logMessage('Info', $message);
    $returnValues[] = $message;
  }
  catch (CiviCRM_API3_Exception $ex) {
    $logger->logMessage('Error', 'Could not find a group with ID '.$sourceData[0].' and title '.$sourceData[2]);
  }
}