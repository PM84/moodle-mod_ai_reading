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
 * Upgrade script for mod_aireading
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade function for mod_aireading
 *
 * @param int $oldversion The old version of the module
 * @return bool True on success
 */
function xmldb_aireading_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025111101) {
        // Define fields to be added to aireading table.
        $table = new xmldb_table('aireading');

        // Add readingtext field.
        $field = new xmldb_field('readingtext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add readingtextformat field.
        $field = new xmldb_field('readingtextformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'readingtext');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add targetwpm field.
        $field = new xmldb_field('targetwpm', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100', 'readingtextformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add maxattempts field.
        $field = new xmldb_field('maxattempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '3', 'targetwpm');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add grademethod field.
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'maxattempts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add language field.
        $field = new xmldb_field('language', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'de', 'grademethod');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add readingdifficulty field.
        $field = new xmldb_field('readingdifficulty', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1', 'language');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add silencethreshold field.
        $field = new xmldb_field(
            'silencethreshold',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '10',
            'readingdifficulty'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add enablepronunciation field.
        $field = new xmldb_field(
            'enablepronunciation',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'silencethreshold'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add minconfidence field.
        $field = new xmldb_field(
            'minconfidence',
            XMLDB_TYPE_NUMBER,
            '3, 2',
            null,
            XMLDB_NOTNULL,
            null,
            '0.80',
            'enablepronunciation'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add analysisversion field.
        $field = new xmldb_field('analysisversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'minconfidence');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add timecreated field.
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'analysisversion');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table aireading_attempts to be created.
        $table = new xmldb_table('aireading_attempts');

        // Adding fields to table aireading_attempts.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('aireading_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('audiofileid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeanalyzed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('wordcount_original', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('wordcount_transcribed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('transcription', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('analysis_data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('wpm', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('accuracy_score', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
        $table->add_field('fluency_score', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
        $table->add_field('pronunciation_score', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null);
        $table->add_field('analysisversion', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('teacherfeedback', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table aireading_attempts.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('aireading_id', XMLDB_KEY_FOREIGN, ['aireading_id'], 'aireading', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table aireading_attempts.
        $table->add_index('aireading_id', XMLDB_INDEX_NOTUNIQUE, ['aireading_id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('timeanalyzed', XMLDB_INDEX_NOTUNIQUE, ['timeanalyzed']);
        $table->add_index('aireading_userid_attempt', XMLDB_INDEX_UNIQUE, ['aireading_id', 'userid', 'attempt']);

        // Conditionally launch create table for aireading_attempts.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // aireading savepoint reached.
        upgrade_mod_savepoint(true, 2025111101, 'aireading');
    }

    return true;
}
