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
class CRM_Utils_Check_Component_Schema extends CRM_Utils_Check_Component {

  /**
   * @return array
   */
  public function checkIndices() {
    $messages = [];

    // CRM-21298: The "Update Indices" tool that this check suggests is
    // unreliable. Bypass this check until CRM-20817 and CRM-20533 are resolved.
    return $messages;

    $missingIndices = CRM_Core_BAO_SchemaHandler::getMissingIndices();
    if ($missingIndices) {
      $html = '';
      foreach ($missingIndices as $tableName => $indices) {
        foreach ($indices as $index) {
          $fields = implode(', ', $index['field']);
          $html .= "<tr><td>{$tableName}</td><td>{$index['name']}</td><td>$fields</td>";
        }
      }
      $message = "<p>The following tables have missing indices. Click 'Update Indices' button to create them.<p>
        <p><table><thead><tr><th>Table Name</th><th>Key Name</th><th>Expected Indices</th>
        </tr></thead><tbody>
        $html
        </tbody></table></p>";
      $msg = new CRM_Utils_Check_Message(
        __FUNCTION__,
        ts($message),
        ts('Performance warning: Missing indices'),
        \Psr\Log\LogLevel::WARNING,
        'fa-server'
      );
      $msg->addAction(
        ts('Update Indices'),
        ts('Update all database indices now? This may take a few minutes and cause a noticeable performance lag for all users while running.'),
        'api3',
        ['System', 'updateindexes']
      );
      $messages[] = $msg;
    }
    return $messages;
  }

  /**
   * @return array
   */
  public function checkMissingLogTables() {
    $messages = [];
    $logging = new CRM_Logging_Schema();
    $missingLogTables = $logging->getMissingLogTables();

    if (Civi::settings()->get('logging') && $missingLogTables) {
      $msg = new CRM_Utils_Check_Message(
        __FUNCTION__,
        ts("You don't have logging enabled on some tables. This may cause errors on performing insert/update operation on them."),
        ts('Missing Log Tables'),
        \Psr\Log\LogLevel::WARNING,
        'fa-server'
      );
      $msg->addAction(
        ts('Create Missing Log Tables'),
        ts('Create missing log tables now? This may take few minutes.'),
        'api3',
        ['System', 'createmissinglogtables']
      );
      $messages[] = $msg;
    }
    return $messages;
  }

  /**
   * Check whether any log tables use the ARCHIVE storage engine and offer conversion
   *
   * @return array
   */
  public function checkLogTableEngine() {
    $messages = [];
    $text = '<p>Some of your log tables use the MySQL ARCHIVE storage engine.
                  The ARCHIVE engine has known durability and performance issues.
                  Converting log tables to InnoDB will improve the reliability
                  of log tables and speed up the contact change log report.</p>
               <p><strong>Conversion may take several minutes to several hours for large sites.</strong>
                  During this process, your site will be partially unavailable
                  and should be put into maintenance mode. By default, and
                  unlike the ARCHIVE engine, InnoDB does not use compression, so
                  <strong>table size will increase</strong>.
                  It is highly recommended to test the conversion on a separate
                  system with a full copy of your database.</p>
                  <p>Please refer to <a href="TODO" target="_blank">TODO</a> for more details.</p>';
    $msg = new CRM_Utils_Check_Message(
      __FUNCTION__,
      ts($text),
      ts('Convert log tables'),
      \Psr\Log\LogLevel::WARNING,
      'fa-server'
    );
    $msg->addAction(
      ts('Convert log tables'),
      ts('Convert log tables to InnoDB? This may take several minutes to several hours for large sites and will increase the disk usage of your MySQL server.'),
      'api3',
      ['System', 'updatelogtables', ['forceEngineMigration' => TRUE]]
    );
    if (Civi::settings()->get('logging')) {
      $logging = new CRM_Logging_Schema();
      foreach ($logging->getLogTableSpec() as $logTable => $spec) {
        $currentEngine = strtoupper($logging->getEngineForLogTable('log_' . $logTable));
        // only act if $spec['engine'] is not set, i.e. ARCHIVE was not
        // explicitly requested by an extension via hook_civicrm_alterLogTables
        if (empty($spec['engine']) && $currentEngine == 'ARCHIVE') {
          $messages[] = $msg;
          // stop once we encounter the first ARCHIVE table
          break;
        }
      }

    }
    return $messages;
  }

}
