<?php
use CRM_Corrections_ExtensionUtil as E;

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
  $returnValues = [];
  $container = CRM_Extension_System::singleton()->getFullContainer();
  $resourcesPath = $container->getPath('be.aivl.corrections').'/resources/';
  // initialize logger
  // check if folder
  if (!is_dir($resourcesPath)) {
    $message = E::ts('The resources folder ') . $resourcesPath . E::ts(' is not a valid folder or you have no access rights.');
    Civi::log()->error($message);
    return civicrm_api3_create_error($message);
  }
  else {
    $fileName = $resourcesPath.'groups_to_be_deleted.csv';
    $sourceFile = fopen($fileName, 'r');
    while (($sourceData = fgetcsv($sourceFile, 0, ";")) !== FALSE) {
      _processGroup($sourceData, $returnValues);
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'Group', 'Cleanup');
}

/**
 * Function to process group
 *
 * @param $sourceData
 * @param $returnValues
 */
function _processGroup($sourceData, &$returnValues) {
  try {
    civicrm_api3('Group', 'delete', ['id' => $sourceData[0]]);
    $message = E::ts('Group ID ') . $sourceData[0] . E::ts(' with title ') . $sourceData[2] . E::ts(' was removed');
    Civi::log()->info($message);
    $returnValues[] = $message;
  }
  catch (CiviCRM_API3_Exception $ex) {
    Civi::log()->error(E::ts('Could not find a group with ID ') . $sourceData[0] . E::ts(' and title ') . $sourceData[2]);
  }
}