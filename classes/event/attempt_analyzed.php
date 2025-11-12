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
 * The attempt_analyzed event
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\event;

/**
 * The attempt_analyzed event class
 *
 * Triggered when a reading attempt has been successfully analyzed
 * by the STT service and results have been stored.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int attemptid: The ID of the attempt
 *      - int userid: The user who made the attempt
 *      - int wordcount: Number of words transcribed
 * }
 */
class attempt_analyzed extends \core\event\base {
    /**
     * Init method
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'aireading_attempts';
    }

    /**
     * Return localised event name
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_attempt_analyzed', 'mod_aireading');
    }

    /**
     * Returns description of what happened
     *
     * @return string
     */
    public function get_description() {
        return "User {$this->relateduserid} had their reading attempt {$this->other['attemptid']} " .
            "analyzed in activity {$this->objectid}. Transcribed {$this->other['wordcount']} words.";
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/mod/aireading/view.php',
            ['id' => $this->contextinstanceid, 'attempt' => $this->other['attemptid']]
        );
    }

    /**
     * Custom validation
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['attemptid'])) {
            throw new \coding_exception('The \'attemptid\' value must be set in other.');
        }

        if (!isset($this->other['wordcount'])) {
            throw new \coding_exception('The \'wordcount\' value must be set in other.');
        }

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
