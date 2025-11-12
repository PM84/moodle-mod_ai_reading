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
 * View aireading instance
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Course module id - REQUIRED (not optional for security).
$id = required_param('id', PARAM_INT);

// Get course module and validate.
$cm = get_coursemodule_from_id('aireading', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record('aireading', ['id' => $cm->instance], '*', MUST_EXIST);

// Security: require login and capability check.
require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aireading:view', $context);

// Trigger course module viewed event.
\mod_aireading\event\course_module_viewed::create_from_record($moduleinstance, $cm, $course)->trigger();

// Mark as viewed for completion.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Set up page.
$PAGE->set_url('/mod/aireading/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

// Display intro with proper escaping.
if ($moduleinstance->intro) {
    echo $OUTPUT->box(
        format_module_intro('aireading', $moduleinstance, $cm->id),
        'generalbox mod_introbox',
        'aireadingintro'
    );
}

// Get the renderer.
$renderer = $PAGE->get_renderer('mod_aireading');

// Display different views based on capabilities.
if (has_capability('mod/aireading:submit', $context)) {
    // Student view: Show reading interface.
    echo $renderer->render_reading_view($moduleinstance, $cm, $context, $USER->id);
} else if (has_capability('mod/aireading:viewallattempts', $context)) {
    // Teacher report link.
    echo html_writer::tag('h3', get_string('teacherview', 'mod_aireading'));
    $reporturl = new moodle_url('/mod/aireading/report.php', ['id' => $cm->id]);
    echo html_writer::link(
        $reporturl,
        get_string('viewreport', 'mod_aireading'),
        ['class' => 'btn btn-primary']
    );
}

echo $OUTPUT->footer();
