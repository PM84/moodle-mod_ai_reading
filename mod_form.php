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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for adding and editing aireading instances
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aireading_mod_form extends moodleform_mod {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General fieldset.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        if (!empty($this->_features->introeditor)) {
            // Description element that is usually added to the General fieldset.
            $this->standard_intro_elements();
        }

        // Reading text configuration.
        $mform->addElement('header', 'readingtextheader', get_string('readingtextheader', 'mod_aireading'));

        // Reading text editor.
        $mform->addElement(
            'editor',
            'readingtext_editor',
            get_string('readingtext', 'mod_aireading'),
            ['rows' => 10],
            ['maxfiles' => 0, 'maxbytes' => 0, 'context' => $this->context]
        );
        $mform->setType('readingtext_editor', PARAM_RAW);
        $mform->addRule('readingtext_editor', null, 'required', null, 'client');
        $mform->addHelpButton('readingtext_editor', 'readingtext', 'mod_aireading');

        // Language.
        $languages = [
            'de' => get_string('german', 'mod_aireading'),
            'en' => get_string('english', 'mod_aireading'),
        ];
        $mform->addElement(
            'select',
            'language',
            get_string('language', 'mod_aireading'),
            $languages
        );
        $mform->setDefault('language', 'de');
        $mform->addHelpButton('language', 'language', 'mod_aireading');

        // Reading difficulty.
        $difficulties = [
            1 => get_string('difficulty_easy', 'mod_aireading'),
            2 => get_string('difficulty_medium', 'mod_aireading'),
            3 => get_string('difficulty_advanced', 'mod_aireading'),
        ];
        $mform->addElement(
            'select',
            'readingdifficulty',
            get_string('readingdifficulty', 'mod_aireading'),
            $difficulties
        );
        $mform->setDefault('readingdifficulty', 1);
        $mform->addHelpButton('readingdifficulty', 'readingdifficulty', 'mod_aireading');

        // Assessment settings.
        $mform->addElement('header', 'assessmentsettings', get_string('assessmentsettings', 'mod_aireading'));

        // Target WPM.
        $mform->addElement(
            'text',
            'targetwpm',
            get_string('targetwpm', 'mod_aireading')
        );
        $mform->setType('targetwpm', PARAM_INT);
        $mform->setDefault('targetwpm', 100);
        $mform->addRule('targetwpm', null, 'required', null, 'client');
        $mform->addRule('targetwpm', null, 'numeric', null, 'client');
        $mform->addHelpButton('targetwpm', 'targetwpm', 'mod_aireading');

        // Max attempts.
        $mform->addElement(
            'text',
            'maxattempts',
            get_string('maxattempts', 'mod_aireading')
        );
        $mform->setType('maxattempts', PARAM_INT);
        $mform->setDefault('maxattempts', 3);
        $mform->addRule('maxattempts', null, 'required', null, 'client');
        $mform->addRule('maxattempts', null, 'numeric', null, 'client');
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_aireading');

        // Grade method.
        $grademethods = [
            1 => get_string('grademethod_highest', 'mod_aireading'),
            2 => get_string('grademethod_latest', 'mod_aireading'),
            3 => get_string('grademethod_average', 'mod_aireading'),
        ];
        $mform->addElement(
            'select',
            'grademethod',
            get_string('grademethod', 'mod_aireading'),
            $grademethods
        );
        $mform->setDefault('grademethod', 1);
        $mform->addHelpButton('grademethod', 'grademethod', 'mod_aireading');

        // Recording settings.
        $mform->addElement('header', 'recordingsettings', get_string('recordingsettings', 'mod_aireading'));

        // Silence threshold.
        $mform->addElement(
            'text',
            'silencethreshold',
            get_string('silencethreshold', 'mod_aireading')
        );
        $mform->setType('silencethreshold', PARAM_INT);
        $mform->setDefault('silencethreshold', 10);
        $mform->addRule('silencethreshold', null, 'required', null, 'client');
        $mform->addRule('silencethreshold', null, 'numeric', null, 'client');
        $mform->addHelpButton('silencethreshold', 'silencethreshold', 'mod_aireading');

        // Pronunciation assessment.
        $mform->addElement(
            'header',
            'pronunciation_header',
            get_string('pronunciation_settings', 'mod_aireading')
        );

        // Enable pronunciation.
        $mform->addElement(
            'advcheckbox',
            'enablepronunciation',
            get_string('enablepronunciation', 'mod_aireading'),
            get_string('enablepronunciation_desc', 'mod_aireading')
        );
        $mform->setDefault('enablepronunciation', 0);
        $mform->addHelpButton('enablepronunciation', 'enablepronunciation', 'mod_aireading');

        // Minimum confidence.
        $confidencelevels = [
            '0.70' => '70%',
            '0.75' => '75%',
            '0.80' => '80% (' . get_string('recommended', 'mod_aireading') . ')',
            '0.85' => '85%',
            '0.90' => '90%',
        ];
        $mform->addElement(
            'select',
            'minconfidence',
            get_string('minconfidence', 'mod_aireading'),
            $confidencelevels
        );
        $mform->setDefault('minconfidence', '0.80');
        $mform->addHelpButton('minconfidence', 'minconfidence', 'mod_aireading');
        $mform->hideIf('minconfidence', 'enablepronunciation', 'notchecked');

        // Other standard elements that are displayed in their own fieldsets.
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Preprocess data before form display
     *
     * @param array $defaultvalues Default values
     */
    public function data_preprocessing(&$defaultvalues) {
        if ($this->current->instance) {
            // Prepare editor for reading text.
            $draftitemid = file_get_submitted_draft_itemid('readingtext');
            $defaultvalues['readingtext_editor']['text'] = file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_aireading',
                'readingtext',
                0,
                ['subdirs' => false, 'maxfiles' => 0],
                $defaultvalues['readingtext'] ?? ''
            );
            $defaultvalues['readingtext_editor']['format'] = $defaultvalues['readingtextformat'] ?? FORMAT_HTML;
            $defaultvalues['readingtext_editor']['itemid'] = $draftitemid;
        }
    }
}
