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
 * Callback implementations for aireading
 *
 * Documentation: {@link https://moodledev.io/docs/apis/plugintypes/mod}
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in module
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function aireading_supports($feature) {
    if (defined('FEATURE_MOD_PURPOSE') && $feature == FEATURE_MOD_PURPOSE) {
        // Available since Moodle 4.0.
        return MOD_PURPOSE_CONTENT;
    }
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        default:
            return null;
    }
}

/**
 * Add aireading instance
 *
 * Given an object containing all the necessary data, (defined by the form in mod_form.php)
 * this function will create a new instance and return the id of the instance
 *
 * @param stdClass $moduleinstance form data
 * @param mod_aireading_mod_form $form the form
 * @return int new instance id
 */
function aireading_add_instance($moduleinstance, $form = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = time();

    // Process reading text editor.
    if (isset($moduleinstance->readingtext_editor)) {
        $moduleinstance->readingtext = $moduleinstance->readingtext_editor['text'];
        $moduleinstance->readingtextformat = $moduleinstance->readingtext_editor['format'];
    }

    $id = $DB->insert_record('aireading', $moduleinstance);

    // Save any files.
    if (isset($moduleinstance->readingtext_editor)) {
        $context = context_module::instance($moduleinstance->coursemodule);
        $moduleinstance->readingtext = file_save_draft_area_files(
            $moduleinstance->readingtext_editor['itemid'],
            $context->id,
            'mod_aireading',
            'readingtext',
            0,
            ['subdirs' => false, 'maxfiles' => 0],
            $moduleinstance->readingtext_editor['text']
        );
        $DB->update_record('aireading', ['id' => $id, 'readingtext' => $moduleinstance->readingtext]);
    }

    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $moduleinstance->coursemodule,
        'aireading',
        $id,
        $completiontimeexpected
    );
    return $id;
}

/**
 * Updates an instance of the aireading in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param stdClass $moduleinstance An object from the form in mod_form.php
 * @param mod_aireading_mod_form $form The form
 * @return bool True if successful, false otherwise
 */
function aireading_update_instance($moduleinstance, $form = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    // Process reading text editor.
    if (isset($moduleinstance->readingtext_editor)) {
        $moduleinstance->readingtext = $moduleinstance->readingtext_editor['text'];
        $moduleinstance->readingtextformat = $moduleinstance->readingtext_editor['format'];

        // Save any files.
        $context = context_module::instance($moduleinstance->coursemodule);
        $moduleinstance->readingtext = file_save_draft_area_files(
            $moduleinstance->readingtext_editor['itemid'],
            $context->id,
            'mod_aireading',
            'readingtext',
            0,
            ['subdirs' => false, 'maxfiles' => 0],
            $moduleinstance->readingtext_editor['text']
        );
    }

    $DB->update_record('aireading', $moduleinstance);

    $completiontimeexpected = !empty($moduleinstance->completionexpected) ? $moduleinstance->completionexpected : null;
    \core_completion\api::update_completion_date_event(
        $moduleinstance->coursemodule,
        'aireading',
        $moduleinstance->id,
        $completiontimeexpected
    );

    return true;
}

/**
 * Removes an instance of the aireading from the database.
 *
 * @param int $id Id of the module instance
 * @return bool True if successful, false otherwise
 */
function aireading_delete_instance($id) {
    global $DB;

    $record = $DB->get_record('aireading', ['id' => $id]);
    if (!$record) {
        return false;
    }

    // Delete all attempts and their files.
    $attempts = $DB->get_records('aireading_attempts', ['aireading_id' => $id]);
    $cm = get_coursemodule_from_instance('aireading', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();

        foreach ($attempts as $attempt) {
            // Delete audio files.
            $fs->delete_area_files($context->id, 'mod_aireading', 'attemptaudio', $attempt->id);
            $fs->delete_area_files($context->id, 'mod_aireading', 'attempttranscript', $attempt->id);
        }

        // Delete reading text files.
        $fs->delete_area_files($context->id, 'mod_aireading', 'readingtext');
    }

    // Delete all attempts.
    $DB->delete_records('aireading_attempts', ['aireading_id' => $id]);

    // Delete all calendar events.
    $events = $DB->get_records('event', ['modulename' => 'aireading', 'instance' => $record->id]);
    foreach ($events as $event) {
        calendar_event::load($event)->delete();
    }

    // Delete the instance.
    $DB->delete_records('aireading', ['id' => $id]);

    return true;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 */
function mod_aireading_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['content'], $filter);
    return $updates;
}

/**
 * Serves the files from the aireading file areas.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object
 * @param stdClass $context The context
 * @param string $filearea The name of the file area
 * @param array $args Extra arguments (itemid, path)
 * @param bool $forcedownload Whether or not force download
 * @param array $options Additional options affecting the file serving
 * @return bool False if the file not found, just send the file otherwise and do not return anything
 */
function aireading_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    // Check the contextlevel is as expected.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the user is logged in and has access to the module.
    require_login($course, false, $cm);
    require_capability('mod/aireading:view', $context);

    // Check the relevant file area.
    if (!in_array($filearea, ['readingtext', 'attemptaudio', 'attempttranscript'])) {
        return false;
    }

    // For attempt files, check access permissions.
    if (in_array($filearea, ['attemptaudio', 'attempttranscript'])) {
        $attemptid = (int)array_shift($args);
        if (!$attemptid || $attemptid <= 0) {
            return false;
        }

        // SECURITY: Get attempt AND verify it belongs to this activity in ONE query.
        $attempt = $DB->get_record(
            'aireading_attempts',
            [
                'id' => $attemptid,
                'aireading_id' => $cm->instance,
            ],
            '*',
            MUST_EXIST
        );

        // Check if user owns this attempt or has permission to view all attempts.
        $isown = ($attempt->userid == $USER->id);
        $canviewall = has_capability('mod/aireading:viewallattempts', $context);

        if (!$isown && !$canviewall) {
            return false;
        }
    }

    // Extract the filename - SECURITY: validate filename.
    $filename = array_pop($args);
    if (!$filename || $filename === '.' || $filename === '..') {
        return false;
    }

    // Security: prevent directory traversal.
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }

    // Build the file path.
    $filepath = '/';
    if (!empty($args)) {
        $filepath .= implode('/', $args) . '/';
    }

    // Retrieve the file from the file storage.
    $fs = get_file_storage();
    $itemid = 0;
    if (in_array($filearea, ['attemptaudio', 'attempttranscript'])) {
        $itemid = $attemptid;
    }

    $file = $fs->get_file($context->id, 'mod_aireading', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    // Send the file.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Create or update grade item for the given aireading instance.
 *
 * @param stdClass $moduleinstance aireading instance object with extra cmidnumber and modname property
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function aireading_grade_item_update($moduleinstance, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [];
    $item['itemname'] = clean_param($moduleinstance->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax'] = 100;
    $item['grademin'] = 0;

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/aireading',
        $moduleinstance->course,
        'mod',
        'aireading',
        $moduleinstance->id,
        0,
        $grades,
        $item
    );
}

/**
 * Delete grade item for given aireading instance.
 *
 * @param stdClass $moduleinstance aireading instance object
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED
 */
function aireading_grade_item_delete($moduleinstance) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/aireading',
        $moduleinstance->course,
        'mod',
        'aireading',
        $moduleinstance->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Update grades in the gradebook.
 *
 * @param stdClass $moduleinstance aireading instance object
 * @param int $userid specific user only, 0 means all users
 * @param bool $nullifnone If true and the user has no attempts, return null; otherwise return 0
 * @return void
 */
function aireading_update_grades($moduleinstance, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($userid != 0) {
        $users = [$userid];
    } else {
        // Get all users who have submitted attempts.
        $sql = "SELECT DISTINCT userid
                  FROM {aireading_attempts}
                 WHERE aireading_id = :aireadingid
                   AND status = :status";
        $params = [
            'aireadingid' => $moduleinstance->id,
            'status' => 2, // Analyzed.
        ];
        $userrecords = $DB->get_records_sql($sql, $params);
        $users = array_keys($userrecords);
    }

    if (empty($users)) {
        return;
    }

    $grades = [];
    foreach ($users as $userid) {
        $grade = aireading_calculate_user_grade($moduleinstance, $userid);

        if ($grade === null && !$nullifnone) {
            $grade = 0;
        }

        if ($grade !== null) {
            $grades[$userid] = (object)[
                'userid' => $userid,
                'rawgrade' => $grade,
            ];
        }
    }

    aireading_grade_item_update($moduleinstance, $grades);
}

/**
 * Calculate the grade for a user based on the grading method.
 *
 * @param stdClass $moduleinstance aireading instance object
 * @param int $userid User ID
 * @return float|null Grade value or null if no attempts
 */
function aireading_calculate_user_grade($moduleinstance, $userid) {
    global $DB;

    // Get all analyzed attempts for this user.
    $attempts = $DB->get_records('aireading_attempts', [
        'aireading_id' => $moduleinstance->id,
        'userid' => $userid,
        'status' => 2, // Analyzed.
    ], 'attempt ASC', 'id, grade');

    if (empty($attempts)) {
        return null;
    }

    $grades = array_column($attempts, 'grade');
    $grademethod = isset($moduleinstance->grademethod) ? $moduleinstance->grademethod : 1;

    switch ($grademethod) {
        case 1: // Highest grade.
            return max($grades);
        case 2: // Latest grade.
            return end($grades);
        case 3: // Average grade.
            return array_sum($grades) / count($grades);
        default:
            return max($grades);
    }
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $moduleinstance aireading instance object
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function aireading_get_user_grades($moduleinstance, $userid = 0) {
    global $DB;

    $params = ['aireadingid' => $moduleinstance->id];
    $usersql = '';
    if ($userid) {
        $usersql = ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    // Get all analyzed attempts.
    $sql = "SELECT userid, MAX(grade) as grade
              FROM {aireading_attempts}
             WHERE aireading_id = :aireadingid
               AND status = 2
               $usersql
          GROUP BY userid";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Checks if scale is being used by any instance of aireading.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return bool True if the scale is used by any aireading instance
 */
function aireading_scale_used_anywhere($scaleid) {
    // We do not use scales in aireading.
    return false;
}

/**
 * Checks if scale is being used by given aireading instance.
 *
 * @param int $instanceid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool True if the scale is used by the given aireading instance
 */
function aireading_scale_used($instanceid, $scaleid) {
    // We do not use scales in aireading.
    return false;
}
