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
 * Text normalization utilities for reading analysis
 *
 * This class provides methods to normalize text for comparison between
 * original reading text and speech-to-text transcriptions. Normalization
 * includes handling of punctuation, case, and language-specific characters.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Text normalizer class for reading analysis
 *
 * Provides static methods to normalize text for accurate comparison.
 */
class text_normalizer {
    /**
     * Normalize text for comparison
     *
     * Applies all normalization steps:
     * - Convert to lowercase
     * - Remove punctuation
     * - Normalize whitespace
     * - Handle language-specific characters (umlauts, etc.)
     *
     * @param string $text Input text
     * @param string $language Language code (e.g., 'de', 'en')
     * @return string Normalized text
     */
    public static function normalize($text, $language = 'en') {
        // Convert to lowercase.
        $text = self::lowercase($text);

        // Handle language-specific characters.
        $text = self::normalize_language_chars($text, $language);

        // Remove punctuation.
        $text = self::remove_punctuation($text);

        // Normalize whitespace.
        $text = self::normalize_whitespace($text);

        return $text;
    }

    /**
     * Convert text to lowercase
     *
     * Uses multibyte-safe lowercase conversion.
     *
     * @param string $text Input text
     * @return string Lowercased text
     */
    public static function lowercase($text) {
        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Remove all punctuation from text
     *
     * Removes common punctuation marks including:
     * - Periods, commas, semicolons, colons
     * - Question marks, exclamation marks
     * - Quotes (single and double)
     * - Brackets, parentheses
     * - Hyphens, dashes
     *
     * @param string $text Input text
     * @return string Text without punctuation
     */
    public static function remove_punctuation($text) {
        // Remove common punctuation marks.
        $punctuation = [
            '.', ',', ';', ':', '!', '?',
            '"', "'", '`', '´',
            '(', ')', '[', ']', '{', '}',
            '-', '–', '—', '_',
            '/', '\\',
        ];

        $text = str_replace($punctuation, '', $text);

        // Remove any remaining non-word characters except spaces.
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        return $text;
    }

    /**
     * Normalize whitespace
     *
     * - Converts all whitespace types to single space
     * - Removes leading/trailing whitespace
     * - Collapses multiple spaces to single space
     *
     * @param string $text Input text
     * @return string Text with normalized whitespace
     */
    public static function normalize_whitespace($text) {
        // Convert all whitespace types to single space.
        $text = preg_replace('/\s+/u', ' ', $text);

        // Trim leading/trailing whitespace.
        $text = trim($text);

        return $text;
    }

    /**
     * Normalize language-specific characters
     *
     * Handles language-specific character normalization:
     * - German: ä→ae, ö→oe, ü→ue, ß→ss, Ä→ae, Ö→oe, Ü→ue
     * - French: Remove accents (é→e, è→e, ê→e, etc.)
     * - Other languages can be added as needed
     *
     * @param string $text Input text
     * @param string $language Language code
     * @return string Text with normalized characters
     */
    public static function normalize_language_chars($text, $language) {
        switch ($language) {
            case 'de':
                return self::normalize_german_chars($text);
            case 'fr':
                return self::normalize_french_chars($text);
            default:
                return $text;
        }
    }

    /**
     * Normalize German characters
     *
     * Converts German umlauts and ß to their ASCII equivalents:
     * - ä, Ä → ae
     * - ö, Ö → oe
     * - ü, Ü → ue
     * - ß → ss
     *
     * @param string $text Input text
     * @return string Text with normalized German characters
     */
    private static function normalize_german_chars($text) {
        $replacements = [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            'Ä' => 'ae',
            'Ö' => 'oe',
            'Ü' => 'ue',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Normalize French characters
     *
     * Removes accents from French characters:
     * - é, è, ê, ë → e
     * - à, â → a
     * - ô → o
     * - ù, û, ü → u
     * - ç → c
     * - î, ï → i
     *
     * @param string $text Input text
     * @return string Text with normalized French characters
     */
    private static function normalize_french_chars($text) {
        $replacements = [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a',
            'ô' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            'î' => 'i', 'ï' => 'i',
            // Uppercase variants.
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'À' => 'a', 'Â' => 'a',
            'Ô' => 'o',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c',
            'Î' => 'i', 'Ï' => 'i',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Split text into words array
     *
     * Normalizes text and returns array of individual words.
     *
     * @param string $text Input text
     * @param string $language Language code
     * @return array Array of normalized words
     */
    public static function get_words($text, $language = 'en') {
        $normalized = self::normalize($text, $language);
        $words = explode(' ', $normalized);

        // Filter out empty strings.
        return array_values(array_filter($words, function ($word) {
            return $word !== '';
        }));
    }

    /**
     * Count words in text
     *
     * Returns the number of words after normalization.
     *
     * @param string $text Input text
     * @param string $language Language code
     * @return int Number of words
     */
    public static function count_words($text, $language = 'en') {
        return count(self::get_words($text, $language));
    }
}
