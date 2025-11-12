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
 * Statistics manager for AI Reading Trainer
 *
 * Provides methods for calculating and retrieving reading statistics
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Statistics manager class
 *
 * Handles calculation and retrieval of reading statistics including:
 * - Individual user performance metrics
 * - Course/activity averages
 * - Progress tracking over multiple attempts
 * - Pronunciation statistics (when enabled)
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statistics_manager {
    /**
     * Get user's best WPM score for an activity
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return float Best WPM score, or 0 if no attempts
     */
    public function get_user_best_wpm($aireadingid, $userid) {
        global $DB;

        $sql = "SELECT MAX(wpm) as bestwpm
                FROM {aireading_attempts}
                WHERE aireading_id = :aireadingid
                AND userid = :userid
                AND status = :status";

        $params = [
            'aireadingid' => $aireadingid,
            'userid' => $userid,
            'status' => 2, // Analyzed.
        ];

        $result = $DB->get_record_sql($sql, $params);

        return $result && $result->bestwpm ? (float)$result->bestwpm : 0.0;
    }

    /**
     * Get course average WPM for an activity
     *
     * @param int $aireadingid AI Reading activity ID
     * @return float Average WPM across all users, or 0 if no attempts
     */
    public function get_course_average_wpm($aireadingid) {
        global $DB;

        $sql = "SELECT AVG(wpm) as avgwpm
                FROM {aireading_attempts}
                WHERE aireading_id = :aireadingid
                AND status = :status";

        $params = [
            'aireadingid' => $aireadingid,
            'status' => 2, // Analyzed.
        ];

        $result = $DB->get_record_sql($sql, $params);

        return $result && $result->avgwpm ? (float)$result->avgwpm : 0.0;
    }

    /**
     * Get user's progress across all attempts
     *
     * Returns array of attempt data showing progression over time
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return array Array of attempt records with metrics
     */
    public function get_user_progress($aireadingid, $userid) {
        global $DB;

        $sql = "SELECT id, attempt, wpm, accuracy_score, fluency_score,
                       pronunciation_score, grade, timefinished
                FROM {aireading_attempts}
                WHERE aireading_id = :aireadingid
                AND userid = :userid
                AND status = :status
                ORDER BY attempt ASC";

        $params = [
            'aireadingid' => $aireadingid,
            'userid' => $userid,
            'status' => 2, // Analyzed.
        ];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get detailed statistics for a user
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \stdClass Statistics object with all metrics
     */
    public function get_user_statistics($aireadingid, $userid) {
        global $DB;

        $stats = new \stdClass();

        // Get all analyzed attempts.
        $attempts = $this->get_user_progress($aireadingid, $userid);

        if (empty($attempts)) {
            return $this->get_empty_statistics();
        }

        $stats->totalattempts = count($attempts);

        // Calculate aggregates.
        $wpms = [];
        $accuracies = [];
        $fluencies = [];
        $pronunciations = [];
        $grades = [];

        foreach ($attempts as $attempt) {
            $wpms[] = $attempt->wpm;
            $accuracies[] = $attempt->accuracy_score;
            $fluencies[] = $attempt->fluency_score;
            $grades[] = $attempt->grade;

            if ($attempt->pronunciation_score !== null) {
                $pronunciations[] = $attempt->pronunciation_score;
            }
        }

        // WPM statistics.
        $stats->bestwpm = !empty($wpms) ? max($wpms) : 0;
        $stats->avgwpm = !empty($wpms) ? array_sum($wpms) / count($wpms) : 0;
        $stats->latestwpm = !empty($wpms) ? end($wpms) : 0;

        // Accuracy statistics.
        $stats->bestaccuracy = !empty($accuracies) ? max($accuracies) : 0;
        $stats->avgaccuracy = !empty($accuracies) ? array_sum($accuracies) / count($accuracies) : 0;

        // Fluency statistics.
        $stats->bestfluency = !empty($fluencies) ? max($fluencies) : 0;
        $stats->avgfluency = !empty($fluencies) ? array_sum($fluencies) / count($fluencies) : 0;

        // Pronunciation statistics (if applicable).
        if (!empty($pronunciations)) {
            $stats->haspronunciation = true;
            $stats->bestpronunciation = max($pronunciations);
            $stats->avgpronunciation = array_sum($pronunciations) / count($pronunciations);
        } else {
            $stats->haspronunciation = false;
        }

        // Grade statistics.
        $stats->bestgrade = !empty($grades) ? max($grades) : 0;
        $stats->avggrade = !empty($grades) ? array_sum($grades) / count($grades) : 0;
        $stats->latestgrade = !empty($grades) ? end($grades) : 0;

        // Progress tracking.
        if (count($attempts) > 1) {
            $stats->hasimprovement = true;
            $firstattempt = reset($attempts);
            $lastattempt = end($attempts);

            $stats->wpmimprovement = $lastattempt->wpm - $firstattempt->wpm;
            $stats->accuracyimprovement = $lastattempt->accuracy_score - $firstattempt->accuracy_score;
            $stats->fluencyimprovement = $lastattempt->fluency_score - $firstattempt->fluency_score;

            if ($stats->haspronunciation) {
                $stats->pronunciationimprovement = $lastattempt->pronunciation_score - $firstattempt->pronunciation_score;
            }
        } else {
            $stats->hasimprovement = false;
        }

        return $stats;
    }

    /**
     * Get empty statistics object
     *
     * @return \stdClass Empty statistics with zero values
     */
    private function get_empty_statistics() {
        $stats = new \stdClass();
        $stats->totalattempts = 0;
        $stats->bestwpm = 0;
        $stats->avgwpm = 0;
        $stats->latestwpm = 0;
        $stats->bestaccuracy = 0;
        $stats->avgaccuracy = 0;
        $stats->bestfluency = 0;
        $stats->avgfluency = 0;
        $stats->haspronunciation = false;
        $stats->bestgrade = 0;
        $stats->avggrade = 0;
        $stats->latestgrade = 0;
        $stats->hasimprovement = false;

        return $stats;
    }

    /**
     * Get comparison data for user vs. course average
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \stdClass Comparison data
     */
    public function get_comparison_data($aireadingid, $userid) {
        $comparison = new \stdClass();

        $comparison->userbest = $this->get_user_best_wpm($aireadingid, $userid);
        $comparison->courseaverage = $this->get_course_average_wpm($aireadingid);

        // Calculate percentile ranking.
        if ($comparison->courseaverage > 0) {
            $comparison->percentile = ($comparison->userbest / $comparison->courseaverage) * 100;
        } else {
            $comparison->percentile = 0;
        }

        return $comparison;
    }

    /**
     * Get error breakdown statistics
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \stdClass Error statistics
     */
    public function get_error_statistics($aireadingid, $userid) {
        global $DB;

        $errorstats = new \stdClass();
        $errorstats->totalerrors = 0;
        $errorstats->accuracyerrors = 0;
        $errorstats->fluencyerrors = 0;
        $errorstats->pronunciationerrors = 0;

        // Get all analyzed attempts.
        $attempts = $this->get_user_progress($aireadingid, $userid);

        foreach ($attempts as $attempt) {
            $analysisdata = json_decode($attempt->analysis_data ?? '{}', true);

            if (!isset($analysisdata['errors'])) {
                continue;
            }

            foreach ($analysisdata['errors'] as $error) {
                $errorstats->totalerrors++;

                $type = $error['type'] ?? '';

                if (in_array($type, ['substitution', 'omission', 'insertion'])) {
                    $errorstats->accuracyerrors++;
                } else if (in_array($type, ['pause', 'hesitation'])) {
                    $errorstats->fluencyerrors++;
                } else if (in_array($type, ['pronunciation_bad', 'pronunciation_ok'])) {
                    $errorstats->pronunciationerrors++;
                }
            }
        }

        // Calculate percentages.
        if ($errorstats->totalerrors > 0) {
            $errorstats->accuracypercent = ($errorstats->accuracyerrors / $errorstats->totalerrors) * 100;
            $errorstats->fluencypercent = ($errorstats->fluencyerrors / $errorstats->totalerrors) * 100;
            $errorstats->pronunciationpercent = ($errorstats->pronunciationerrors / $errorstats->totalerrors) * 100;
        } else {
            $errorstats->accuracypercent = 0;
            $errorstats->fluencypercent = 0;
            $errorstats->pronunciationpercent = 0;
        }

        return $errorstats;
    }

    /**
     * Get pronunciation trend data across attempts
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return array|null Array of confidence scores per attempt, or null if not available
     */
    public function get_pronunciation_trend($aireadingid, $userid) {
        $attempts = $this->get_user_progress($aireadingid, $userid);

        if (empty($attempts)) {
            return null;
        }

        $trend = [];

        foreach ($attempts as $attempt) {
            if ($attempt->pronunciation_score === null) {
                return null; // Pronunciation not enabled.
            }

            $analysisdata = json_decode($attempt->analysis_data ?? '{}', true);

            $avgconfidence = 0;
            if (isset($analysisdata['heuristics']['pronunciation']['avg_confidence'])) {
                $avgconfidence = $analysisdata['heuristics']['pronunciation']['avg_confidence'];
            }

            $trend[] = [
                'attempt' => $attempt->attempt,
                'score' => $attempt->pronunciation_score,
                'confidence' => $avgconfidence * 100,
            ];
        }

        return $trend;
    }

    /**
     * Check if user has sufficient attempts for trend analysis
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @param int $minattempts Minimum attempts needed (default: 2)
     * @return bool True if sufficient attempts exist
     */
    public function has_sufficient_attempts($aireadingid, $userid, $minattempts = 2) {
        $attempts = $this->get_user_progress($aireadingid, $userid);
        return count($attempts) >= $minattempts;
    }

    /**
     * Get pronunciation statistics for a user
     *
     * Returns pronunciation-specific statistics including average score
     * and confidence data across all attempts.
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \stdClass|null Statistics object or null if pronunciation not enabled
     */
    public function get_pronunciation_statistics($aireadingid, $userid) {
        global $DB;

        // Get AI reading instance to check if pronunciation is enabled.
        $aireadingrecord = $DB->get_record('aireading', ['id' => $aireadingid], '*', MUST_EXIST);

        if (!$aireadingrecord->enablepronunciation) {
            return null; // Pronunciation not enabled.
        }

        $attempts = $this->get_user_progress($aireadingid, $userid);

        if (empty($attempts)) {
            return null;
        }

        $stats = new \stdClass();
        $pronunciationscores = [];

        foreach ($attempts as $attempt) {
            if ($attempt->pronunciation_score !== null) {
                $pronunciationscores[] = $attempt->pronunciation_score;
            }
        }

        if (empty($pronunciationscores)) {
            return null;
        }

        $stats->average_pronunciation = array_sum($pronunciationscores) / count($pronunciationscores);
        $stats->best_pronunciation = max($pronunciationscores);
        $stats->latest_pronunciation = end($pronunciationscores);
        $stats->total_attempts_with_pronunciation = count($pronunciationscores);

        return $stats;
    }

    /**
     * Get error distribution for a user
     *
     * Returns breakdown of error types (accuracy, fluency, pronunciation)
     * across all user attempts.
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return array Array with error distribution by category
     */
    public function get_error_distribution($aireadingid, $userid) {
        $attempts = $this->get_user_progress($aireadingid, $userid);

        $distribution = [
            'accuracy' => 0,
            'fluency' => 0,
            'pronunciation' => 0,
            'total' => 0,
        ];

        if (empty($attempts)) {
            return $distribution;
        }

        foreach ($attempts as $attempt) {
            $analysisdata = json_decode($attempt->analysis_data ?? '{}', true);

            if (!isset($analysisdata['errors'])) {
                continue;
            }

            foreach ($analysisdata['errors'] as $error) {
                $distribution['total']++;

                $type = $error['type'] ?? '';

                // Map error types to categories based on new structure.
                if (in_array($type, ['substitution', 'omission', 'insertion'])) {
                    $distribution['accuracy']++;
                } else if (in_array($type, ['pause', 'hesitation'])) {
                    $distribution['fluency']++;
                } else if ($type === 'pronunciation') {
                    $distribution['pronunciation']++;
                }
            }
        }

        return $distribution;
    }
}
