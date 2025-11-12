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
 * Analysis engine for reading attempt evaluation
 *
 * This class performs comprehensive analysis of reading attempts by comparing
 * the original text with speech-to-text transcription. It calculates objective
 * metrics (WER, WPM) and heuristic scores (accuracy, fluency, pronunciation).
 *
 * The analysis separates objective measurements from pedagogical scoring:
 * - Objective metrics: Word Error Rate, Words per Minute, word counts
 * - Heuristic scores: Accuracy %, Fluency %, Pronunciation % (if enabled)
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Analysis engine class
 *
 * Analyzes reading attempts and generates comprehensive feedback.
 */
class analysis_engine {
    /**
     * Perform complete analysis of a reading attempt
     *
     * Main analysis method that coordinates all sub-analyses.
     *
     * @param string $originaltext The original reading text
     * @param array $sttdata STT data with 'text' and 'segments'
     * @param \stdClass $settings Analysis settings containing:
     *   - language: Language code (e.g., 'de', 'en')
     *   - targetwpm: Target words per minute
     *   - enablepronunciation: Whether pronunciation assessment is enabled
     *   - minconfidence: Minimum confidence threshold for pronunciation
     * @return array Analysis results with metrics, scores, errors, flags
     */
    public function analyze($originaltext, $sttdata, $settings) {
        // Normalize both texts.
        $normalizer = new text_normalizer();
        $originalwords = $normalizer->get_words($originaltext, $settings->language);
        $transcribedwords = $normalizer->get_words($sttdata['text'], $settings->language);

        // Sort segments by start time (safety measure).
        $segments = $sttdata['segments'];
        usort($segments, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        // Calculate objective metrics.
        $wer = $this->calculate_wer($originalwords, $transcribedwords);
        $wpm = $this->calculate_wpm($segments, count($originalwords));
        $duration = $this->calculate_duration($segments);

        // Detect errors.
        $accuracyerrors = $this->detect_accuracy_errors($originalwords, $transcribedwords, $segments);
        $fluencyerrors = $this->detect_fluency_errors($segments);

        $pronunciationerrors = [];
        $pronunciationunavailable = false;

        if ($settings->enablepronunciation) {
            // Check if confidence data is available.
            if ($this->has_confidence_data($segments)) {
                $pronunciationerrors = $this->detect_pronunciation_errors(
                    $segments,
                    $settings->minconfidence
                );
            } else {
                $pronunciationunavailable = true;
            }
        }

        // Combine all errors.
        $allerrors = array_merge($accuracyerrors, $fluencyerrors, $pronunciationerrors);

        // Calculate heuristic scores.
        $scores = $this->calculate_scores(
            $allerrors,
            $wpm,
            $settings->targetwpm,
            count($originalwords),
            $settings->enablepronunciation && !$pronunciationunavailable
        );

        // Generate JSON structure.
        return $this->generate_analysis_json(
            $wer,
            $wpm,
            $duration,
            count($originalwords),
            count($transcribedwords),
            $scores,
            $allerrors,
            $pronunciationunavailable
        );
    }

    /**
     * Calculate Word Error Rate (WER)
     *
     * WER is an objective metric based on Levenshtein distance at word level.
     * Formula: (Substitutions + Deletions + Insertions) / Total words in reference
     *
     * @param array $originalwords Array of reference words
     * @param array $transcribedwords Array of transcribed words
     * @return float WER as decimal (e.g., 0.12 for 12%)
     */
    private function calculate_wer($originalwords, $transcribedwords) {
        $refcount = count($originalwords);
        if ($refcount === 0) {
            return 0.0;
        }

        $hypcount = count($transcribedwords);

        // Calculate Levenshtein distance.
        $distance = $this->levenshtein_distance($originalwords, $transcribedwords);

        // WER = distance / reference length.
        $wer = $distance / $refcount;

        return round($wer, 4);
    }

    /**
     * Calculate Levenshtein distance between two word arrays
     *
     * @param array $ref Reference words
     * @param array $hyp Hypothesis words
     * @return int Edit distance
     */
    private function levenshtein_distance($ref, $hyp) {
        $reflen = count($ref);
        $hyplen = count($hyp);

        // Initialize matrix.
        $matrix = [];
        for ($i = 0; $i <= $reflen; $i++) {
            $matrix[$i] = [];
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $hyplen; $j++) {
            $matrix[0][$j] = $j;
        }

        // Fill matrix.
        for ($i = 1; $i <= $reflen; $i++) {
            for ($j = 1; $j <= $hyplen; $j++) {
                $cost = ($ref[$i - 1] === $hyp[$j - 1]) ? 0 : 1;

                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1, // Deletion.
                    $matrix[$i][$j - 1] + 1, // Insertion.
                    $matrix[$i - 1][$j - 1] + $cost  // Substitution.
                );
            }
        }

        return $matrix[$reflen][$hyplen];
    }

    /**
     * Calculate Words Per Minute (WPM)
     *
     * Objective metric based on actual speech duration (from segments).
     * Formula: (word count / duration in seconds) * 60
     *
     * @param array $segments STT segments with start/end times
     * @param int $wordcount Total word count
     * @return float WPM value
     */
    private function calculate_wpm($segments, $wordcount) {
        if (empty($segments) || $wordcount === 0) {
            return 0.0;
        }

        $duration = $this->calculate_duration($segments);

        if ($duration <= 0) {
            return 0.0;
        }

        $wpm = ($wordcount / $duration) * 60;

        return round($wpm, 2);
    }

    /**
     * Calculate actual speech duration from segments
     *
     * @param array $segments STT segments
     * @return float Duration in seconds
     */
    private function calculate_duration($segments) {
        if (empty($segments)) {
            return 0.0;
        }

        $firststart = $segments[0]['start'];
        $lastend = end($segments)['end'];

        return max(0, $lastend - $firststart);
    }

    /**
     * Detect accuracy errors by comparing original and transcribed words
     *
     * Identifies substitutions, omissions, and insertions using
     * Levenshtein alignment.
     *
     * @param array $originalwords Reference words
     * @param array $transcribedwords Hypothesis words
     * @param array $segments STT segments for timestamps
     * @return array Array of accuracy error objects
     */
    private function detect_accuracy_errors($originalwords, $transcribedwords, $segments) {
        $errors = [];

        // Get alignment from Levenshtein backtracking.
        $alignment = $this->align_words($originalwords, $transcribedwords);
        foreach ($alignment as $align) {
            if ($align['type'] === 'match') {
                continue; // No error.
            }

            // Map alignment types to error types for rendering.
            // 'deletion' → 'omission', others keep their name.
            $errortype = $align['type'];
            if ($errortype === 'deletion') {
                $errortype = 'omission';
            }

            $error = [
                'type' => $errortype, // Direct type: substitution, omission, insertion.
                'position' => $align['refpos'],
                'expected' => $align['refword'] ?? '',
                'actual' => $align['hypword'] ?? '',
            ];

            // Add timestamp if available.
            if (isset($align['hyppos']) && isset($segments[$align['hyppos']])) {
                $error['timestamp'] = $segments[$align['hyppos']]['start'];
            }

            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * Align words using Levenshtein backtracking
     *
     * @param array $ref Reference words
     * @param array $hyp Hypothesis words
     * @return array Alignment with type (match|substitution|deletion|insertion)
     */
    private function align_words($ref, $hyp) {
        $reflen = count($ref);
        $hyplen = count($hyp);

        // Build distance matrix.
        $matrix = [];
        for ($i = 0; $i <= $reflen; $i++) {
            $matrix[$i] = [];
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $hyplen; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $reflen; $i++) {
            for ($j = 1; $j <= $hyplen; $j++) {
                $cost = ($ref[$i - 1] === $hyp[$j - 1]) ? 0 : 1;

                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        // Backtrack to get alignment.
        $alignment = [];
        $i = $reflen;
        $j = $hyplen;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0) {
                $cost = ($ref[$i - 1] === $hyp[$j - 1]) ? 0 : 1;

                if ($matrix[$i][$j] === $matrix[$i - 1][$j - 1] + $cost) {
                    // Match or substitution.
                    $type = ($cost === 0) ? 'match' : 'substitution';
                    $alignment[] = [
                        'type' => $type,
                        'refpos' => $i - 1,
                        'hyppos' => $j - 1,
                        'refword' => $ref[$i - 1],
                        'hypword' => $hyp[$j - 1],
                    ];
                    $i--;
                    $j--;
                    continue;
                }
            }

            if ($i > 0 && ($j === 0 || $matrix[$i][$j] === $matrix[$i - 1][$j] + 1)) {
                // Deletion (omission).
                $alignment[] = [
                    'type' => 'deletion',
                    'refpos' => $i - 1,
                    'refword' => $ref[$i - 1],
                ];
                $i--;
            } else if ($j > 0) {
                // Insertion.
                $alignment[] = [
                    'type' => 'insertion',
                    'hyppos' => $j - 1,
                    'hypword' => $hyp[$j - 1],
                    'refpos' => $i, // Position where inserted.
                ];
                $j--;
            }
        }

        return array_reverse($alignment);
    }

    /**
     * Detect pronunciation errors based on confidence scores
     *
     * Evaluates ALL words (not just focus words) based on STT confidence.
     * Three categories:
     * - Good: confidence >= minconfidence (no marking)
     * - OK: minconfidence > confidence >= minconfidence - 0.1 (yellow)
     * - Bad: confidence < minconfidence - 0.1 (red)
     *
     * @param array $segments STT segments with confidence scores
     * @param float $minconfidence Minimum confidence threshold
     * @return array Array of pronunciation error objects
     */
    private function detect_pronunciation_errors($segments, $minconfidence) {
        $errors = [];

        foreach ($segments as $position => $segment) {
            $confidence = $segment['confidence'] ?? 1.0;

            // Determine severity.
            if ($confidence < $minconfidence - 0.1) {
                $severity = 'bad';
            } else if ($confidence < $minconfidence) {
                $severity = 'ok';
            } else {
                continue; // Good - no marking.
            }

            $errors[] = [
                'type' => 'pronunciation',
                'severity' => $severity,
                'position' => $position,
                'word' => $segment['word'],
                'confidence' => round($confidence, 2),
                'timestamp' => $segment['start'],
            ];
        }

        return $errors;
    }

    /**
     * Check if segments contain confidence data
     *
     * @param array $segments STT segments
     * @return bool True if confidence data is available
     */
    private function has_confidence_data($segments) {
        if (empty($segments)) {
            return false;
        }

        // Check first segment.
        return isset($segments[0]['confidence']);
    }

    /**
     * Detect fluency errors (pauses and hesitations)
     *
     * Heuristic detection based on timing gaps and word patterns.
     *
     * Pauses: Gap > 0.5s between word end and next word start
     * Hesitations: Repeated word fragments (w...w...word pattern)
     *
     * @param array $segments STT segments
     * @return array Array of fluency error objects
     */
    private function detect_fluency_errors($segments) {
        $errors = [];

        // Detect pauses (gaps between words).
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $current = $segments[$i];
            $next = $segments[$i + 1];

            $gap = $next['start'] - $current['end'];

            if ($gap > 0.5) { // 0.5 second threshold.
                $errors[] = [
                    'type' => 'pause', // Direct type for rendering.
                    'position' => $i,
                    'word' => $current['word'],
                    'duration' => round($gap, 2),
                    'timestamp' => $current['end'],
                ];
            }
        }

        // Detect hesitations (word fragments/repetitions).
        // Pattern: Look for ellipsis or very short segments followed by similar word.
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $current = $segments[$i];
            $next = $segments[$i + 1];

            // Check for ellipsis or very short word.
            if (
                strpos($current['word'], '...') !== false ||
                ($current['end'] - $current['start']) < 0.2
            ) {
                // Check if next word starts similarly.
                $currword = str_replace('...', '', $current['word']);
                if (
                    strlen($currword) > 0 &&
                    stripos($next['word'], substr($currword, 0, min(3, strlen($currword)))) === 0
                ) {
                    $errors[] = [
                        'type' => 'hesitation', // Direct type for rendering.
                        'position' => $i,
                        'word' => $next['word'],
                        'fragment' => $current['word'],
                        'timestamp' => $current['start'],
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Calculate heuristic scores from errors and metrics
     *
     * Generates pedagogical scores (0-100) based on objective data:
     * - Accuracy: Based on WER
     * - Fluency: Based on pauses and hesitations
     * - Pronunciation: Based on confidence issues (if enabled)
     * - Grade: Weighted average with WPM bonus
     *
     * @param array $errors All detected errors
     * @param float $wpm Calculated words per minute
     * @param int $targetwpm Target WPM setting
     * @param int $totalwords Total word count
     * @param bool $haspronunciation Whether pronunciation scoring is included
     * @return array Scores array with accuracy, fluency, pronunciation, grade
     */
    private function calculate_scores($errors, $wpm, $targetwpm, $totalwords, $haspronunciation) {
        // Count errors by type.
        $accuracyerrorcount = 0;
        $fluencyerrorcount = 0;
        $pronunciationbadcount = 0;
        $pronunciationokcount = 0;

        foreach ($errors as $error) {
            $errortype = $error['type'];

            // Count accuracy errors (substitution, omission, insertion).
            if (in_array($errortype, ['substitution', 'omission', 'insertion'])) {
                $accuracyerrorcount++;
            } else if (in_array($errortype, ['pause', 'hesitation'])) {
                // Count fluency errors.
                $fluencyerrorcount++;
            } else if ($errortype === 'pronunciation') {
                // Count pronunciation errors by severity.
                if ($error['severity'] === 'bad') {
                    $pronunciationbadcount++;
                } else if ($error['severity'] === 'ok') {
                    $pronunciationokcount++;
                }
            }
        }

        // Accuracy score: 100 - (error% * 100).
        $accuracyscore = max(0, 100 - (($accuracyerrorcount / max(1, $totalwords)) * 100));

        // Fluency score: Deduct 5 points per fluency error, minimum 0.
        $fluencyscore = max(0, 100 - ($fluencyerrorcount * 5));

        // Pronunciation score (if enabled).
        $pronunciationscore = null;
        if ($haspronunciation) {
            // Bad errors: -10 points each, OK errors: -5 points each.
            $pronunciationdeduction = ($pronunciationbadcount * 10) + ($pronunciationokcount * 5);
            $pronunciationscore = max(0, 100 - $pronunciationdeduction);
        }

        // Calculate weighted grade.
        $grade = $this->calculate_final_grade(
            $accuracyscore,
            $fluencyscore,
            $pronunciationscore,
            $wpm,
            $targetwpm
        );

        return [
            'accuracy' => round($accuracyscore, 2),
            'fluency' => round($fluencyscore, 2),
            'pronunciation' => $pronunciationscore !== null ? round($pronunciationscore, 2) : null,
            'grade' => round($grade, 2),
        ];
    }

    /**
     * Calculate final grade with weighting and WPM bonus
     *
     * Weights:
     * - If pronunciation enabled: Accuracy 40%, Fluency 30%, Pronunciation 30%
     * - If pronunciation disabled: Accuracy 60%, Fluency 40%
     *
     * WPM Bonus/Penalty:
     * - >= target WPM: +5% bonus (max 100)
     * - < target WPM: Linear penalty down to -10% at 50% of target
     *
     * @param float $accuracy Accuracy score
     * @param float $fluency Fluency score
     * @param float|null $pronunciation Pronunciation score (null if disabled)
     * @param float $wpm Actual WPM
     * @param int $targetwpm Target WPM
     * @return float Final grade (0-100)
     */
    private function calculate_final_grade($accuracy, $fluency, $pronunciation, $wpm, $targetwpm) {
        // Base grade from weighted scores.
        if ($pronunciation !== null) {
            $basegrade = ($accuracy * 0.4) + ($fluency * 0.3) + ($pronunciation * 0.3);
        } else {
            $basegrade = ($accuracy * 0.6) + ($fluency * 0.4);
        }

        // WPM adjustment.
        $wpmratio = $targetwpm > 0 ? ($wpm / $targetwpm) : 1.0;

        if ($wpmratio >= 1.0) {
            // At or above target: +5% bonus.
            $wpmbonus = 5;
        } else if ($wpmratio >= 0.5) {
            // 50-100% of target: Linear scale from -10 to 0.
            $wpmbonus = -10 + ($wpmratio - 0.5) * 20;
        } else {
            // Below 50%: -10% penalty.
            $wpmbonus = -10;
        }

        $finalgrade = $basegrade + $wpmbonus;

        return max(0, min(100, $finalgrade));
    }

    /**
     * Generate complete analysis JSON structure
     *
     * Creates the final JSON object with strict separation:
     * - metrics: Objective measurements (WER, WPM, counts)
     * - scores: Heuristic pedagogical scores
     * - errors: Detailed error list
     * - flags: Analysis metadata
     *
     * @param float $wer Word Error Rate
     * @param float $wpm Words per minute
     * @param float $duration Speech duration
     * @param int $originalcount Original word count
     * @param int $transcribedcount Transcribed word count
     * @param array $scores Score array
     * @param array $errors Error array
     * @param bool $pronunciationunavailable Flag for missing confidence data
     * @return array Complete analysis structure
     */
    private function generate_analysis_json(
        $wer,
        $wpm,
        $duration,
        $originalcount,
        $transcribedcount,
        $scores,
        $errors,
        $pronunciationunavailable
    ) {
        return [
            'metrics' => [
                'wer' => $wer,
                'wpm' => $wpm,
                'duration' => round($duration, 2),
                'wordcount_original' => $originalcount,
                'wordcount_transcribed' => $transcribedcount,
            ],
            'scores' => [
                'accuracy' => $scores['accuracy'],
                'fluency' => $scores['fluency'],
                'pronunciation' => $scores['pronunciation'],
                'grade' => $scores['grade'],
            ],
            'errors' => $errors,
            'flags' => [
                'pronunciation_unavailable' => $pronunciationunavailable,
                'incomplete_transcription' => $transcribedcount === 0,
            ],
        ];
    }
}
