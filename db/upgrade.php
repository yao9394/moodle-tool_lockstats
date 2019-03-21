<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * DB upgrades for tool_lockstats.
 *
 * @package    tool_lockstats
 * @author     Trisha Milan <trishamilan@catalyst-au.net>
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_lockstats_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2019011600) {
        $lockstable = new xmldb_table('tool_lockstats_locks');
        $historytable = new xmldb_table('tool_lockstats_history');

        $field = new xmldb_field('task');
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        // Update task column to use CHAR instead of TEXT.
        $dbman->change_field_type($lockstable, $field, $continue = true, $feedback = true);
        $dbman->change_field_type($historytable, $field, $continue = true, $feedback = true);

        $index = new xmldb_index('task', XMLDB_INDEX_NOTUNIQUE, array('task'));
        // Conditionally launch add index.
        if (!$dbman->index_exists($lockstable, $index)) {
            $dbman->add_index($lockstable, $index);
        }
        upgrade_plugin_savepoint(true, 2019011600, 'tool', 'lockstats');
    }

    if ($oldversion < 2019030700) {
        $lockstable = new xmldb_table('tool_lockstats_locks');
        $historytable = new xmldb_table('tool_lockstats_history');

        $taskfield = new xmldb_field('task', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'id');
        $index = new xmldb_index('task', XMLDB_INDEX_NOTUNIQUE, array('task'));
        $newindex = new xmldb_index('resourcekey', XMLDB_INDEX_NOTUNIQUE, array('resourcekey'));
        $compoentfield = new xmldb_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'resourcekey');
        $classnamefield = new xmldb_field('classname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'component');
        $customdatafield = new xmldb_field('customdata', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'pid');
        $historytaskfield = new xmldb_field('task', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'taskid');
        $historycomponentfield = new xmldb_field('component', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'taskid');

        // Launch rename index task to resourcekey.
        $dbman->drop_index($lockstable, $index, true, true);
        // Launch rename field task.
        $dbman->rename_field($lockstable, $taskfield, 'resourcekey');
        // Conditionally launch add field component.
        if (!$dbman->field_exists($lockstable, $compoentfield)) {
            $dbman->add_field($lockstable, $compoentfield);
        }
        $dbman->add_index($lockstable, $newindex, true, true);
        // Conditionally launch add field classname.
        if (!$dbman->field_exists($lockstable, $classnamefield)) {
            $dbman->add_field($lockstable, $classnamefield);
        }
        // Conditionally launch add field customdata.
        if (!$dbman->field_exists($lockstable, $customdatafield)) {
            $dbman->add_field($lockstable, $customdatafield);
        }
        // Launch rename field task.
        $dbman->rename_field($historytable, $historytaskfield, 'classname');
        // Conditionally launch add field component.
        if (!$dbman->field_exists($historytable, $historycomponentfield)) {
            $dbman->add_field($historytable, $historycomponentfield);
        }

        // Keep other task names but not adhoc tasks since old data in the field is not adhoc task name.
        $DB->execute('UPDATE {tool_lockstats_locks} SET classname = resourcekey WHERE ' . $DB->sql_like('resourcekey', ':resourcek'), array('resourcek' => 'adhoc_%') );
        // Fill in components for scheduled tasks.
        $updatelockssql = 'UPDATE {tool_lockstats_locks} SET component = ts.component FROM  {task_scheduled} ts WHERE ts.classname = {tool_lockstats_locks}.resourcekey';
        $DB->execute($updatelockssql);
        $updatehistorysql = 'UPDATE {tool_lockstats_history} SET component = ts.component FROM  {task_scheduled} ts WHERE ts.classname = {tool_lockstats_history}.classname';
        $DB->execute($updatehistorysql);

        upgrade_plugin_savepoint(true, 2019030700, 'tool', 'lockstats');
    }

    if ($oldversion < 2019030702) {
        $table = new xmldb_table('tool_lockstats_locks');
        $field = new xmldb_field('latency', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'customdata');
        $dbman->add_field($table, $field);

        $table = new xmldb_table('tool_lockstats_history');
        $field = new xmldb_field('latency', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'customdata');
        $dbman->add_field($table, $field);

        upgrade_plugin_savepoint(true, 2019030702, 'tool', 'pid');
    }

    if ($oldversion < 2019030703) {
        $table = new xmldb_table('tool_lockstats_history');
        $field = new xmldb_field('latency');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        // Update latency column to use INT instead of CHAR.
        $dbman->change_field_type($table, $field, $continue = true, $feedback = true);

        upgrade_plugin_savepoint(true, 2019030703, 'tool', 'pid');
    }

    if ($oldversion < 2019030706) {

        $table = new xmldb_table('tool_lockstats_history');
        $field = new xmldb_field('type');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $dbman->add_field($table, $field);

        $sql = "DELETE FROM {tool_lockstats_locks}
                      WHERE " . $DB->sql_like('resourcekey', ':resourcek');

        $params = [
            'resourcek' => 'adhoc_%'
        ];

        // Delete adhoc records from locks table. They should only exist in history once processed.
        $DB->execute($sql, $params);

        upgrade_plugin_savepoint(true, 2019030706, 'tool', 'pid');
    }

    return true;
}