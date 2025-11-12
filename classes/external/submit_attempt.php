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
 * External API for submitting reading attempts
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use mod_aireading\attempt_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API for submitting reading attempts
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_attempt extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'audiodata' => new external_value(PARAM_RAW, 'Base64 encoded audio data'),
            'filename' => new external_value(PARAM_FILE, 'Audio filename'),
            'mimetype' => new external_value(PARAM_RAW, 'Audio MIME type'),
        ]);
    }

    /**
     * Submit a reading attempt
     *
     * @param int $cmid Course module ID
     * @param string $audiodata Base64 encoded audio data
     * @param string $filename Audio filename
     * @param string $mimetype Audio MIME type
     * @return array Result data
     */
    public static function execute($cmid, $audiodata, $filename, $mimetype) {
        global $DB, $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'audiodata' => $audiodata,
            'filename' => $filename,
            'mimetype' => $mimetype,
        ]);

        // Context validation.
        $cm = get_coursemodule_from_id('aireading', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Capability check.
        require_capability('mod/aireading:submit', $context);

        // Get AI reading instance.
        $aireadingrecord = $DB->get_record('aireading', ['id' => $cm->instance], '*', MUST_EXIST);

        // Decode audio data first.
        $audiocontent = base64_decode($params['audiodata']);
        if ($audiocontent === false) {
            throw new \moodle_exception('invalidaudiodata', 'mod_aireading');
        }

        // Validate file size (max 50MB).
        $maxsize = 50 * 1024 * 1024;
        if (strlen($audiocontent) > $maxsize) {
            throw new \moodle_exception('audiofiletoolarge', 'mod_aireading');
        }

        // Validate minimum duration (at least 2 seconds of data, rough estimate: 16KB for 2 seconds).
        if (strlen($audiocontent) < 16000) {
            throw new \moodle_exception('audiofiletoshort', 'mod_aireading');
        }

        // Create attempt.
        $manager = new attempt_manager();
        $attempt = $manager->create_attempt($aireadingrecord->id, $USER->id);

        // Save audio file.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_aireading',
            'filearea' => 'attemptaudio',
            'itemid' => $attempt->id,
            'filepath' => '/',
            'filename' => $params['filename'],
            // Don't trust user-supplied MIME type - let Moodle determine it.
        ];

        $file = $fs->create_file_from_string($filerecord, $audiocontent);

        // Verify actual MIME type after file creation.
        $actualmimetype = $file->get_mimetype();
        $allowedextensions = ['mp3', 'wav', 'webm', 'ogg', 'm4a'];
        $validmimetypes = [];

        foreach ($allowedextensions as $ext) {
            $types = \core_filetypes::get_file_extension($ext);
            if ($types && isset($types['type'])) {
                $validmimetypes[] = $types['type'];
            }
        }

        // Fallback: common audio MIME types.
        $validmimetypes = array_merge($validmimetypes, [
            'audio/webm',
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/mp4',
            'audio/x-m4a',
        ]);

        $validmimetypes = array_unique($validmimetypes);

        if (!in_array($actualmimetype, $validmimetypes)) {
            // Delete invalid file.
            $file->delete();
            $manager->mark_attempt_error($attempt->id, 'invalidmimetype', 'Invalid audio file type: ' . $actualmimetype);
            throw new \moodle_exception('invalidaudioformat', 'mod_aireading');
        }

        // Run antivirus scan.
        try {
            \core\antivirus\manager::scan_file($file, $params['filename'], false);
        } catch (\core\antivirus\scanner_exception $e) {
            // Virus detected - delete file and mark attempt as failed.
            $file->delete();
            $manager->mark_attempt_error($attempt->id, 'virusdetected', $e->getMessage());
            throw new \moodle_exception('invalidaudioformat', 'mod_aireading', '', null, 'Antivirus scan failed');
        }

        // Save file reference and finalize attempt.
        $manager->save_audio_file($attempt->id, $file);

        return [
            'success' => true,
            'attemptid' => $attempt->id,
            'message' => get_string('attemptsubmitted', 'mod_aireading'),
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success status'),
            'attemptid' => new external_value(PARAM_INT, 'Attempt ID'),
            'message' => new external_value(PARAM_TEXT, 'Response message'),
        ]);
    }
}
