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
 * Chart generator for AI Reading Trainer
 *
 * Creates visualizations using Moodle Chart API
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/chartlib.php');

/**
 * Chart generator class
 *
 * Generates various chart types for reading statistics:
 * 1. WPM comparison chart (user vs. target vs. average)
 * 2. Progress over attempts (line chart)
 * 3. Error type distribution (pie/donut chart)
 * 4. Pronunciation progress (if enabled)
 * 5. Improvement deltas (bar chart)
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chart_generator {
    /** @var statistics_manager Statistics manager instance */
    private $statsmanager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->statsmanager = new statistics_manager();
    }

    /**
     * Generate WPM comparison chart
     *
     * Shows user's WPM against target and course average
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @param \stdClass $moduleinstance Module instance
     * @return \core\chart_bar Bar chart object
     */
    public function generate_wpm_comparison_chart($aireadingid, $userid, $moduleinstance) {
        $chart = new \core\chart_bar();
        $chart->set_title(get_string('chart_wpm_comparison', 'mod_aireading'));

        $userstats = $this->statsmanager->get_user_statistics($aireadingid, $userid);
        $courseavg = $this->statsmanager->get_course_average_wpm($aireadingid);

        // Data series.
        $series = new \core\chart_series(
            get_string('reading_speed', 'mod_aireading'),
            [$moduleinstance->targetwpm, $userstats->bestwpm, $userstats->latestwpm, $courseavg]
        );

        $chart->add_series($series);

        // Labels.
        $chart->set_labels([
            get_string('target_wpm', 'mod_aireading'),
            get_string('your_best', 'mod_aireading'),
            get_string('your_latest', 'mod_aireading'),
            get_string('course_average', 'mod_aireading'),
        ]);

        // Configuration.
        $chart->set_horizontal(true);

        return $chart;
    }

    /**
     * Generate progress over attempts chart
     *
     * Line chart showing WPM, accuracy, and fluency across attempts
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @param bool $includepronunciation Include pronunciation line
     * @return \core\chart_line|null Line chart object or null if insufficient data
     */
    public function generate_progress_chart($aireadingid, $userid, $includepronunciation = false) {
        $attempts = $this->statsmanager->get_user_progress($aireadingid, $userid);

        if (count($attempts) < 2) {
            return null; // Need at least 2 attempts for trend.
        }

        $chart = new \core\chart_line();
        $chart->set_title(get_string('chart_progress', 'mod_aireading'));

        // Extract data.
        $labels = [];
        $wpmdata = [];
        $accuracydata = [];
        $fluencydata = [];
        $pronunciationdata = [];

        foreach ($attempts as $attempt) {
            $labels[] = get_string('attempt_n', 'mod_aireading', $attempt->attempt);
            $wpmdata[] = $attempt->wpm;
            $accuracydata[] = $attempt->accuracy_score;
            $fluencydata[] = $attempt->fluency_score;

            if ($includepronunciation && $attempt->pronunciation_score !== null) {
                $pronunciationdata[] = $attempt->pronunciation_score;
            }
        }

        // WPM series (scaled to 0-100 for comparison).
        $wpmseries = new \core\chart_series(
            get_string('wpm_label', 'mod_aireading'),
            $wpmdata
        );
        $chart->add_series($wpmseries);

        // Accuracy series.
        $accuracyseries = new \core\chart_series(
            get_string('accuracy_label', 'mod_aireading'),
            $accuracydata
        );
        $chart->add_series($accuracyseries);

        // Fluency series.
        $fluencyseries = new \core\chart_series(
            get_string('fluency_label', 'mod_aireading'),
            $fluencydata
        );
        $chart->add_series($fluencyseries);

        // Pronunciation series (if available).
        if ($includepronunciation && !empty($pronunciationdata)) {
            $pronunciationseries = new \core\chart_series(
                get_string('pronunciation_label', 'mod_aireading'),
                $pronunciationdata
            );
            $chart->add_series($pronunciationseries);
        }

        $chart->set_labels($labels);

        // Enable smooth lines.
        $chart->set_smooth(true);

        return $chart;
    }

    /**
     * Generate error type distribution chart
     *
     * Pie/donut chart showing breakdown of error types
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \core\chart_pie|null Pie chart object or null if no errors
     */
    public function generate_error_distribution_chart($aireadingid, $userid) {
        $errorstats = $this->statsmanager->get_error_statistics($aireadingid, $userid);

        if ($errorstats->totalerrors == 0) {
            return null; // No errors to display.
        }

        $chart = new \core\chart_pie();
        $chart->set_title(get_string('chart_error_distribution', 'mod_aireading'));

        // Prepare data.
        $labels = [];
        $data = [];

        if ($errorstats->accuracyerrors > 0) {
            $labels[] = get_string('accuracy_errors', 'mod_aireading');
            $data[] = $errorstats->accuracyerrors;
        }

        if ($errorstats->fluencyerrors > 0) {
            $labels[] = get_string('fluency_errors', 'mod_aireading');
            $data[] = $errorstats->fluencyerrors;
        }

        if ($errorstats->pronunciationerrors > 0) {
            $labels[] = get_string('pronunciation_errors', 'mod_aireading');
            $data[] = $errorstats->pronunciationerrors;
        }

        $series = new \core\chart_series(
            get_string('errors', 'mod_aireading'),
            $data
        );

        $chart->add_series($series);
        $chart->set_labels($labels);

        // Make it a donut chart.
        $chart->set_doughnut(true);

        return $chart;
    }

    /**
     * Generate pronunciation progress chart
     *
     * Shows average confidence score progression across attempts
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @param float $minconfidence Minimum confidence threshold
     * @return \core\chart_line|null Line chart or null if not available
     */
    public function generate_pronunciation_progress_chart($aireadingid, $userid, $minconfidence) {
        $trend = $this->statsmanager->get_pronunciation_trend($aireadingid, $userid);

        if ($trend === null || count($trend) < 2) {
            return null;
        }

        $chart = new \core\chart_line();
        $chart->set_title(get_string('chart_pronunciation_progress', 'mod_aireading'));

        // Extract data.
        $labels = [];
        $confidencedata = [];

        foreach ($trend as $point) {
            $labels[] = get_string('attempt_n', 'mod_aireading', $point['attempt']);
            $confidencedata[] = $point['confidence'];
        }

        // Confidence series.
        $confseries = new \core\chart_series(
            get_string('avg_confidence', 'mod_aireading'),
            $confidencedata
        );
        $chart->add_series($confseries);

        // Threshold line.
        $thresholddata = array_fill(0, count($labels), $minconfidence * 100);
        $thresholdseries = new \core\chart_series(
            get_string('required_threshold', 'mod_aireading'),
            $thresholddata
        );
        $chart->add_series($thresholdseries);

        $chart->set_labels($labels);
        $chart->set_smooth(true);

        return $chart;
    }

    /**
     * Generate improvement delta chart
     *
     * Bar chart showing improvement from first to last attempt
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @return \core\chart_bar|null Bar chart or null if insufficient data
     */
    public function generate_improvement_chart($aireadingid, $userid) {
        $userstats = $this->statsmanager->get_user_statistics($aireadingid, $userid);

        if (!$userstats->hasimprovement) {
            return null;
        }

        $chart = new \core\chart_bar();
        $chart->set_title(get_string('chart_improvement', 'mod_aireading'));

        // Data.
        $improvements = [
            $userstats->wpmimprovement,
            $userstats->accuracyimprovement,
            $userstats->fluencyimprovement,
        ];

        $labels = [
            get_string('wpm_label', 'mod_aireading'),
            get_string('accuracy_label', 'mod_aireading'),
            get_string('fluency_label', 'mod_aireading'),
        ];

        // Add pronunciation if available.
        if ($userstats->haspronunciation && isset($userstats->pronunciationimprovement)) {
            $improvements[] = $userstats->pronunciationimprovement;
            $labels[] = get_string('pronunciation_label', 'mod_aireading');
        }

        $series = new \core\chart_series(
            get_string('improvement', 'mod_aireading'),
            $improvements
        );

        $chart->add_series($series);
        $chart->set_labels($labels);

        return $chart;
    }

    /**
     * Generate all available charts for user
     *
     * @param int $aireadingid AI Reading activity ID
     * @param int $userid User ID
     * @param \stdClass $moduleinstance Module instance
     * @return array Array of chart objects with keys
     */
    public function generate_all_charts($aireadingid, $userid, $moduleinstance) {
        $charts = [];

        // WPM comparison (always available if user has attempts).
        if ($this->statsmanager->has_sufficient_attempts($aireadingid, $userid, 1)) {
            $charts['wpm_comparison'] = $this->generate_wpm_comparison_chart($aireadingid, $userid, $moduleinstance);
        }

        // Progress chart (needs at least 2 attempts).
        if ($this->statsmanager->has_sufficient_attempts($aireadingid, $userid, 2)) {
            $includepronunciation = (bool)$moduleinstance->enablepronunciation;
            $charts['progress'] = $this->generate_progress_chart($aireadingid, $userid, $includepronunciation);
        }

        // Error distribution.
        $errorchart = $this->generate_error_distribution_chart($aireadingid, $userid);
        if ($errorchart !== null) {
            $charts['error_distribution'] = $errorchart;
        }

        // Pronunciation progress (if enabled).
        if ($moduleinstance->enablepronunciation) {
            $pronunciationchart = $this->generate_pronunciation_progress_chart(
                $aireadingid,
                $userid,
                $moduleinstance->minconfidence
            );
            if ($pronunciationchart !== null) {
                $charts['pronunciation_progress'] = $pronunciationchart;
            }
        }

        // Improvement chart.
        $improvementchart = $this->generate_improvement_chart($aireadingid, $userid);
        if ($improvementchart !== null) {
            $charts['improvement'] = $improvementchart;
        }

        return $charts;
    }

    /**
     * Render chart to output
     *
     * @param \core\chart_base $chart Chart object
     * @return string HTML output
     */
    public function render_chart(\core\chart_base $chart) {
        global $OUTPUT;
        return $OUTPUT->render($chart);
    }
}
