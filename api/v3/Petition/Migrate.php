<?php

/**
 * Petition.Migrate API
 * One time job to pick up all the petition that were entered in the old environment,
 * check if the activity already exists and if not, process the petition on the new environment
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_petition_Migrate($params) {
  $returnValues = array('Importing petitions complete, check log in sites/default/files/civicrm/ConfigAndLog');
  $petitionSignature = new CRM_Corrections_PetitionSignature();
  // read json file with petition signatures
  $config = CRM_Core_Config::singleton();
  $baseFolder = $config->customFileUploadDir;
  $parts = explode('custom/', $baseFolder);
  $sourceFolder = $parts[0];
  $log = fopen($sourceFolder.'petition_signatures.log', 'r');
  while (!feof($log)) {
    $logLine = fgets($log, 4096);
    $petitionSignature->import(json_decode($logLine));
  }
  return civicrm_api3_create_success($returnValues, $params, 'Petition', 'Migrate');
}
