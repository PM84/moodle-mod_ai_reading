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
 * Results viewer for AI Reading Trainer
 *
 * Provides interactive features for reading feedback display:
 * - Error tooltips on hover
 * - Click-to-seek audio synchronization
 * - Visual highlighting during audio playback
 *
 * @module     mod_aireading/results_viewer
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {

    /**
     * Results viewer class
     */
    var ResultsViewer = function() {
        this.audioplayer = null;
        this.currentWord = null;
        this.minConfidence = 0.80;
        this.beginnerMode = false;
    };

    /**
     * Initialize the results viewer
     *
     * @param {Object} config Configuration object
     * @param {number} config.minconfidence Minimum confidence threshold
     * @param {boolean} config.beginnermode Whether beginner mode is active
     */
    ResultsViewer.prototype.init = function(config) {
        this.minConfidence = config.minconfidence || 0.80;
        this.beginnerMode = config.beginnermode || false;

        // Get audio player reference.
        this.audioplayer = document.getElementById('attempt-audio-player');

        // Attach event listeners.
        this.attachWordListeners();
        this.attachAudioListeners();
        this.initializeTooltips();

        // Initialize accessibility features.
        this.initializeAccessibility();
    };

    /**
     * Attach event listeners to word elements
     */
    ResultsViewer.prototype.attachWordListeners = function() {
        var self = this;

        $('.ai-reading-annotated-text .word').each(function() {
            var $word = $(this);

            // Hover events for tooltips.
            $word.on('mouseenter', function() {
                self.showTooltip($word);
            });

            $word.on('mouseleave', function() {
                self.hideTooltip($word);
            });

            // Click events for audio seek.
            $word.on('click', function(e) {
                e.preventDefault();
                self.seekToWord($word);
            });

            // Keyboard support.
            $word.on('keypress', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space.
                    e.preventDefault();
                    self.seekToWord($word);
                }
            });
        });
    };

    /**
     * Attach event listeners to audio player
     */
    ResultsViewer.prototype.attachAudioListeners = function() {
        var self = this;

        if (!this.audioplayer) {
            return;
        }

        // Update highlighting during playback.
        $(this.audioplayer).on('timeupdate', function() {
            self.updatePlaybackHighlight();
        });

        // Clear highlighting when playback ends.
        $(this.audioplayer).on('ended pause', function() {
            self.clearPlaybackHighlight();
        });
    };

    /**
     * Initialize tooltip functionality
     */
    ResultsViewer.prototype.initializeTooltips = function() {
        // Create tooltip container if it doesn't exist.
        if ($('#ai-reading-tooltip').length === 0) {
            $('body').append('<div id="ai-reading-tooltip" class="ai-reading-tooltip" role="tooltip"></div>');
        }
    };

    /**
     * Show tooltip for a word
     *
     * @param {jQuery} $word Word element
     */
    ResultsViewer.prototype.showTooltip = function($word) {
        var errorType = $word.data('error-type');

        if (!errorType) {
            return; // No error, no tooltip.
        }

        var tooltipContent = this.generateTooltipContent($word, errorType);
        var $tooltip = $('#ai-reading-tooltip');

        $tooltip.html(tooltipContent);
        $tooltip.show();

        // Position tooltip near word.
        var wordOffset = $word.offset();
        var wordHeight = $word.outerHeight();

        $tooltip.css({
            top: (wordOffset.top + wordHeight + 5) + 'px',
            left: wordOffset.left + 'px'
        });

        // ARIA announcement.
        $tooltip.attr('aria-live', 'polite');
    };

    /**
     * Hide tooltip
     *
     * @param {jQuery} $word Word element
     */
    ResultsViewer.prototype.hideTooltip = function($word) {
        $('#ai-reading-tooltip').hide();
    };

    /**
     * Generate tooltip content based on error type
     *
     * @param {jQuery} $word Word element
     * @param {string} errorType Type of error
     * @return {string} HTML content for tooltip
     */
    ResultsViewer.prototype.generateTooltipContent = function($word, errorType) {
        var html = '<div class="tooltip-content">';
        var self = this;

        switch (errorType) {
            case 'substitution':
                var expected = $word.data('expected');
                var actual = $word.data('actual');
                html += '<strong>' + M.util.get_string('error_substitution', 'mod_aireading') + '</strong><br>';
                html += M.util.get_string('expected', 'mod_aireading') + ': ' + this.escapeHtml(expected) + '<br>';
                html += M.util.get_string('you_said', 'mod_aireading') + ': ' + this.escapeHtml(actual);
                break;

            case 'omission':
                var omitted = $word.data('omitted');
                html += '<strong>' + M.util.get_string('error_omission', 'mod_aireading') + '</strong><br>';
                html += M.util.get_string('word_omitted', 'mod_aireading') + ': ' + this.escapeHtml(omitted);
                break;

            case 'pause':
                var duration = parseFloat($word.data('duration'));
                html += '<strong>' + M.util.get_string('error_pause', 'mod_aireading') + '</strong><br>';
                html += M.util.get_string('pause_duration', 'mod_aireading') + ': ' + duration.toFixed(1) + 's';
                break;

            case 'hesitation':
                html += '<strong>' + M.util.get_string('error_hesitation', 'mod_aireading') + '</strong><br>';
                html += M.util.get_string('hesitation_desc', 'mod_aireading');
                break;

            case 'pronunciation_bad':
            case 'pronunciation_ok':
                var confidence = parseFloat($word.data('confidence'));
                var isPoor = errorType === 'pronunciation_bad';
                var label = isPoor ? 'pronunciation_poor' : 'pronunciation_acceptable';

                html += '<strong>' + M.util.get_string(label, 'mod_aireading') + '</strong><br>';
                html += M.util.get_string('confidence_score', 'mod_aireading') + ': ' + confidence.toFixed(1) + '%<br>';
                html += M.util.get_string('expected', 'mod_aireading') + ': ≥ ' +
                        (this.minConfidence * 100).toFixed(0) + '%';

                if (isPoor) {
                    html += '<br><em>' + M.util.get_string('practice_suggestion', 'mod_aireading') + '</em>';
                }
                break;

            default:
                html += M.util.get_string('error_unknown', 'mod_aireading');
        }

        html += '</div>';

        // Add click-to-seek hint if not in beginner mode.
        if (!this.beginnerMode && this.audioplayer) {
            html += '<div class="tooltip-hint">' +
                    M.util.get_string('click_to_seek', 'mod_aireading') +
                    '</div>';
        }

        return html;
    };

    /**
     * Seek audio player to word position
     *
     * @param {jQuery} $word Word element
     */
    ResultsViewer.prototype.seekToWord = function($word) {
        if (!this.audioplayer) {
            return;
        }

        // Get word position and try to find corresponding time in audio.
        var position = parseInt($word.data('position'));
        var timestamp = this.calculateTimestamp(position);

        if (timestamp !== null) {
            this.audioplayer.currentTime = timestamp;
            this.audioplayer.play();

            // Highlight word during playback.
            this.highlightWord($word);
        }
    };

    /**
     * Calculate timestamp for word position
     *
     * This is a simple estimation - in production, you would get
     * actual timestamps from the STT segments data.
     *
     * @param {number} position Word position in text
     * @return {number|null} Timestamp in seconds
     */
    ResultsViewer.prototype.calculateTimestamp = function(position) {
        // This should ideally come from STT segment data.
        // For now, we'll use a simple estimation based on average reading speed.

        // Try to get actual timestamp from data attribute (if available).
        var $word = $('.ai-reading-annotated-text .word[data-position="' + position + '"]');
        var timestamp = $word.data('timestamp');

        if (timestamp !== undefined) {
            return parseFloat(timestamp);
        }

        // Fallback: estimate based on position.
        // Assuming average 2 words per second.
        return position * 0.5;
    };

    /**
     * Update highlighting during audio playback
     */
    ResultsViewer.prototype.updatePlaybackHighlight = function() {
        if (!this.audioplayer) {
            return;
        }

        var currentTime = this.audioplayer.currentTime;

        // Find word at current playback position.
        var $words = $('.ai-reading-annotated-text .word');
        var self = this;

        $words.each(function() {
            var $word = $(this);
            var timestamp = parseFloat($word.data('timestamp'));

            if (timestamp !== undefined && Math.abs(timestamp - currentTime) < 0.3) {
                self.highlightWord($word);
                return false; // Break loop.
            }
        });
    };

    /**
     * Highlight a word during playback
     *
     * @param {jQuery} $word Word element
     */
    ResultsViewer.prototype.highlightWord = function($word) {
        // Clear previous highlight.
        $('.ai-reading-annotated-text .word').removeClass('playing');

        // Add highlight to current word.
        $word.addClass('playing');
        this.currentWord = $word;

        // Scroll word into view.
        $word[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    };

    /**
     * Clear playback highlighting
     */
    ResultsViewer.prototype.clearPlaybackHighlight = function() {
        $('.ai-reading-annotated-text .word').removeClass('playing');
        this.currentWord = null;
    };

    /**
     * Initialize accessibility features
     */
    ResultsViewer.prototype.initializeAccessibility = function() {
        // Add ARIA live region for announcements.
        if ($('#ai-reading-announcements').length === 0) {
            $('body').append(
                '<div id="ai-reading-announcements" class="sr-only" ' +
                'role="status" aria-live="polite" aria-atomic="true"></div>'
            );
        }

        // Keyboard navigation hints.
        $('.ai-reading-annotated-text').attr('role', 'application');
        $('.ai-reading-annotated-text').attr(
            'aria-label',
            M.util.get_string('annotated_text_description', 'mod_aireading')
        );
    };

    /**
     * Escape HTML special characters
     *
     * @param {string} text Text to escape
     * @return {string} Escaped text
     */
    ResultsViewer.prototype.escapeHtml = function(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Module initialization
     *
     * @param {Object} config Configuration object
     */
    var init = function(config) {
        var viewer = new ResultsViewer();
        viewer.init(config);
    };

    return {
        init: init
    };
});
