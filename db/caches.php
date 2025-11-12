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
 * Cache definitions for AI Reading module.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Cache for user attempts - frequently accessed for student view.
    'user_attempts' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 50,
        'ttl' => 3600, // 1 hour.
        'invalidationevents' => [
            'mod_aireading\event\attempt_created',
            'mod_aireading\event\attempt_submitted',
            'mod_aireading\event\attempt_analyzed',
        ],
    ],

    // Cache for course statistics - used in teacher reports.
    'course_stats' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 20,
        'ttl' => 7200, // 2 hours.
        'invalidationevents' => [
            'mod_aireading\event\attempt_analyzed',
        ],
    ],

    // Cache for analysis results - expensive to recompute.
    'analysis_results' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
        'ttl' => 86400, // 24 hours.
        'invalidationevents' => [
            'mod_aireading\event\attempt_analyzed',
        ],
    ],

    // Cache for chart data - reduces JSON processing overhead.
    'chart_data' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30,
        'ttl' => 3600, // 1 hour.
        'invalidationevents' => [
            'mod_aireading\event\attempt_analyzed',
        ],
    ],
];
