<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Teacher report for AI Reading Trainer
 *
 * Provides word difficulty analysis for pronunciation assessment
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\output;

/**
 * Teacher report renderable class
 *
 * Generates reports for teachers showing:
 * - Word difficulty analysis (pronunciation-based)
 * - Class-wide pronunciation issues
 * - Individual student progress
 * - Recommendations for targeted instruction
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_report {
    /** @var \mod_aireading\statistics_manager Statistics manager instance */
    private $statsmanager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->statsmanager = new \mod_aireading\statistics_manager();
    }

    /**
     * Get word difficulty analysis for activity
     *
     * Analyzes all student attempts to identify which words are most difficult
     * based on pronunciation confidence scores
     *
     * @param int $aireadingid AI Reading activity ID
     * @return array|null Array of word difficulty data, or null if not available
     */
    public function get_word_difficulty_analysis($aireadingid) {
        global $DB;

        // Get activity settings.
        $activity = $DB->get_record('aireading', ['id' => $aireadingid], '*', MUST_EXIST);

        // Check if pronunciation is enabled.
        if (!$activity->enablepronunciation) {
            return null;
        }

        // Get all analyzed attempts for this activity.
        $sql = "SELECT id, userid, analysis_data
                FROM {aireading_attempts}
                WHERE aireading_id = :aireadingid
                AND status = :status
                AND pronunciation_score IS NOT NULL";

        $params = [
            'aireadingid' => $aireadingid,
            'status' => 2, // Analyzed.
        ];

        $attempts = $DB->get_records_sql($sql, $params);

        if (empty($attempts)) {
            return null;
        }

        // Aggregate word confidence scores across all students.
        $worddata = [];
        $studentcount = [];

        foreach ($attempts as $attempt) {
            $analysisdata = json_decode($attempt->analysis_data ?? '{}', true);

            if (!isset($analysisdata['heuristics']['pronunciation']['word_scores'])) {
                continue;
            }

            $wordscores = $analysisdata['heuristics']['pronunciation']['word_scores'];

            foreach ($wordscores as $word => $confidence) {
                if (!isset($worddata[$word])) {
                    $worddata[$word] = [
                        'word' => $word,
                        'totalconfidence' => 0,
                        'count' => 0,
                        'students' => [],
                        'lowconfidencecount' => 0,
                    ];
                }

                $worddata[$word]['totalconfidence'] += $confidence;
                $worddata[$word]['count']++;

                // Track unique students.
                if (!in_array($attempt->userid, $worddata[$word]['students'])) {
                    $worddata[$word]['students'][] = $attempt->userid;
                }

                // Count low confidence instances.
                if ($confidence < $activity->minconfidence) {
                    $worddata[$word]['lowconfidencecount']++;
                }
            }
        }

        // Calculate averages and difficulty scores.
        $results = [];

        foreach ($worddata as $word => $data) {
            $avgconfidence = $data['totalconfidence'] / $data['count'];
            $studentcount = count($data['students']);
            $difficultypercentage = ($data['lowconfidencecount'] / $data['count']) * 100;

            $results[] = [
                'word' => $word,
                'avgconfidence' => $avgconfidence,
                'avgconfidence_percent' => round($avgconfidence * 100, 1),
                'studentcount' => $studentcount,
                'totalattempts' => $data['count'],
                'lowconfidencecount' => $data['lowconfidencecount'],
                'difficultypercent' => round($difficultypercentage, 1),
                'difficulty_class' => $this->get_difficulty_class($difficultypercentage),
            ];
        }

        // Sort by difficulty (highest difficulty first).
        usort($results, function ($a, $b) {
            return $b['difficultypercent'] - $a['difficultypercent'];
        });

        return $results;
    }

    /**
     * Get difficulty CSS class based on percentage
     *
     * @param float $difficultypercent Difficulty percentage
     * @return string CSS class name
     */
    private function get_difficulty_class($difficultypercent) {
        if ($difficultypercent >= 50) {
            return 'danger'; // Very difficult.
        } else if ($difficultypercent >= 25) {
            return 'warning'; // Moderately difficult.
        } else {
            return 'success'; // Easy.
        }
    }

    /**
     * Get student pronunciation overview
     *
     * @param int $aireadingid AI Reading activity ID
     * @return array Array of student data with pronunciation metrics
     */
    public function get_student_pronunciation_overview($aireadingid) {
        global $DB;

        // Get activity.
        $activity = $DB->get_record('aireading', ['id' => $aireadingid], '*', MUST_EXIST);

        if (!$activity->enablepronunciation) {
            return [];
        }

        // Get all students with attempts.
        $sql = "SELECT DISTINCT userid
                FROM {aireading_attempts}
                WHERE aireading_id = :aireadingid
                AND status = :status
                AND pronunciation_score IS NOT NULL";

        $params = [
            'aireadingid' => $aireadingid,
            'status' => 2,
        ];

        $userids = $DB->get_fieldset_sql($sql, $params);

        if (empty($userids)) {
            return [];
        }

        $overview = [];

        foreach ($userids as $userid) {
            $userstats = $this->statsmanager->get_user_statistics($aireadingid, $userid);

            if (!$userstats->haspronunciation) {
                continue;
            }

            $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');

            $overview[] = [
                'userid' => $userid,
                'fullname' => fullname($user),
                'email' => $user->email,
                'attempts' => $userstats->totalattempts,
                'bestpronunciation' => round($userstats->bestpronunciation, 1),
                'avgpronunciation' => round($userstats->avgpronunciation, 1),
                'improvement' => $userstats->hasimprovement && isset($userstats->pronunciationimprovement)
                    ? round($userstats->pronunciationimprovement, 1)
                    : 0,
                'status_class' => $this->get_status_class($userstats->bestpronunciation, $activity->minconfidence * 100),
            ];
        }

        // Sort by best pronunciation (lowest first - needs most help).
        usort($overview, function ($a, $b) {
            return $a['bestpronunciation'] - $b['bestpronunciation'];
        });

        return $overview;
    }

    /**
     * Get status CSS class based on pronunciation score
     *
     * @param float $score Pronunciation score
     * @param float $threshold Minimum threshold
     * @return string CSS class
     */
    private function get_status_class($score, $threshold) {
        if ($score >= $threshold) {
            return 'success';
        } else if ($score >= ($threshold - 10)) {
            return 'warning';
        } else {
            return 'danger';
        }
    }

    /**
     * Render word difficulty report
     *
     * @param int $aireadingid AI Reading activity ID
     * @return string HTML output
     */
    public function render_word_difficulty_report($aireadingid) {
        global $OUTPUT;

        $worddata = $this->get_word_difficulty_analysis($aireadingid);

        if ($worddata === null) {
            return html_writer::div(
                get_string('pronunciation_not_enabled', 'mod_aireading'),
                'alert alert-info'
            );
        }

        if (empty($worddata)) {
            return html_writer::div(
                get_string('no_pronunciation_data', 'mod_aireading'),
                'alert alert-info'
            );
        }

        $data = [
            'words' => $worddata,
            'totalwords' => count($worddata),
        ];

        return $OUTPUT->render_from_template('mod_aireading/word_difficulty_report', $data);
    }

    /**
     * Render student pronunciation overview
     *
     * @param int $aireadingid AI Reading activity ID
     * @return string HTML output
     */
    public function render_student_overview($aireadingid) {
        global $OUTPUT;

        $studentdata = $this->get_student_pronunciation_overview($aireadingid);

        if (empty($studentdata)) {
            return html_writer::div(
                get_string('no_student_pronunciation_data', 'mod_aireading'),
                'alert alert-info'
            );
        }

        $data = [
            'students' => $studentdata,
            'totalstudents' => count($studentdata),
        ];

        return $OUTPUT->render_from_template('mod_aireading/student_pronunciation_overview', $data);
    }

    /**
     * Get focus words for class
     *
     * Returns top N most difficult words that need focused instruction
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $limit Number of words to return (default: 10)
     * @return array Array of focus words with difficulty data
     */
    public function get_class_focus_words($aireadingid, $limit = 10) {
        $worddata = $this->get_word_difficulty_analysis($aireadingid);

        if ($worddata === null || empty($worddata)) {
            return [];
        }

        // Already sorted by difficulty - just take top N.
        return array_slice($worddata, 0, $limit);
    }
}
