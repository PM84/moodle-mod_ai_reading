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
 * Annotated text generator for AI Reading Trainer
 *
 * Generates HTML with span-based error markers for reading feedback
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Generates annotated text with error markers
 *
 * Creates HTML representation of reading text with visual markers for:
 * - Accuracy errors (substitution, omission, insertion)
 * - Fluency issues (pauses, hesitation)
 * - Pronunciation problems (confidence-based)
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class annotated_text {
    /** @var array Error type priorities for CSS class selection */
    const ERROR_PRIORITIES = [
        'substitution' => 1,
        'omission' => 2,
        'insertion' => 3,
        'pronunciation_bad' => 4,
        'pronunciation_ok' => 5,
        'pause' => 6,
        'hesitation' => 7,
    ];

    /**
     * Generate annotated HTML from original text and analysis data
     *
     * @param string $originaltext Original reading text
     * @param array $analysisdata Analysis data containing errors
     * @param bool $beginnermode Simplified view for beginners
     * @return string HTML with annotated spans
     */
    public function generate($originaltext, $analysisdata, $beginnermode = false) {
        // Normalize text for processing.
        $normalizer = new text_normalizer();
        $normalizedtext = $normalizer->normalize($originaltext);
        $words = explode(' ', $normalizedtext);

        // Extract errors from analysis data.
        $errors = isset($analysisdata['errors']) ? $analysisdata['errors'] : [];

        // Build word-to-error mapping.
        $wordmap = $this->build_word_error_map($words, $errors);

        // Generate HTML.
        $html = '<div class="ai-reading-annotated-text" role="article" aria-label="' .
                get_string('annotatedtext', 'mod_aireading') . '">';

        foreach ($words as $index => $word) {
            $html .= $this->render_word($word, $index, $wordmap, $beginnermode);
            $html .= ' '; // Space between words.
        }

        $html .= '</div>';

        // Add legend if not in beginner mode.
        if (!$beginnermode) {
            $html .= $this->render_legend($analysisdata);
        }

        return $html;
    }

    /**
     * Build mapping of word positions to errors
     *
     * @param array $words Array of words from normalized text
     * @param array $errors Array of error objects
     * @return array Map of word index to error details
     */
    private function build_word_error_map($words, $errors) {
        $map = [];

        foreach ($errors as $error) {
            if (!isset($error['position'])) {
                continue;
            }

            $position = $error['position'];

            // Initialize position in map if not exists.
            if (!isset($map[$position])) {
                $map[$position] = [];
            }

            // Add error to this position.
            $map[$position][] = $error;
        }

        // Sort errors by priority for each position.
        foreach ($map as $position => $positionerrors) {
            usort($map[$position], function ($a, $b) {
                $prioritya = self::ERROR_PRIORITIES[$a['type']] ?? 99;
                $priorityb = self::ERROR_PRIORITIES[$b['type']] ?? 99;
                return $prioritya - $priorityb;
            });
        }

        return $map;
    }

    /**
     * Render a single word with appropriate error markers
     *
     * @param string $word The word to render
     * @param int $index Word position in text
     * @param array $wordmap Word-to-error mapping
     * @param bool $beginnermode Simplified view
     * @return string HTML span element
     */
    private function render_word($word, $index, $wordmap, $beginnermode) {
        $classes = ['word'];
        $attributes = [
            'data-position' => $index,
        ];

        // Check if word has errors.
        if (isset($wordmap[$index]) && !empty($wordmap[$index])) {
            $primaryerror = $wordmap[$index][0]; // Highest priority error.

            // Add error-specific class.
            $errortype = $primaryerror['type'];
            $classes[] = 'error-' . str_replace('_', '-', $errortype);

            // Add data attributes for tooltip.
            $attributes['data-error-type'] = $errortype;
            $attributes['tabindex'] = '0';
            $attributes['role'] = 'button';
            $attributes['aria-label'] = get_string('error_' . $errortype, 'mod_aireading');

            // Type-specific attributes.
            switch ($errortype) {
                case 'substitution':
                    $attributes['data-expected'] = isset($primaryerror['expected']) ? $primaryerror['expected'] : '';
                    $attributes['data-actual'] = isset($primaryerror['actual']) ? $primaryerror['actual'] : '';
                    break;

                case 'omission':
                    $attributes['data-omitted'] = isset($primaryerror['expected']) ? $primaryerror['expected'] : '';
                    break;

                case 'pause':
                    $attributes['data-duration'] = isset($primaryerror['duration']) ? $primaryerror['duration'] : '';
                    break;

                case 'hesitation':
                    $attributes['data-fragment'] = isset($primaryerror['fragment']) ? $primaryerror['fragment'] : '';
                    break;

                case 'pronunciation_bad':
                case 'pronunciation_ok':
                    $confidence = isset($primaryerror['confidence']) ? $primaryerror['confidence'] : 0;
                    $attributes['data-confidence'] = round($confidence * 100, 1);
                    break;
            }

            // Add all errors as JSON for detailed tooltip.
            if (!$beginnermode) {
                $attributes['data-errors'] = json_encode($wordmap[$index]);
            }
        } else {
            $classes[] = 'correct';
        }

        // Build span element.
        $classstr = implode(' ', $classes);
        $attrstr = '';
        foreach ($attributes as $key => $value) {
            $attrstr .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }

        return '<span class="' . $classstr . '"' . $attrstr . '>' . htmlspecialchars($word) . '</span>';
    }

    /**
     * Render legend for error markers
     *
     * @param array $analysisdata Analysis data for context
     * @return string HTML legend
     */
    private function render_legend($analysisdata) {
        $html = '<div class="ai-reading-legend" role="complementary" aria-label="' .
                get_string('errorlegend', 'mod_aireading') . '">';
        $html .= '<h4>' . get_string('errorlegend', 'mod_aireading') . '</h4>';
        $html .= '<ul class="legend-items">';

        // Accuracy errors.
        $html .= '<li><span class="legend-marker error-substitution"></span> ' .
                 get_string('legend_substitution', 'mod_aireading') . '</li>';
        $html .= '<li><span class="legend-marker error-omission"></span> ' .
                 get_string('legend_omission', 'mod_aireading') . '</li>';

        // Fluency errors.
        $html .= '<li><span class="legend-marker error-pause"></span> ' .
                 get_string('legend_pause', 'mod_aireading') . '</li>';
        $html .= '<li><span class="legend-marker error-hesitation"></span> ' .
                 get_string('legend_hesitation', 'mod_aireading') . '</li>';

        // Pronunciation errors (if present in analysis).
        if (isset($analysisdata['heuristics']['pronunciation'])) {
            $html .= '<li><span class="legend-marker error-pronunciation-bad"></span> ' .
                     get_string('legend_pronunciation_bad', 'mod_aireading') . '</li>';
            $html .= '<li><span class="legend-marker error-pronunciation-ok"></span> ' .
                     get_string('legend_pronunciation_ok', 'mod_aireading') . '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
