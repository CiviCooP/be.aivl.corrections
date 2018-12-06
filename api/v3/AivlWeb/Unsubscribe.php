<?php

/**
 * AivlWeb.Unsubscribe API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_aivl_web_Unsubscribe($params) {
  $unsubscribe = new CRM_Corrections_Unsubscribe();
  $unsubscribe->process();
  return civicrm_api3_create_success([], $params, 'AivlWeb', 'Unsubscribe');
}
