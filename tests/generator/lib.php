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
 * Data generator class
 *
 * @package mod_aireading
 * @category test
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aireading_generator extends testing_module_generator {
    /**
     * Creates an instance of the module for testing purposes.
     *
     * Module type will be taken from the class name.
     *
     * @param array|stdClass $record data for module being generated. Requires 'course' key
     *     (an id or the full object). Also can have any fields from add module form.
     * @param null|array $options general options for course module, can be merged into $record
     * @return stdClass record from module-defined table with additional field
     *     cmid (corresponding id in course_modules table)
     */
    public function create_instance($record = null, ?array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/aireading/lib.php');

        $record = (object)(array)$record;

        // Set default values for plugin-specific fields.
        $defaults = [
            'readingtext' => 'Der Wald ist dunkel und geheimnisvoll. Die Eichhörnchen springen von Baum zu Baum.',
            'readingtextformat' => FORMAT_HTML,
            'targetwpm' => 100,
            'maxattempts' => 3,
            'grademethod' => 1,
            'language' => 'de',
            'readingdifficulty' => 1,
            'silencethreshold' => 10,
            'enablepronunciation' => 0,
            'minconfidence' => 0.80,
            'analysisversion' => 1,
            'grade' => 100,
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($record->$key)) {
                $record->$key = $value;
            }
        }

        $instance = parent::create_instance($record, (array)$options);

        return $instance;
    }

    /**
     * Create a reading attempt
     *
     * @param array $record Attempt data
     * @return stdClass Attempt record
     */
    public function create_attempt(array $record) {
        global $DB;

        $defaults = [
            'audiofileid' => null,
            'status' => 0,
            'timestarted' => time(),
            'timefinished' => null,
            'timeanalyzed' => null,
            'duration' => 0,
            'wordcount_original' => 0,
            'wordcount_transcribed' => 0,
            'transcription' => '',
            'analysis_data' => null,
            'wpm' => 0.00,
            'accuracy_score' => 0.00,
            'fluency_score' => 0.00,
            'pronunciation_score' => null,
            'grade' => 0.00,
            'analysisversion' => 1,
            'teacherfeedback' => null,
        ];

        $record = array_merge($defaults, $record);
        $record['id'] = $DB->insert_record('aireading_attempts', $record);

        return (object)$record;
    }

    /**
     * Create a mock analyzed attempt with full data
     *
     * @param int $aireadingid Activity ID
     * @param int $userid User ID
     * @param int $attempt Attempt number
     * @param array $options Optional settings
     * @return stdClass Complete attempt with analysis
     */
    public function create_analyzed_attempt($aireadingid, $userid, $attempt = 1, array $options = []) {
        $defaults = [
            'wpm' => 95.5,
            'accuracy' => 85.0,
            'fluency' => 80.0,
            'pronunciation' => null,
            'duration' => 60,
            'wordcount_original' => 15,
            'wordcount_transcribed' => 14,
        ];

        $options = array_merge($defaults, $options);

        // Create analysis data JSON.
        $analysisdata = [
            'metrics' => [
                'wer' => 0.12,
                'wpm' => $options['wpm'],
                'wordcount_original' => $options['wordcount_original'],
                'wordcount_transcribed' => $options['wordcount_transcribed'],
            ],
            'scores' => [
                'accuracy' => $options['accuracy'],
                'fluency' => $options['fluency'],
                'pronunciation' => $options['pronunciation'],
                'grade' => ($options['accuracy'] + $options['fluency']) / 2,
            ],
            'errors' => [
                [
                    'type' => 'substitution', // Direct type (new structure).
                    'position' => 5,
                    'expected' => 'Wald',
                    'actual' => 'Walt',
                    'timestamp' => 2.5,
                ],
                [
                    'type' => 'pause', // Direct type (new structure).
                    'position' => 8,
                    'word' => 'geheimnisvoll',
                    'duration' => 0.8,
                    'timestamp' => 5.2,
                ],
            ],
        ];

        $record = [
            'aireading_id' => $aireadingid,
            'userid' => $userid,
            'attempt' => $attempt,
            'status' => 2, // Analyzed.
            'timestarted' => time() - 120,
            'timefinished' => time() - 60,
            'timeanalyzed' => time(),
            'duration' => $options['duration'],
            'wordcount_original' => $options['wordcount_original'],
            'wordcount_transcribed' => $options['wordcount_transcribed'],
            'transcription' => 'Der Walt ist dunkel und geheimnisvoll.',
            'analysis_data' => json_encode($analysisdata),
            'wpm' => $options['wpm'],
            'accuracy_score' => $options['accuracy'],
            'fluency_score' => $options['fluency'],
            'pronunciation_score' => $options['pronunciation'],
            'grade' => ($options['accuracy'] + $options['fluency']) / 2,
            'analysisversion' => 1,
        ];

        return $this->create_attempt($record);
    }

    /**
     * Generate mock STT response
     *
     * @param string $text Transcribed text
     * @param array $segments Word segments with timestamps
     * @return array STT response format
     */
    public function create_mock_stt_response($text = null, $segments = null) {
        if ($text === null) {
            $text = 'Der Wald ist dunkel';
        }

        if ($segments === null) {
            $segments = [
                [
                    'word' => 'Der',
                    'start' => 0.0,
                    'end' => 0.3,
                    'confidence' => 0.98,
                ],
                [
                    'word' => 'Wald',
                    'start' => 0.35,
                    'end' => 0.7,
                    'confidence' => 0.95,
                ],
                [
                    'word' => 'ist',
                    'start' => 0.75,
                    'end' => 0.9,
                    'confidence' => 0.97,
                ],
                [
                    'word' => 'dunkel',
                    'start' => 1.0,
                    'end' => 1.4,
                    'confidence' => 0.92,
                ],
            ];
        }

        return [
            'text' => $text,
            'segments' => $segments,
        ];
    }
}
