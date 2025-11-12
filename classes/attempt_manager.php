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
 * Attempt manager for AI Reading module
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Class for managing reading attempts
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attempt_manager {
    /**
     * Create a new attempt for a user
     *
     * @param int $aireadingid The AI reading activity ID
     * @param int $userid The user ID
     * @return \stdClass The created attempt record
     * @throws \moodle_exception If user cannot attempt or if creation fails
     */
    public function create_attempt($aireadingid, $userid) {
        global $DB;

        // Check if user can create another attempt.
        if (!$this->can_user_attempt($aireadingid, $userid)) {
            throw new \moodle_exception('maxattemptsreached', 'mod_aireading');
        }

        // Get the next attempt number.
        $attemptnumber = $this->get_next_attempt_number($aireadingid, $userid);

        // Create attempt record.
        $attempt = new \stdClass();
        $attempt->aireading_id = $aireadingid;
        $attempt->userid = $userid;
        $attempt->attempt = $attemptnumber;
        $attempt->status = 0; // In progress.
        $attempt->timestarted = time();

        $attempt->id = $DB->insert_record('aireading_attempts', $attempt);

        // Trigger attempt created event.
        $event = \mod_aireading\event\attempt_created::create([
            'objectid' => $attempt->id,
            'context' => $this->get_context_from_aireading_id($aireadingid),
            'other' => [
                'attemptid' => $attempt->id,
                'userid' => $userid,
                'attemptnumber' => $attemptnumber,
            ],
        ]);
        $event->trigger();

        return $attempt;
    }

    /**
     * Save audio file to an attempt and finalize it
     *
     * @param int $attemptid The attempt ID
     * @param \stored_file $file The audio file to save
     * @return bool True on success
     * @throws \moodle_exception On failure
     */
    public function save_audio_file($attemptid, $file) {
        global $DB;

        $attempt = $DB->get_record('aireading_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // Update attempt with file reference.
        $attempt->audiofileid = $file->get_id();

        // Finalize the attempt.
        return $this->finalize_attempt($attemptid);
    }

    /**
     * Finalize an attempt (mark as submitted)
     *
     * @param int $attemptid The attempt ID
     * @return bool True on success
     * @throws \moodle_exception On failure
     */
    public function finalize_attempt($attemptid) {
        global $DB;

        $attempt = $DB->get_record('aireading_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // Calculate duration if not set.
        if (empty($attempt->timefinished)) {
            $attempt->timefinished = time();
        }

        // Set status to submitted.
        $attempt->status = 1;

        $success = $DB->update_record('aireading_attempts', $attempt);

        if ($success) {
            // Trigger attempt submitted event.
            $event = \mod_aireading\event\attempt_submitted::create([
                'objectid' => $attempt->id,
                'context' => $this->get_context_from_attempt($attempt),
                'other' => [
                    'attemptid' => $attempt->id,
                    'userid' => $attempt->userid,
                    'duration' => $attempt->timefinished - $attempt->timestarted,
                ],
            ]);
            $event->trigger();
        }

        return $success;
    }

    /**
     * Mark an attempt as error
     *
     * This is an administrative function that requires grade capability.
     *
     * @param int $attemptid The attempt ID
     * @param string $code Error code
     * @param string $message Error message
     * @return bool True on success
     * @throws \moodle_exception If user lacks required capability
     */
    public function mark_attempt_error($attemptid, $code, $message) {
        global $DB;

        $attempt = $DB->get_record('aireading_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // Require grade capability for administrative error marking.
        // Note: This is called from automated processes (webservice, tasks) where
        // capability is already checked at controller level, but we enforce it here
        // for safety in case method is called from other contexts.
        $context = $this->get_context_from_attempt($attempt);
        if (!has_capability('mod/aireading:grade', $context, null, false)) {
            // If called from system context (e.g., cron task), allow it.
            $syscontext = \context_system::instance();
            if (!has_capability('moodle/site:config', $syscontext, null, false)) {
                throw new \moodle_exception('nopermissions', 'error', '', 'mod/aireading:grade');
            }
        }

        $attempt->status = 3; // Error.
        $success = $DB->update_record('aireading_attempts', $attempt);

        if ($success) {
            // Trigger attempt failed event.
            $event = \mod_aireading\event\attempt_failed::create([
                'objectid' => $attempt->id,
                'context' => $this->get_context_from_attempt($attempt),
                'other' => [
                    'attemptid' => $attempt->id,
                    'userid' => $attempt->userid,
                    'errorcode' => $code,
                    'errormessage' => $message,
                ],
            ]);
            $event->trigger();
        }

        return $success;
    }

    /**
     * Queue attempt for reanalysis with new version
     *
     * @param int $attemptid The attempt ID
     * @param int $newversion New analysis version
     * @return bool True on success
     */
    public function requeue_for_reanalysis($attemptid, $newversion) {
        global $DB;

        $attempt = $DB->get_record('aireading_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        // Reset to submitted status.
        $attempt->status = 1;
        $attempt->analysisversion = $newversion;

        return $DB->update_record('aireading_attempts', $attempt);
    }

    /**
     * Get all attempts for a user on a specific activity
     *
     * @param int $aireadingid The AI reading activity ID
     * @param int $userid The user ID
     * @return array Array of attempt records
     */
    public function get_user_attempts($aireadingid, $userid) {
        global $DB;

        return $DB->get_records('aireading_attempts', [
            'aireading_id' => $aireadingid,
            'userid' => $userid,
        ], 'attempt ASC');
    }

    /**
     * Check if user can create another attempt
     *
     * @param int $aireadingid The AI reading activity ID
     * @param int $userid The user ID
     * @return bool True if user can attempt, false otherwise
     */
    public function can_user_attempt($aireadingid, $userid) {
        global $DB;

        $aireadingrecord = $DB->get_record('aireading', ['id' => $aireadingid], '*', MUST_EXIST);

        // If maxattempts is 0, unlimited attempts allowed.
        if ($aireadingrecord->maxattempts == 0) {
            return true;
        }

        // Count user's attempts.
        $attemptcount = $DB->count_records('aireading_attempts', [
            'aireading_id' => $aireadingid,
            'userid' => $userid,
        ]);

        return $attemptcount < $aireadingrecord->maxattempts;
    }

    /**
     * Get the next attempt number for a user
     *
     * @param int $aireadingid The AI reading activity ID
     * @param int $userid The user ID
     * @return int The next attempt number
     */
    protected function get_next_attempt_number($aireadingid, $userid) {
        global $DB;

        $sql = "SELECT COALESCE(MAX(attempt), 0) + 1 AS nextattempt
                  FROM {aireading_attempts}
                 WHERE aireading_id = :aireadingid AND userid = :userid";

        $result = $DB->get_record_sql($sql, [
            'aireadingid' => $aireadingid,
            'userid' => $userid,
        ]);

        return (int)$result->nextattempt;
    }

    /**
     * Get context from AI reading ID
     *
     * @param int $aireadingid The AI reading activity ID
     * @return \context_module The module context
     */
    protected function get_context_from_aireading_id($aireadingid) {
        $cm = get_coursemodule_from_instance('aireading', $aireadingid, 0, false, MUST_EXIST);
        return \context_module::instance($cm->id);
    }

    /**
     * Get context from attempt record
     *
     * @param \stdClass $attempt The attempt record
     * @return \context_module The module context
     */
    protected function get_context_from_attempt($attempt) {
        return $this->get_context_from_aireading_id($attempt->aireading_id);
    }
}
