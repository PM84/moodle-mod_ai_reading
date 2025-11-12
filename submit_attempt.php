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
 * AJAX endpoint for audio upload
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Security: require login.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

try {
    // Get parameters.
    $aireadingid = required_param('aireadingid', PARAM_INT);
    $duration = optional_param('duration', 0, PARAM_INT);

    // Get module instance and validate.
    $moduleinstance = $DB->get_record('aireading', ['id' => $aireadingid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('aireading', $moduleinstance->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Security: Check capability.
    require_capability('mod/aireading:submit', $context);

    // Check if user can make more attempts.
    $attemptmanager = new \mod_aireading\attempt_manager();
    if (!$attemptmanager->can_user_attempt($aireadingid, $USER->id)) {
        throw new moodle_exception('maxattemptsreached', 'mod_aireading');
    }

    // Get uploaded file.
    if (empty($_FILES['audiofile'])) {
        throw new moodle_exception('nofile', 'error');
    }

    $file = $_FILES['audiofile'];

    // Validate file size (max 50MB).
    $maxsize = 50 * 1024 * 1024; // 50MB.
    if ($file['size'] > $maxsize) {
        throw new moodle_exception('audiofiletoolarge', 'mod_aireading');
    }

    // Validate duration (min 2 seconds).
    if ($duration < 2) {
        throw new moodle_exception('audiofiletoshort', 'mod_aireading');
    }

    // Validate file format.
    $allowedtypes = ['audio/webm', 'audio/ogg', 'audio/mp3', 'audio/mpeg', 'audio/wav'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimetype = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimetype, $allowedtypes)) {
        throw new moodle_exception('invalidaudioformat', 'mod_aireading');
    }

    // Create new attempt.
    $attempt = $attemptmanager->create_attempt($aireadingid, $USER->id);

    // Save audio file.
    $fs = get_file_storage();
    $fileinfo = [
        'contextid' => $context->id,
        'component' => 'mod_aireading',
        'filearea' => 'attemptaudio',
        'itemid' => $attempt->id,
        'filepath' => '/',
        'filename' => 'recording_' . $attempt->id . '.webm',
    ];

    // Delete any existing file (should not happen, but safety first).
    $fs->delete_area_files($context->id, 'mod_aireading', 'attemptaudio', $attempt->id);

    // Create file from uploaded data.
    $storedfile = $fs->create_file_from_pathname($fileinfo, $file['tmp_name']);

    if (!$storedfile) {
        // Rollback: Delete attempt if file creation failed.
        $DB->delete_records('aireading_attempts', ['id' => $attempt->id]);
        throw new moodle_exception('filecreationerror', 'error');
    }

    // Update attempt with file reference and finalize.
    $attempt->audiofileid = $storedfile->get_id();
    $attempt->timefinished = time();
    $attempt->status = 1; // Submitted.
    $DB->update_record('aireading_attempts', $attempt);

    // Trigger event.
    \mod_aireading\event\attempt_submitted::create_from_attempt($attempt, $cm, $moduleinstance)->trigger();

    // Queue for analysis (adhoc task).
    $task = new \mod_aireading\task\analyze_attempt_task();
    $task->set_custom_data([
        'attemptid' => $attempt->id,
    ]);
    \core\task\manager::queue_adhoc_task($task);

    // Success response.
    echo json_encode([
        'success' => true,
        'attemptid' => $attempt->id,
        'message' => get_string('attemptsubmitted', 'mod_aireading'),
    ]);

} catch (Exception $e) {
    // Error response.
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
