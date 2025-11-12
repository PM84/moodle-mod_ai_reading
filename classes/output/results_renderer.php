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
 * Results renderer for AI Reading Trainer
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading\output;

use plugin_renderer_base;
use renderable;
use moodle_url;
use html_writer;

/**
 * Renderer for reading attempt results
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results_renderer extends plugin_renderer_base {
    /**
     * Render attempt details view
     *
     * @param \stdClass $attempt Attempt record with analysis data
     * @param \stdClass $moduleinstance Module instance
     * @param \context_module $context Module context
     * @param bool $beginnermode Whether to show simplified view for beginners
     * @return string HTML output
     */
    public function render_attempt_details($attempt, $moduleinstance, $context, $beginnermode = false) {
        global $DB;

        $data = new \stdClass();
        $data->attemptid = $attempt->id;
        $data->attemptnumber = $attempt->attempt;
        $data->timestarted = userdate($attempt->timestarted);
        $data->timefinished = userdate($attempt->timefinished);
        $data->duration = format_time($attempt->duration);
        $data->beginnermode = $beginnermode;

        // Parse analysis data.
        $analysisdata = json_decode($attempt->analysis_data, true);
        if (!$analysisdata) {
            $analysisdata = [];
        }

        // Basic metrics.
        $data->wpm = $attempt->wpm ? round($attempt->wpm, 1) : 0;
        $data->targetwpm = $moduleinstance->targetwpm;
        $data->accuracy = $attempt->accuracy_score ? round($attempt->accuracy_score, 1) : 0;
        $data->fluency = $attempt->fluency_score ? round($attempt->fluency_score, 1) : 0;
        $data->grade = $attempt->grade ? round($attempt->grade, 1) : 0;

        // Pronunciation (if enabled).
        $data->haspronunciation = $moduleinstance->enablepronunciation && $attempt->pronunciation_score !== null;
        if ($data->haspronunciation) {
            $data->pronunciation = round($attempt->pronunciation_score, 1);
        }

        // Annotated text with errors.
        $data->annotatedtext = $this->render_annotated_text($moduleinstance->readingtext, $analysisdata, $beginnermode);

        // Audio player.
        $data->audioplayer = $this->render_audio_player($attempt->audiofileid, $context);

        // Statistics.
        $data->statistics = $this->render_statistics($attempt, $moduleinstance, $analysisdata, $beginnermode);

        // Pronunciation statistics panel (if enabled).
        if ($data->haspronunciation) {
            $data->pronunciationstats = $this->render_pronunciation_stats($analysisdata, $moduleinstance);
        }

        // Teacher feedback (if present).
        if (!empty($attempt->teacherfeedback)) {
            $data->teacherfeedback = format_text($attempt->teacherfeedback, FORMAT_HTML, ['context' => $context]);
        }

        return $this->render_from_template('mod_aireading/attempt_results', $data);
    }

    /**
     * Render annotated text with error markers
     *
     * @param string $originaltext Original reading text
     * @param array $analysisdata Analysis data containing errors
     * @param bool $beginnermode Simplified view for beginners
     * @return string HTML output
     */
    public function render_annotated_text($originaltext, $analysisdata, $beginnermode = false) {
        $annotatedtext = new \mod_aireading\annotated_text();
        return $annotatedtext->generate($originaltext, $analysisdata, $beginnermode);
    }

    /**
     * Render audio player for attempt
     *
     * @param int $audiofileid File ID from file storage
     * @param \context_module $context Module context
     * @return string HTML output
     */
    public function render_audio_player($audiofileid, $context) {
        if (!$audiofileid) {
            return html_writer::div(
                get_string('noaudioavailable', 'mod_aireading'),
                'alert alert-info'
            );
        }

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($audiofileid);

        if (!$file) {
            return html_writer::div(
                get_string('audiofilenotfound', 'mod_aireading'),
                'alert alert-warning'
            );
        }

        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_aireading',
            'attemptaudio',
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        $audioplayer = html_writer::start_tag('audio', [
            'controls' => 'controls',
            'class' => 'ai-reading-audio-player',
            'id' => 'attempt-audio-player',
        ]);
        $audioplayer .= html_writer::empty_tag('source', [
            'src' => $url->out(),
            'type' => $file->get_mimetype(),
        ]);
        $audioplayer .= get_string('audionotsupported', 'mod_aireading');
        $audioplayer .= html_writer::end_tag('audio');

        return $audioplayer;
    }

    /**
     * Render statistics overview
     *
     * @param \stdClass $attempt Attempt record
     * @param \stdClass $moduleinstance Module instance
     * @param array $analysisdata Analysis data
     * @param bool $beginnermode Simplified view
     * @return string HTML output
     */
    public function render_statistics($attempt, $moduleinstance, $analysisdata, $beginnermode = false) {
        $data = new \stdClass();

        // Word counts.
        $data->wordcount_original = $attempt->wordcount_original;
        $data->wordcount_transcribed = $attempt->wordcount_transcribed;

        // WPM comparison.
        $data->wpm = round($attempt->wpm, 1);
        $data->targetwpm = $moduleinstance->targetwpm;
        $data->wpm_percentage = $moduleinstance->targetwpm > 0
            ? round(($attempt->wpm / $moduleinstance->targetwpm) * 100, 1)
            : 0;

        // Error counts (only in advanced mode).
        if (!$beginnermode && isset($analysisdata['errors'])) {
            $errors = $analysisdata['errors'];
            $data->showdetails = true;

            // Count by type.
            $data->accuracy_errors = count(array_filter($errors, function ($e) {
                return isset($e['type']) && in_array($e['type'], ['substitution', 'omission', 'insertion']);
            }));

            $data->fluency_errors = count(array_filter($errors, function ($e) {
                return isset($e['type']) && in_array($e['type'], ['pause', 'hesitation']);
            }));

            if ($moduleinstance->enablepronunciation) {
                $data->pronunciation_errors = count(array_filter($errors, function ($e) {
                    return isset($e['type']) && in_array($e['type'], ['pronunciation_bad', 'pronunciation_ok']);
                }));
            }
        }

        // Metrics (objective data).
        if (isset($analysisdata['metrics'])) {
            $data->wer = isset($analysisdata['metrics']['wer'])
                ? round($analysisdata['metrics']['wer'] * 100, 1)
                : 0;
        }

        return $this->render_from_template('mod_aireading/statistics', $data);
    }

    /**
     * Render pronunciation statistics panel
     *
     * @param array $analysisdata Analysis data
     * @param \stdClass $moduleinstance Module instance
     * @return string HTML output
     */
    public function render_pronunciation_stats($analysisdata, $moduleinstance) {
        if (!isset($analysisdata['heuristics']['pronunciation'])) {
            return '';
        }

        $pronunciationdata = $analysisdata['heuristics']['pronunciation'];
        $data = new \stdClass();

        // Overall stats.
        $data->avgconfidence = isset($pronunciationdata['avg_confidence'])
            ? round($pronunciationdata['avg_confidence'] * 100, 1)
            : 0;
        $data->minconfidence = round($moduleinstance->minconfidence * 100, 1);

        // Word-level breakdown.
        if (isset($pronunciationdata['word_scores']) && !empty($pronunciationdata['word_scores'])) {
            $data->has_word_scores = true;

            $wordscores = $pronunciationdata['word_scores'];
            arsort($wordscores); // Sort by confidence descending.

            // Get top 5 worst pronunciations.
            $worst = array_slice($wordscores, -5, 5, true);
            $data->worst_words = [];
            foreach ($worst as $word => $confidence) {
                $data->worst_words[] = [
                    'word' => s($word),
                    'confidence' => round($confidence * 100, 1),
                    'class' => $confidence < ($moduleinstance->minconfidence - 0.1) ? 'bad' : 'ok',
                ];
            }

            // Get top 5 best pronunciations.
            $best = array_slice($wordscores, 0, 5, true);
            $data->best_words = [];
            foreach ($best as $word => $confidence) {
                $data->best_words[] = [
                    'word' => s($word),
                    'confidence' => round($confidence * 100, 1),
                ];
            }
        }

        // Focus words (words that need practice).
        if (isset($pronunciationdata['focus_words']) && !empty($pronunciationdata['focus_words'])) {
            $data->has_focus_words = true;
            $data->focus_words = array_map(function ($word) {
                return ['word' => s($word)];
            }, $pronunciationdata['focus_words']);
        }

        return $this->render_from_template('mod_aireading/pronunciation_stats', $data);
    }
}
