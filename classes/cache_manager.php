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
 * Cache manager for AI Reading module.
 *
 * Provides centralized cache access and invalidation logic.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aireading;

/**
 * Cache manager class.
 *
 * Wraps Moodle's cache API with convenience methods for the AI Reading module.
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_manager {
    /** @var \cache|null User attempts cache instance */
    private static $userattemptscache = null;

    /** @var \cache|null Course statistics cache instance */
    private static $coursestatscache = null;

    /** @var \cache|null Analysis results cache instance */
    private static $analysisresultscache = null;

    /** @var \cache|null Chart data cache instance */
    private static $chartdatacache = null;

    /**
     * Get user attempts cache instance.
     *
     * @return \cache Cache instance
     */
    private static function get_user_attempts_cache(): \cache {
        if (self::$userattemptscache === null) {
            self::$userattemptscache = \cache::make('mod_aireading', 'user_attempts');
        }
        return self::$userattemptscache;
    }

    /**
     * Get course statistics cache instance.
     *
     * @return \cache Cache instance
     */
    private static function get_course_stats_cache(): \cache {
        if (self::$coursestatscache === null) {
            self::$coursestatscache = \cache::make('mod_aireading', 'course_stats');
        }
        return self::$coursestatscache;
    }

    /**
     * Get analysis results cache instance.
     *
     * @return \cache Cache instance
     */
    private static function get_analysis_results_cache(): \cache {
        if (self::$analysisresultscache === null) {
            self::$analysisresultscache = \cache::make('mod_aireading', 'analysis_results');
        }
        return self::$analysisresultscache;
    }

    /**
     * Get chart data cache instance.
     *
     * @return \cache Cache instance
     */
    private static function get_chart_data_cache(): \cache {
        if (self::$chartdatacache === null) {
            self::$chartdatacache = \cache::make('mod_aireading', 'chart_data');
        }
        return self::$chartdatacache;
    }

    /**
     * Get cached user attempts.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID
     * @return array|false Cached attempts or false if not cached
     */
    public static function get_user_attempts(int $aireadingid, int $userid) {
        $cache = self::get_user_attempts_cache();
        $key = "attempts_{$aireadingid}_{$userid}";
        return $cache->get($key);
    }

    /**
     * Set cached user attempts.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID
     * @param array $attempts Attempts data
     * @return bool Success
     */
    public static function set_user_attempts(int $aireadingid, int $userid, array $attempts): bool {
        $cache = self::get_user_attempts_cache();
        $key = "attempts_{$aireadingid}_{$userid}";
        return $cache->set($key, $attempts);
    }

    /**
     * Invalidate user attempts cache.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID
     * @return bool Success
     */
    public static function invalidate_user_attempts(int $aireadingid, int $userid): bool {
        $cache = self::get_user_attempts_cache();
        $key = "attempts_{$aireadingid}_{$userid}";
        return $cache->delete($key);
    }

    /**
     * Get cached course statistics.
     *
     * @param int $courseid Course ID
     * @param int $aireadingid AI reading activity ID (optional, 0 for all activities)
     * @return array|false Cached statistics or false if not cached
     */
    public static function get_course_stats(int $courseid, int $aireadingid = 0) {
        $cache = self::get_course_stats_cache();
        $key = "stats_{$courseid}_{$aireadingid}";
        return $cache->get($key);
    }

    /**
     * Set cached course statistics.
     *
     * @param int $courseid Course ID
     * @param int $aireadingid AI reading activity ID (optional, 0 for all activities)
     * @param array $stats Statistics data
     * @return bool Success
     */
    public static function set_course_stats(int $courseid, int $aireadingid, array $stats): bool {
        $cache = self::get_course_stats_cache();
        $key = "stats_{$courseid}_{$aireadingid}";
        return $cache->set($key, $stats);
    }

    /**
     * Invalidate course statistics cache.
     *
     * @param int $courseid Course ID
     * @param int $aireadingid AI reading activity ID (optional, 0 for all activities)
     * @return bool Success
     */
    public static function invalidate_course_stats(int $courseid, int $aireadingid = 0): bool {
        $cache = self::get_course_stats_cache();
        $key = "stats_{$courseid}_{$aireadingid}";
        return $cache->delete($key);
    }

    /**
     * Get cached analysis results.
     *
     * @param int $attemptid Attempt ID
     * @return array|false Cached analysis or false if not cached
     */
    public static function get_analysis_results(int $attemptid) {
        $cache = self::get_analysis_results_cache();
        $key = "analysis_{$attemptid}";
        return $cache->get($key);
    }

    /**
     * Set cached analysis results.
     *
     * @param int $attemptid Attempt ID
     * @param array $analysis Analysis data
     * @return bool Success
     */
    public static function set_analysis_results(int $attemptid, array $analysis): bool {
        $cache = self::get_analysis_results_cache();
        $key = "analysis_{$attemptid}";
        return $cache->set($key, $analysis);
    }

    /**
     * Invalidate analysis results cache.
     *
     * @param int $attemptid Attempt ID
     * @return bool Success
     */
    public static function invalidate_analysis_results(int $attemptid): bool {
        $cache = self::get_analysis_results_cache();
        $key = "analysis_{$attemptid}";
        return $cache->delete($key);
    }

    /**
     * Get cached chart data.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID
     * @param string $charttype Chart type (e.g., 'wpm_progress', 'error_distribution')
     * @return array|false Cached chart data or false if not cached
     */
    public static function get_chart_data(int $aireadingid, int $userid, string $charttype) {
        $cache = self::get_chart_data_cache();
        $key = "chart_{$charttype}_{$aireadingid}_{$userid}";
        return $cache->get($key);
    }

    /**
     * Set cached chart data.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID
     * @param string $charttype Chart type
     * @param array $chartdata Chart data
     * @return bool Success
     */
    public static function set_chart_data(int $aireadingid, int $userid, string $charttype, array $chartdata): bool {
        $cache = self::get_chart_data_cache();
        $key = "chart_{$charttype}_{$aireadingid}_{$userid}";
        return $cache->set($key, $chartdata);
    }

    /**
     * Invalidate chart data cache.
     *
     * @param int $aireadingid AI reading activity ID
     * @param int $userid User ID (optional, 0 for all users)
     * @param string $charttype Chart type (optional, empty for all chart types)
     * @return bool Success
     */
    public static function invalidate_chart_data(int $aireadingid, int $userid = 0, string $charttype = ''): bool {
        $cache = self::get_chart_data_cache();

        if ($userid > 0 && !empty($charttype)) {
            // Invalidate specific chart for specific user.
            $key = "chart_{$charttype}_{$aireadingid}_{$userid}";
            return $cache->delete($key);
        } else {
            // Purge all chart data for this activity (expensive but safe).
            return $cache->purge();
        }
    }

    /**
     * Purge all caches for the AI Reading module.
     *
     * Should be called sparingly, typically only during major data migrations or debugging.
     *
     * @return bool Success
     */
    public static function purge_all_caches(): bool {
        $success = true;

        $success = $success && self::get_user_attempts_cache()->purge();
        $success = $success && self::get_course_stats_cache()->purge();
        $success = $success && self::get_analysis_results_cache()->purge();
        $success = $success && self::get_chart_data_cache()->purge();

        return $success;
    }
}
