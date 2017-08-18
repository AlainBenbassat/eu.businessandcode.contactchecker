<?php

require_once 'contactchecker.civix.php';


function contactchecker_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  $returnMessage = '';

  if ($objectName == 'Individual' && ($op == 'create' || $op == 'edit')) {
    $returnMessage = _contactchecker_add_work_address($objectId);
    $returnMessage .= _contactchecker_autofill_fields($objectId);
  }

  if ($returnMessage && $op = 'edit') {
    $returnMessage .= '<br>Ververs het scherm om de wijzigen te zien.';
    CRM_Core_Session::setStatus($returnMessage, 'Kunstenpunt', 'info');
  }
}

function _contactchecker_add_work_address($contactID) {
  $returnMessage = '';

  // get the address id of the employer,
  // and the work address of this contact
  $sql = "
    SELECT
      employer_addr.id employer_address_id
      , contact_addr.id contact_work_address_id
      , contact_addr.master_id contact_work_address_master_id
    FROM
      civicrm_contact c
    INNER JOIN
      civicrm_address employer_addr ON employer_addr.`contact_id` = c.`employer_id` AND employer_addr.`is_primary` = 1
    LEFT OUTER JOIN
      civicrm_address contact_addr ON contact_addr.`contact_id` = c.id AND contact_addr.`location_type_id` = 2
    WHERE
      c.id = %1
  ";
  $sqlParams = array(
    1 => array($contactID, 'Integer'),
  );
  $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

  if ($dao->fetch()) {
    // OK, this contact has an employer, and this employer has an address
    // check if we need to add a work address
    if ($dao->contact_work_address_id && $dao->contact_work_address_master_id == $dao->employer_address_id) {
      // DO NOTHING: this contact has a work address, and it is linked to the address of the employer
    }
    else {
      // check if the contact has a work address
      if ($dao->contact_work_address_id) {
        // remove this address
        $params = array(
          'id' => $dao->contact_work_address_id,
        );
        $a = civicrm_api3('Address', 'delete', $params);
      }

      // get the address of the employer
      $params = array(
        'id' => $dao->employer_address_id,
      );
      $a = civicrm_api3('Address', 'getsingle', $params);

      // make this address the work address of the contact
      $a['master_id'] = $a['id'];
      $a['contact_id'] = $contactID;
      $a['location_type_id'] = 2;
      unset($a['id']);
      $workAddress = civicrm_api3('Address', 'create', $a);
      $returnMessage .= 'Het adres van de organisatie (werkgever) werd automatisch toegevoegd.<br>';
    }
  }
  else {
    // no employer, or the employer has no address
    // remove the work address of this contact (if any)
    $params = array(
      'location_type_id' => 2,
      'contact_id' => $contactID,
    );

    try {
      $a = civicrm_api3('Address', 'getsingle', $params);
      $params = array('id' => $a['id']);
      civicrm_api3('Address', 'delete', $params);
      $returnMessage .= 'Dit contact is niet gekoppeld aan een organisatie (werkgever). Het werkadres werd bijgevolg verwijderd.<br>';
    }
    catch (Exception $e) {
    }
  }

  return $returnMessage;
}

function _contactchecker_autofill_fields($contactID) {
  $returnMessage = '';

  $sql = "
    SELECT
      c.prefix_id
      , c.gender_id
      , v.description
      , c.is_deceased
    FROM
      civicrm_contact c
    LEFT OUTER JOIN 
      civicrm_option_value v ON v.value = c.`prefix_id` AND v.option_group_id = 6
    WHERE
      c.id = %1
  ";
  $sqlParams = array(
    1 => array($contactID, 'Integer'),
  );
  $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

  if ($dao->fetch()) {
    // Check the prefix (Ms., Mr. ...) and set the gender if needed.
    // The description of the prefix, should contain M or V
    if ($dao->description) {
      // remove the html tags from the description
      $gender = str_replace('<p>', '', $dao->description);
      $gender = str_replace('</p>', '', $gender);

      if ($gender == 'V' && $dao->gender_id != 1) {
        $sql = 'update civicrm_contact set gender_id = 1 where id = %1';
        CRM_Core_DAO::executeQuery($sql, $sqlParams);
        $returnMessage .= 'Het geslacht werd op vrouwelijk gezet.<br>';
      }
      else if ($gender == 'M' && $dao->gender_id != 2) {
        $sql = 'update civicrm_contact set gender_id = 2 where id = %1';
        CRM_Core_DAO::executeQuery($sql, $sqlParams);
        $returnMessage .= 'Het geslacht werd op mannelijk gezet.<br>';
      }
    }

    // check if the person is deceased
    if ($dao->is_deceased) {
      // delete the email address(es) - if any
      $sql = 'delete from civicrm_email where contact_id = %1';
      CRM_Core_DAO::executeQuery($sql, $sqlParams);
      $returnMessage .= 'De e-mailadressen van dit overleden contact werden verwijderd.<br>';
    }
  }

  return $returnMessage;
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contactchecker_civicrm_config(&$config) {
  _contactchecker_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function contactchecker_civicrm_xmlMenu(&$files) {
  _contactchecker_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contactchecker_civicrm_install() {
  _contactchecker_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contactchecker_civicrm_uninstall() {
  _contactchecker_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contactchecker_civicrm_enable() {
  _contactchecker_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contactchecker_civicrm_disable() {
  _contactchecker_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contactchecker_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contactchecker_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function contactchecker_civicrm_managed(&$entities) {
  _contactchecker_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function contactchecker_civicrm_caseTypes(&$caseTypes) {
  _contactchecker_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function contactchecker_civicrm_angularModules(&$angularModules) {
_contactchecker_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function contactchecker_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _contactchecker_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Functions below this ship commented out. Uncomment as required.
 *

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function contactchecker_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function contactchecker_civicrm_navigationMenu(&$menu) {
  _contactchecker_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'eu.businessandcode.contactchecker')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _contactchecker_civix_navigationMenu($menu);
} // */
