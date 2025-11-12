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
 * Renderer for mod_aireading
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\output;

use plugin_renderer_base;
use renderable;
use stdClass;

/**
 * Renderer class for AI Reading module
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the main reading view for students
     *
     * @param stdClass $moduleinstance The module instance
     * @param stdClass $cm The course module
     * @param stdClass $context The context
     * @param int $userid The user ID
     * @return string HTML output
     */
    public function render_reading_view($moduleinstance, $cm, $context, $userid) {
        global $DB;

        $attemptmanager = new \mod_aireading\attempt_manager();

        // Get user's attempts.
        $attempts = $attemptmanager->get_user_attempts($moduleinstance->id, $userid);

        // Check if user can make new attempts.
        $canattempt = $attemptmanager->can_user_attempt($moduleinstance->id, $userid);

        // Prepare attempts data for template.
        $attemptsdata = [];
        foreach ($attempts as $attempt) {
            $attemptsdata[] = $this->prepare_attempt_data($attempt, $cm);
        }

        // Prepare template data.
        $data = [
            'readingtext' => format_text(
                $moduleinstance->readingtext,
                $moduleinstance->readingtextformat,
                ['context' => $context]
            ),
            'canattempt' => $canattempt,
            'maxattempts' => $moduleinstance->maxattempts == 0 ?
                get_string('unlimited', 'mod_aireading') : $moduleinstance->maxattempts,
            'attemptsused' => count($attempts),
            'hasattempts' => !empty($attempts),
            'attempts' => $attemptsdata,
            'cmid' => $cm->id,
            'aireadingid' => $moduleinstance->id,
            'silencethreshold' => $moduleinstance->silencethreshold ?? 10,
            'maxduration' => 600, // 10 minutes.
        ];

        return $this->render_from_template('mod_aireading/reading_view', $data);
    }

    /**
     * Prepare attempt data for template
     *
     * @param stdClass $attempt The attempt record
     * @param stdClass $cm The course module
     * @return array Prepared data
     */
    private function prepare_attempt_data($attempt, $cm) {
        $data = [
            'attemptnum' => $attempt->attempt,
            'timestarted' => userdate($attempt->timestarted, get_string('strftimedatetime', 'langconfig')),
            'timefinished' => $attempt->timefinished ?
                userdate($attempt->timefinished, get_string('strftimedatetime', 'langconfig')) : '-',
            'status' => $attempt->status,
            'isanalyzed' => $attempt->status == 2,
            'iserror' => $attempt->status == 3,
        ];

        // Status text and class.
        switch ($attempt->status) {
            case 0:
                $data['statustext'] = get_string('inprogress', 'mod_aireading');
                $data['statusclass'] = 'secondary';
                break;
            case 1:
                $data['statustext'] = get_string('submitted', 'mod_aireading');
                $data['statusclass'] = 'info';
                break;
            case 2:
                $data['statustext'] = get_string('analyzed', 'mod_aireading');
                $data['statusclass'] = 'success';
                break;
            case 3:
                $data['statustext'] = get_string('error', 'core');
                $data['statusclass'] = 'danger';
                break;
        }

        // Add analysis data if available.
        if ($attempt->status == 2) {
            $data['grade'] = round($attempt->grade, 1);
            $data['wpm'] = round($attempt->wpm, 1);
            $data['accuracy'] = round($attempt->accuracy_score, 1);
            $data['fluency'] = round($attempt->fluency_score, 1);

            if ($attempt->pronunciation_score !== null) {
                $data['haspronunciation'] = true;
                $data['pronunciation'] = round($attempt->pronunciation_score, 1);
            }

            $data['viewurl'] = new \moodle_url('/mod/aireading/attempt.php', [
                'id' => $cm->id,
                'attemptid' => $attempt->id,
            ]);
        }

        return $data;
    }

    /**
     * Render recorder interface
     *
     * @param int $cmid Course module ID
     * @param int $aireadingid AI Reading instance ID
     * @param int $silencethreshold Silence threshold in seconds
     * @return string HTML output
     */
    public function render_recorder_interface($cmid, $aireadingid, $silencethreshold = 10) {
        $data = [
            'cmid' => $cmid,
            'aireadingid' => $aireadingid,
            'silencethreshold' => $silencethreshold,
            'maxduration' => 600, // 10 minutes.
        ];

        return $this->render_from_template('mod_aireading/recorder', $data);
    }

    /**
     * Render attempt row
     *
     * @param stdClass $attempt The attempt record
     * @param stdClass $cm The course module
     * @return string HTML output
     */
    public function render_attempt_row($attempt, $cm) {
        $data = $this->prepare_attempt_data($attempt, $cm);
        return $this->render_from_template('mod_aireading/attempt_row', $data);
    }
}
