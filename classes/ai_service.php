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
 * AI Service wrapper for STT integration with local_ai_manager
 *
 * This class handles all communication with the AI Manager plugin for
 * speech-to-text transcription. It implements dependency injection to
 * allow for testing with mock connectors.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * AI Service class for STT integration
 *
 * Provides methods to transcribe audio files using the local_ai_manager
 * plugin with dependency injection support for testability.
 */
class ai_service {
    /** @var \local_ai_manager\base_connector AI connector instance */
    private $aiconnector;

    /**
     * Constructor with Dependency Injection for testability
     *
     * In production, the connector is automatically fetched from AI Manager.
     * In testing, a mock connector can be injected.
     *
     * @param \local_ai_manager\base_connector|null $connector Optional connector for testing
     * @throws \moodle_exception If AI Manager is not available in production
     */
    public function __construct($connector = null) {
        if ($connector === null) {
            // Production: Get real connector from AI Manager.
            if (!class_exists('\\local_ai_manager\\manager')) {
                throw new \moodle_exception('ai_manager_not_available', 'mod_aireading');
            }
            try {
                $this->aiconnector = \local_ai_manager\manager::get_connector('whisper');
            } catch (\Exception $e) {
                throw new \moodle_exception(
                    'whisper_connector_not_available',
                    'mod_aireading',
                    '',
                    null,
                    $e->getMessage()
                );
            }
        } else {
            // Testing: Use injected mock connector.
            $this->aiconnector = $connector;
        }
    }

    /**
     * Transcribe audio file to text with word-level timestamps and confidence
     *
     * @param string $filepath Absolute path to audio file
     * @param string $language Language code (e.g., 'de', 'en')
     * @return array Parsed STT data with 'text' and 'segments'
     * @throws \moodle_exception On transcription or validation errors
     */
    public function transcribe_audio($filepath, $language) {
        // Validate input parameters.
        if (!file_exists($filepath)) {
            throw new \moodle_exception('audiofile_not_found', 'mod_aireading', '', $filepath);
        }

        if (empty($language) || strlen($language) > 10) {
            throw new \moodle_exception('invalid_language', 'mod_aireading', '', $language);
        }

        // Call AI Manager connector.
        try {
            $options = [
                'language' => $language,
                'word_timestamps' => true,
                'response_format' => 'verbose_json',
            ];

            $response = $this->aiconnector->transcribe($filepath, $options);
        } catch (\Exception $e) {
            throw new \moodle_exception(
                'stt_transcription_failed',
                'mod_aireading',
                '',
                null,
                $e->getMessage()
            );
        }

        // Parse and validate response.
        $data = $this->parse_stt_response($response);
        $this->validate_stt_output($data);

        return $data;
    }

    /**
     * Parse STT response from AI Manager
     *
     * Expects JSON format with 'text' and 'segments' fields.
     * Each segment should contain: word, start, end, confidence.
     *
     * @param mixed $response Response from AI Manager connector
     * @return array Parsed data with 'text' and 'segments' keys
     * @throws \moodle_exception If response is not valid JSON or has unexpected format
     */
    private function parse_stt_response($response) {
        // Handle different response formats.
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception(
                    'stt_invalid_json',
                    'mod_aireading',
                    '',
                    null,
                    json_last_error_msg()
                );
            }
            $data = $decoded;
        } else if (is_array($response)) {
            $data = $response;
        } else if (is_object($response)) {
            $data = (array) $response;
        } else {
            throw new \moodle_exception(
                'stt_unexpected_response_type',
                'mod_aireading',
                '',
                null,
                gettype($response)
            );
        }

        return $data;
    }

    /**
     * Validate STT output structure
     *
     * Ensures the response contains required fields:
     * - 'text': The full transcription
     * - 'segments': Array of word-level data
     *   - Each segment must have: word, start, end, confidence
     *
     * @param array $data Parsed STT data
     * @throws \moodle_exception If validation fails
     */
    private function validate_stt_output($data) {
        // Check for required top-level fields.
        if (!isset($data['text'])) {
            throw new \moodle_exception('stt_missing_text', 'mod_aireading');
        }

        if (!isset($data['segments']) || !is_array($data['segments'])) {
            throw new \moodle_exception('stt_missing_segments', 'mod_aireading');
        }

        // Validate each segment.
        foreach ($data['segments'] as $index => $segment) {
            $required = ['word', 'start', 'end', 'confidence'];
            foreach ($required as $field) {
                if (!isset($segment[$field])) {
                    throw new \moodle_exception(
                        'stt_invalid_segment',
                        'mod_aireading',
                        '',
                        null,
                        "Segment $index missing field: $field"
                    );
                }
            }

            // Validate data types.
            if (!is_string($segment['word'])) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: word must be string"
                );
            }

            if (!is_numeric($segment['start']) || !is_numeric($segment['end'])) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: start/end must be numeric"
                );
            }

            if (!is_numeric($segment['confidence'])) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: confidence must be numeric"
                );
            }

            // Validate value ranges.
            if ($segment['start'] < 0 || $segment['end'] < 0) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: timestamps cannot be negative"
                );
            }

            if ($segment['end'] < $segment['start']) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: end must be >= start"
                );
            }

            if ($segment['confidence'] < 0 || $segment['confidence'] > 1) {
                throw new \moodle_exception(
                    'stt_invalid_segment',
                    'mod_aireading',
                    '',
                    null,
                    "Segment $index: confidence must be between 0 and 1"
                );
            }
        }
    }

    /**
     * Get connector instance (for testing purposes)
     *
     * @return \local_ai_manager\base_connector
     */
    public function get_connector() {
        return $this->aiconnector;
    }
}
