<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 5                                                  |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2019                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2019
 */

/**
 * Form helper class for an Email object.
 */
class CRM_Contact_Form_Edit_Email {

  /**
   * Build the form object elements for an email object.
   *
   * @param CRM_Core_Form $form
   *   Reference to the form object.
   * @param int $blockCount
   *   Block number to build.
   * @param bool $blockEdit
   *   Is it block edit.
   */
  public static function buildQuickForm(&$form, $blockCount = NULL, $blockEdit = FALSE) {
    // passing this via the session is AWFUL. we need to fix this
    if (!$blockCount) {
      $blockId = ($form->get('Email_Block_Count')) ? $form->get('Email_Block_Count') : 1;
    }
    else {
      $blockId = $blockCount;
    }

    $form->applyFilter('__ALL__', 'trim');

    //Email box
    $form->addField("email[$blockId][email]", ['entity' => 'email', 'aria-label' => ts('Email %1', [1 => $blockId])]);
    $form->addRule("email[$blockId][email]", ts('Email is not valid.'), 'email');
    if (isset($form->_contactType) || $blockEdit) {
      //Block type
      $form->addField("email[$blockId][location_type_id]", ['entity' => 'email', 'placeholder' => NULL, 'class' => 'eight', 'option_url' => NULL]);

      //TODO: Refactor on_hold field to select.
      $multipleBulk = CRM_Core_BAO_Email::isMultipleBulkMail();

      //On-hold select
      if ($multipleBulk) {
        $holdOptions = [
          0 => ts('- select -'),
          1 => ts('On Hold Bounce'),
          2 => ts('On Hold Opt Out'),
        ];
        $form->addElement('select', "email[$blockId][on_hold]", '', $holdOptions);
      }
      else {
        $form->addField("email[$blockId][on_hold]", ['entity' => 'email', 'type' => 'advcheckbox', 'aria-label' => ts('On Hold for Email %1?', [1 => $blockId])]);
      }

      //Bulkmail checkbox
      $form->assign('multipleBulk', $multipleBulk);
      $js = ['id' => "Email_" . $blockId . "_IsBulkmail" , 'aria-label' => ts('Bulk Mailing for Email %1?', [1 => $blockId])];
      if (!$blockEdit) {
        $js['onClick'] = 'singleSelect( this.id );';
      }
      $form->addElement('advcheckbox', "email[$blockId][is_bulkmail]", NULL, '', $js);

      //is_Primary radio
      $js = ['id' => "Email_" . $blockId . "_IsPrimary", 'aria-label' => ts('Email %1 is primary?', [1 => $blockId])];
      if (!$blockEdit) {
        $js['onClick'] = 'singleSelect( this.id );';
      }

      $form->addElement('radio', "email[$blockId][is_primary]", '', '', '1', $js);

      if (CRM_Utils_System::getClassName($form) == 'CRM_Contact_Form_Contact') {

        $form->add('textarea', "email[$blockId][signature_text]", ts('Signature (Text)'),
          ['rows' => 2, 'cols' => 40]
        );

        $form->add('wysiwyg', "email[$blockId][signature_html]", ts('Signature (HTML)'),
          ['rows' => 2, 'cols' => 40]
        );
      }
    }

    self::addCustomDataToForm($form, 211, $blockId);
  }

  /**
   * Add custom data to the form.
   *
   * @param CRM_Core_Form $form
   * @param int $entityId
   * @param int $blockId
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  protected static function addCustomDataToForm(&$form, $entityId, $blockId) {
    $groupTree = CRM_Core_BAO_CustomGroup::getTree('Email', NULL, $entityId);

    if (isset($groupTree) && is_array($groupTree)) {
      // use simplified formatted groupTree
      $groupTree = CRM_Core_BAO_CustomGroup::formatGroupTree($groupTree, 1, $form);

      // make sure custom fields are added /w element-name in the format - 'address[$blockId][custom-X]'
      foreach ($groupTree as $id => $group) {
        foreach ($group['fields'] as $fldId => $field) {
          $groupTree[$id]['fields'][$fldId]['element_custom_name'] = $field['element_name'];
          $groupTree[$id]['fields'][$fldId]['element_name'] = "address[$blockId][{$field['element_name']}]";
        }
      }

      $defaults = [];
      CRM_Core_BAO_CustomGroup::setDefaults($groupTree, $defaults);

      // since we change element name for address custom data, we need to format the setdefault values
      $emailDefaults = [];
      foreach ($defaults as $key => $val) {
        if (!isset($val)) {
          continue;
        }

        // inorder to set correct defaults for checkbox custom data, we need to converted flat key to array
        // this works for all types custom data
        $keyValues = explode('[', str_replace(']', '', $key));
        $emailDefaults[$keyValues[0]][$keyValues[1]][$keyValues[2]] = $val;
      }

      $form->setDefaults($emailDefaults);

      // we setting the prefix to 'dnc_' below, so that we don't overwrite smarty's grouptree var.
      // And we can't set it to 'address_' because we want to set it in a slightly different format.
      CRM_Core_BAO_CustomGroup::buildQuickForm($form, $groupTree, FALSE, 'dnc_');

      // during contact editing : if no address is filled
      // required custom data must not produce 'required' form rule error
      // more handling done in formRule func
      CRM_Contact_Form_Edit_Address::storeRequiredCustomDataInfo($form, $groupTree);

      $tplGroupTree = CRM_Core_Smarty::singleton()
        ->get_template_vars('email_groupTree');
      $tplGroupTree = empty($tplGroupTree) ? [] : $tplGroupTree;

      $form->assign('email_groupTree', $tplGroupTree + [$blockId => $groupTree]);
      // unset the temp smarty var that got created
      $form->assign('dnc_groupTree', NULL);
    }
    // address custom data processing ends ..
  }

}
