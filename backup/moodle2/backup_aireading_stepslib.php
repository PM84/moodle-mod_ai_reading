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
 * Provides all the settings and steps to perform one complete backup of the activity
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_aireading_activity_structure_step extends backup_activity_structure_step {
    /**
     * Backup structure
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // TODO MDL-XXXXX: add all additional fields from the aireading table.
        $aireading = new backup_nested_element(
            'aireading',
            ['id'],
            ['name', 'intro', 'introformat', 'timemodified']
        );

        // Define sources.
        $aireading->set_source_table('aireading', ['id' => backup::VAR_ACTIVITYID]);

        // Define id annotations.
        // TODO MDL-XXXXX: add all additional id annotations.

        // Define file annotations.
        // TODO MDL-XXXXX: add all additional file annotations.
        $aireading->annotate_files('mod_aireading', 'intro', null);

        // Return the root element (aireading), wrapped into standard activity structure.
        return $this->prepare_activity_structure($aireading);
    }
}
