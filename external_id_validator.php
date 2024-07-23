<?php

require_once 'external_id_validator.civix.php';

use CRM_ExternalIdValidator_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function external_id_validator_civicrm_config(&$config): void {
  _external_id_validator_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function external_id_validator_civicrm_install(): void {
  _external_id_validator_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function external_id_validator_civicrm_enable(): void {
  _external_id_validator_civix_civicrm_enable();
}



function external_id_validator_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors): void {
  if ($formName == "CRM_Contact_Form_Contact"){
    $contactType = $form->_contactType;
    if ($contactType == "Individual"){

      $FIN = CRM_Utils_Array::value('external_identifier', $fields );

      if (strlen($FIN) > 0){
        //check if external id is less or equal to 9
        if (strlen($FIN) != 9){
          $errors['external_identifier'] = ts( 'NRIC/FIN must have 9 characters' );
        } else {
          $upperFIN = strtoupper($FIN);
          $prefix = substr($upperFIN, 0, 1);
          $suffixList = [];
          $suffix = substr($upperFIN, -1);
          $digits = substr($upperFIN, 1, -1);

          if (!extidvald_validLetter($prefix)){
            $errors['external_identifier'] = ts( 'Prefix must be a letter.' );
            return;
          }
          if (!extidvald_validLetter($suffix)){
            $errors['external_identifier'] = ts( 'Suffix must be a letter.' );
            return;
          }

          if (extidvald_validIntString($digits)){
            switch ($prefix) {
              case "T":
              case "S":
                  $suffixList = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "Z", "J"];
                  break;
              case "G":
              case "F":
                  $suffixList = ["K", "L", "M", "N", "P", "Q", "R", "T", "U", "W", "X"];
                  break;
              case "M":
                  $suffixList = ["K", "L", "J", "N", "P", "Q", "R", "T", "U", "W", "X"];
                  break;
              default:
                  $errors['external_identifier'] = ts('Invalid Prefix! (Must be T, S, G, F, or M)');
                  return;
            }
    
            
            //suffix of id is a checksum, obtained via modulus 11 method;
            $calculateSuffix = $suffixList[extidvald_calculateChecksum($upperFIN, $prefix)];
            
            //suffix of id is a checksum, will check if input suffix and calculatedsuffix is the same;
            if ($suffix != $calculateSuffix){
              $errors['external_identifier'] = ts( 'Suffix is incorrect.');
            }
          } else {
            $errors['external_identifier'] = ts( 'Digits must be numbers.' );
          }
        }
      } else {
      }

    } else if ($contactType == "Organization"){
      $UEN = CRM_Utils_Array::value('external_identifier', $fields );
      if (strlen($UEN) > 0){
        $upperUEN = strtoupper($UEN);
        $lengthUEN = strlen($upperUEN);
        //FOR BUISNESSES 
        if ($lengthUEN == 9){
          $digits = substr($upperUEN, 0, -1);
          $letter = substr($upperUEN, -1);

          //c'eck if digits are numbers
          if (!extidvald_validIntString($digits)){
            $errors['external_identifier'] = ts('Buisness UEN : must have 8 numbers followed by a letter');
            return;
          }
          //ceck if last digit is a letter
          if (!extidvald_validLetter($letter)){
            $errors['external_identifier'] = ts('Buisness UEN : last char cannot be integer' . $letter);
            return;
          }
        } else if ($lengthUEN == 10){ // FOR COMPANIES + ENTITIES
          $firstInd = $upperUEN[0];
          //COMPANY
          if (!extidvald_validLetter($firstInd)){
            $digits = substr($upperUEN, 0 , 9);
            $letter = substr($upperUEN, -1);
            if (!extidvald_validIntString($digits)){
              $errors['external_identifier'] = ts('Companies UEN : contain 4 numbers for year, followed by 5 numbers and a letter');
              return;
            }

            if (!extidvald_validLetter($letter)){
              $errors['external_identifier'] = ts('Companies UEN : last char cannot be integer');
              return;
            }
          } else { // ENTITIES
            if ($firstInd == "T" || $firstInd == "R" ||$firstInd ==  "S"){
              $entityType = substr($upperUEN, 3,2);
              $digits = substr($upperUEN, 5,4);
              $letter = substr($upperUEN, -1);


              if (!extidvald_checkEntityType($entityType)){
                $errors['external_identifier'] = ts( 'Entities UEN: Unknown entity type' );
                return;
              }

              if (!extidvald_validIntString($digits)){
                $errors['external_identifier'] = ts( 'Entities UEN: 4 numbers should follow entity type' );
                return;
              }

              if (!extidvald_validLetter($letter)){
                $errors['external_identifier'] = ts( 'Entities UEN: Last char should be a letter' );
                return;
              }
            } else {
                $errors['external_identifier'] = ts( 'Entities UEN: Year must start with T,R,S' );
            }
          }
        } else {
          $errors['external_identifier'] = ts( 'UEN must be 9 or 10 characters long.' );
        }
      } else {
      }
    } else {
      //ot'er contact types?
    }
}
}
function extidvald_calculateChecksum($id, $prefix) : int {
  $checksumArr = [2, 7, 6, 5, 4, 3, 2];
  $digitsonly = substr($id, 1,7);
  $totalsum = 0;
  //times digits in id by respective weights
  for ($i = 0; $i < 7 ; $i++){
    $totalsum += $digitsonly[$i] * $checksumArr[$i];
  }

  if ($prefix == "T" || $prefix == "G") {
    $totalsum += 4; // No change for "S" prefix
  } else if ($prefix == "M") {
    $totalsum += 3; // Add 3 for "M" prefix
  } else {
    $totalsum += 0; // Add 4 for other prefixes
  }
  
  //divide sum by 11 and get remainer
  $totalsum = $totalsum % 11;

  //plus sum by 1 and minus by 11
  $totalsum = 11 - ($totalsum + 1);

  //returns suffix index
  return $totalsum;
}

function extidvald_validIntString($digits) : bool {
  return is_numeric($digits) ;
}
function extidvald_validLetter($letter) : bool {
  return (!is_numeric($letter));
}
function extidvald_checkEntityType($entity) : bool{
  $knownTypes = ['LP', 'LL', 'FC', 'PF', 'RF', 'MQ', 'MM', 'NB', 'CC', 'CS', 'MB', 'FM', 'GS', 'DP', 'CP', 'NR', 'CM', 'CD', 'MD', 'HS', 'VH', 'CH', 'MH', 'CL', 'XL', 'CX', 'HC', 'RP', 'TU', 'TC', 'FB', 'FN', 'PA', 'PB', 'SS', 'MC', 'SM', 'GA', 'GB'];
  for ($i = 0; $i < count($knownTypes); $i++){
    if ($entity == $knownTypes[$i]){
      return true;
    }
  }
  return false;
}