<?php
use CRM_Corrections_ExtensionUtil as E;


/**
 * CommPref.Fix API
 *
 * One Time Scheduled Job to fix the Preferred Communicaiton Methods in civicrm_contact that
 * are stored without VALUE SEPARATOR
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_comm_pref_Fix($params) {
  $count = 0;
  $query = "SELECT id, preferred_communication_method FROM civicrm_contact WHERE length(preferred_communication_method) = %1 LIMIT 2500";
  $dao = CRM_Core_DAO::executeQuery($query, [1 => [1, 'Integer']]);
  while ($dao->fetch()) {
    $new = CRM_Core_DAO::VALUE_SEPARATOR . $dao->preferred_communication_method . CRM_Core_DAO::VALUE_SEPARATOR;
    $update = "UPDATE civicrm_contact SET preferred_communication_method = %1 WHERE id = %2";
    CRM_Core_DAO::executeQuery($update, [
      1 => [$new, 'String'],
      2 => [$dao->id, 'Integer'],
    ]);
    $count++;
  }
  return civicrm_api3_create_success([$count . ' communication methods fixed'], $params, 'CommPref', 'Fix');
}
