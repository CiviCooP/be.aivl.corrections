<?php
use CRM_Corrections_ExtensionUtil as E;


/**
 * Class for fixing the contribution net amount validation
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 22 Jan 2019
 * @license AGPL-3.0
 */
class CRM_Corrections_Contribution {
  /**
   * Method to process the validateForm hook for CRM_Contribute_Form_Contribution
   * @param $fields
   * @param $form
   */
  public static function validateForm(&$fields, &$form) {
    if (isset($fields['total_amount']) && isset($fields['net_amount'])) {
      if ($fields['total_amount'] != $fields['net_amount']) {
        $form->setElementError('total_amount', NULL);
        $data = &$form->controller->container();
        if (!isset($fields['fee_amount']) || $fields['fee_amount'] == 0) {
          $data['values']['Contribution']['net_amount'] = $fields['total_amount'];
        }
        else {
          $data['values']['Contribution']['net_amount'] = $fields['total_amount'] - $fields['fee_amount'];
        }
      }
    }
  }
}