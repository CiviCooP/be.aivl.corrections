<?php

/**
 * Class for processing unsubscribes from website until we have final solution (issue 2199)
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 27 Dec 2017
 * @license AGPL-3.0
 */
class CRM_Corrections_Unsubscribe {

  private $_logger = NULL;
  private $_filePath = NULL;
  private $_files = NULL;
  private $_data = array();
  private $_readHeader = FALSE;

  /*
   *
   */
  function __construct() {
    $this->_logger = new CRM_Corrections_Logger('web_unsubscribe');
    $this->_readHeader = FALSE;
  }

  /**
   * Method to get the csv files to read
   */
  public function process() {
    // get file path and all csv files within
    $this->getCsvPath();
    if ($this->_filePath) {
      $this->_files = glob($this->_filePath. "*.csv");
    }
    // read each file and add records to data array
    foreach ($this->_files as $fileName) {
      $file = fopen($fileName, 'r');
      while (!feof($file)) {
        $record = fgetcsv($file, 0,';');
        if ($this->canProcessRecord($record)) {
          $this->_data[] = array(
            'first_name' => $record[9],
            'last_name' => $record[10],
            'birth_date' => date('Ymd', strtotime($record[11])),
            'email' => $record[12],
            'unsubscribe' => $record[13],
          );
        }
      }
      fclose($file);
    }
    // unsubscribe all contacts in data array
    $this->unsubscribe();
  }

  /**
   * Method to determine if record can be processed
   *
   * @param $record
   * @return bool
   */
  private function canProcessRecord($record) {
    if (empty($record)) {
      $this->_logger->logMessage('Error', 'Could not read a record in the file, please check for special characters!');
      return FALSE;
    }
    // once we have read the header we can process each following record
    if ($this->_readHeader == TRUE) {
      return TRUE;
    }
    // switch flag on if this is the header record
    if ($this->isHeader($record) == TRUE) {
      $this->_readHeader = TRUE;
      return FALSE;
    }
    // in all other cases record can not be processed and has to be skipped
    return FALSE;
  }

  /**
   * Method to determine if read record is the header
   *
   * @param $record
   * @return bool
   */
  private function isHeader($record) {
    $headers = array(
      'Serienummer',
      'SID',
      'Tijdstip van indienen',
      'Verwerkingstijd',
      'Tijdstip van wijziging',
      'Kladversie',
      'IP-adres',
      'UID',
      'Gebruikersnaam',
      'Voornaam',
      'Achternaam',
      'Geboortedatum',
      'E-mailadres',
      'Ik wil geen e-mails meer ontvangen.',
      );
    foreach ($headers as $key => $value) {
      if (trim($record[$key]) != trim($value)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Method to unsubscribe contacts if found
   */
  private function unsubscribe() {
    foreach ($this->_data as $data) {
      // find contact with email
      try {
        $foundEmails = civicrm_api3('Email', 'get', array(
          'email' => $data['email'],
        ));
        foreach ($foundEmails['values'] as $foundEmail) {
          try {
            civicrm_api3('Contact', 'create', array(
              'is_opt_out' => 1,
              'id' => $foundEmail['contact_id'],
            ));
          }
          catch (CiviCRM_API3_Exception $ex) {
          }
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Warning', 'No contact found with email '.$data['email']);
      }

    }

  }

  /**
   * Method to get the path where the csv files should be
   *
   */
  private function getCsvPath() {
    try {
      $civiVersion = civicrm_api3('Domain', 'getvalue', array(
        'return' => "version",
        'id' => 1,
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->_logger->logMessage('Error', 'Could not find a valid file path in '.__METHOD__);
      return FAlSE;
    }
    $civiVersion = (float) round($civiVersion, 1);
    if ($civiVersion >= 4.7) {
      $container = CRM_Extension_System::singleton()->getFullContainer();
      $this->_filePath = $container->getPath('be.aivl.corrections') . '/resources/unsubscribes/';
    } else {
      try {
        $extensionsDir = civicrm_api3('Setting', 'getvalue', array(
          'name' => "extensionsDir",
        ));
        $this->_filePath = $extensionsDir.'/be.aivl.corrections/resources/unsubscribes/';
      }
      catch (CiviCRM_API3_Exception $ex) {
        $this->_logger->logMessage('Error', 'Could not execute the Setting API in '.__METHOD__);
        return FAlSE;
      }
    }
  }


}