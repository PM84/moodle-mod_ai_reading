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
 * Scheduled task to analyze submitted reading attempts
 *
 * This task runs every minute to process attempts that have been
 * submitted but not yet analyzed. It performs STT transcription
 * and stores the results.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\task;

/**
 * Scheduled task class for analyzing reading attempts
 *
 * Processes submitted attempts (status=1) in batches:
 * 1. Retrieve audio file
 * 2. Send to STT service via ai_service
 * 3. Store transcription
 * 4. Update status to analyzed (status=2)
 * 5. Handle errors by setting status=3
 */
class analyze_attempt_task extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string Task name for display in admin UI
     */
    public function get_name() {
        return get_string('task_analyze_attempts', 'mod_aireading');
    }

    /**
     * Execute the task
     *
     * Processes up to 50 submitted attempts per run to avoid
     * long-running tasks. Attempts are analyzed via STT and
     * results are stored in the database.
     */
    public function execute() {
        global $DB;

        // Find submitted attempts waiting for analysis (status = 1).
        $sql = "SELECT a.*,
                       ar.language, ar.analysisversion, ar.readingtext,
                       ar.targetwpm, ar.enablepronunciation, ar.minconfidence
                  FROM {aireading_attempts} a
                  JOIN {aireading} ar ON ar.id = a.aireading_id
                 WHERE a.status = :status
              ORDER BY a.timefinished ASC";

        $params = ['status' => 1]; // 1 = submitted.
        $attempts = $DB->get_records_sql($sql, $params, 0, 50);

        if (empty($attempts)) {
            mtrace('No attempts waiting for analysis.');
            return;
        }

        mtrace('Found ' . count($attempts) . ' attempts to analyze.');

        $aiservice = new \mod_aireading\ai_service();
        $fs = get_file_storage();

        foreach ($attempts as $attempt) {
            try {
                mtrace("Processing attempt {$attempt->id} for user {$attempt->userid}...");

                // Get course module and context.
                $cmid = $this->get_cm_from_attempt($attempt);
                $context = \context_module::instance($cmid);

                // Get the audio file.
                $files = $fs->get_area_files(
                    $context->id,
                    'mod_aireading',
                    'attemptaudio',
                    $attempt->id,
                    'timemodified DESC',
                    false
                );

                if (empty($files)) {
                    throw new \moodle_exception(
                        'audiofile_not_found',
                        'mod_aireading',
                        '',
                        $attempt->id
                    );
                }

                $file = reset($files);
                $filepath = $file->copy_content_to_temp();

                // Transcribe audio via AI service.
                $sttdata = $aiservice->transcribe_audio($filepath, $attempt->language);

                // Clean up temp file.
                @unlink($filepath);

                // Perform analysis.
                $analysisresults = $this->perform_analysis($attempt, $sttdata);

                // Store transcription and analysis results.
                $this->store_transcription($attempt, $sttdata, $analysisresults);

                mtrace("  ✓ Successfully analyzed attempt {$attempt->id}");
            } catch (\Exception $e) {
                mtrace("  ✗ Error analyzing attempt {$attempt->id}: " . $e->getMessage());

                // Mark attempt as failed.
                $manager = new \mod_aireading\attempt_manager();
                $manager->mark_attempt_error($attempt->id, 'analysis_failed', $e->getMessage());
            }
        }

        mtrace('Analysis batch completed.');
    }

    /**
     * Perform analysis of transcription
     *
     * Calls the analysis engine to compare transcription with original text
     * and calculate all metrics, scores, and error detections.
     *
     * @param \stdClass $attempt Attempt record with reading settings
     * @param array $sttdata Parsed STT data with 'text' and 'segments'
     * @return array Analysis results from engine
     */
    private function perform_analysis($attempt, $sttdata) {
        // Prepare settings object for analysis engine.
        $settings = new \stdClass();
        $settings->language = $attempt->language;
        $settings->targetwpm = $attempt->targetwpm;
        $settings->enablepronunciation = (bool)$attempt->enablepronunciation;
        $settings->minconfidence = (float)$attempt->minconfidence;

        // Run analysis engine.
        $engine = new \mod_aireading\analysis_engine();
        $results = $engine->analyze($attempt->readingtext, $sttdata, $settings);

        return $results;
    }

    /**
     * Store transcription results in attempt record
     *
     * Updates the attempt with:
     * - Full transcription text
     * - Word counts
     * - Analysis data (complete analysis JSON)
     * - Calculated scores (WPM, accuracy, fluency, pronunciation, grade)
     * - Analysis timestamp
     * - Status = 2 (analyzed)
     *
     * @param \stdClass $attempt Attempt record
     * @param array $sttdata Parsed STT data with 'text' and 'segments'
     * @param array $analysisresults Analysis results from engine
     */
    private function store_transcription($attempt, $sttdata, $analysisresults) {
        global $DB;

        // Extract metrics and scores from analysis results.
        $metrics = $analysisresults['metrics'];
        $scores = $analysisresults['scores'];

        // Prepare update record.
        $update = new \stdClass();
        $update->id = $attempt->id;
        $update->transcription = $sttdata['text'];
        $update->wordcount_original = $metrics['wordcount_original'];
        $update->wordcount_transcribed = $metrics['wordcount_transcribed'];
        $update->duration = $metrics['duration'];
        $update->wpm = $metrics['wpm'];
        $update->accuracy_score = $scores['accuracy'];
        $update->fluency_score = $scores['fluency'];
        $update->pronunciation_score = $scores['pronunciation']; // NULL if disabled.
        $update->grade = $scores['grade'];
        $update->analysis_data = json_encode($analysisresults);
        $update->timeanalyzed = time();
        $update->status = 2; // 2 = analyzed.
        $update->analysisversion = $attempt->analysisversion;

        $DB->update_record('aireading_attempts', $update);

        // Update grades in gradebook.
        $aireading = $DB->get_record('aireading', ['id' => $attempt->aireading_id], '*', MUST_EXIST);
        aireading_update_grades($aireading, $attempt->userid);

        // Get course module for event context.
        $cm = get_coursemodule_from_instance('aireading', $attempt->aireading_id);

        // Trigger event.
        $event = \mod_aireading\event\attempt_analyzed::create([
            'objectid' => $attempt->id,
            'context' => \context_module::instance($cm->id),
            'relateduserid' => $attempt->userid,
            'other' => [
                'attemptid' => $attempt->id,
                'userid' => $attempt->userid,
                'wordcount' => $metrics['wordcount_transcribed'],
            ],
        ]);
        $event->trigger();
    }

    /**
     * Get context module ID from attempt
     *
     * Helper method to retrieve the course module ID associated
     * with an attempt's activity instance.
     *
     * @param \stdClass $attempt Attempt record
     * @return int Course module ID
     * @throws \moodle_exception If course module not found
     */
    private function get_cm_from_attempt($attempt) {
        $cm = get_coursemodule_from_instance('aireading', $attempt->aireading_id);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule', 'mod_aireading');
        }
        return $cm->id;
    }
}
