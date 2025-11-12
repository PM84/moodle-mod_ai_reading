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

namespace mod_aireading;

/**
 * Unit tests for statistics_manager
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aireading\statistics_manager
 */
final class statistics_manager_test extends \advanced_testcase {
    /**
     * Test get_user_best_wpm
     */
    public function test_get_user_best_wpm(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');

        // Create 3 attempts with different WPM values.
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 1, ['wpm' => 80.0]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 2, ['wpm' => 95.5]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 3, ['wpm' => 88.2]);

        $statsmanager = new statistics_manager();
        $bestwpm = $statsmanager->get_user_best_wpm($aireadingrecord->id, $user->id);

        $this->assertEquals(95.5, $bestwpm);
    }

    /**
     * Test get_user_best_wpm with no attempts
     */
    public function test_get_user_best_wpm_no_attempts(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $statsmanager = new statistics_manager();
        $bestwpm = $statsmanager->get_user_best_wpm($aireadingrecord->id, $user->id);

        $this->assertNull($bestwpm);
    }

    /**
     * Test get_course_average_wpm
     */
    public function test_get_course_average_wpm(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');

        // Create 3 users with attempts.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $generator->create_analyzed_attempt($aireadingrecord->id, $user1->id, 1, ['wpm' => 80.0]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user2->id, 1, ['wpm' => 100.0]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user3->id, 1, ['wpm' => 90.0]);

        $statsmanager = new statistics_manager();
        $avgwpm = $statsmanager->get_course_average_wpm($aireadingrecord->id);

        $this->assertEquals(90.0, $avgwpm);
    }

    /**
     * Test get_user_progress
     */
    public function test_get_user_progress(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');

        // Create 3 attempts showing progress.
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 1, [
            'wpm' => 70.0,
            'accuracy' => 70.0,
            'fluency' => 65.0,
        ]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 2, [
            'wpm' => 85.0,
            'accuracy' => 80.0,
            'fluency' => 75.0,
        ]);
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 3, [
            'wpm' => 95.0,
            'accuracy' => 90.0,
            'fluency' => 85.0,
        ]);

        $statsmanager = new statistics_manager();
        $progress = $statsmanager->get_user_progress($aireadingrecord->id, $user->id);

        $this->assertCount(3, $progress);
        $this->assertEquals(1, $progress[0]->attempt);
        $this->assertEquals(70.0, $progress[0]->wpm);
        $this->assertEquals(3, $progress[2]->attempt);
        $this->assertEquals(95.0, $progress[2]->wpm);
    }

    /**
     * Test get_pronunciation_statistics
     */
    public function test_get_pronunciation_statistics(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
            'enablepronunciation' => 1,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');

        // Create attempt with pronunciation score.
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 1, [
            'pronunciation' => 75.0,
        ]);

        $statsmanager = new statistics_manager();
        $stats = $statsmanager->get_pronunciation_statistics($aireadingrecord->id, $user->id);

        $this->assertNotNull($stats);
        $this->assertEquals(75.0, $stats->average_pronunciation);
    }

    /**
     * Test get_pronunciation_statistics with disabled pronunciation
     */
    public function test_get_pronunciation_statistics_disabled(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
            'enablepronunciation' => 0,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 1);

        $statsmanager = new statistics_manager();
        $stats = $statsmanager->get_pronunciation_statistics($aireadingrecord->id, $user->id);

        $this->assertNull($stats);
    }

    /**
     * Test error distribution calculation
     */
    public function test_get_error_distribution(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $aireadingrecord = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aireading');
        $generator->create_analyzed_attempt($aireadingrecord->id, $user->id, 1);

        $statsmanager = new statistics_manager();
        $distribution = $statsmanager->get_error_distribution($aireadingrecord->id, $user->id);

        $this->assertIsArray($distribution);
        $this->assertArrayHasKey('accuracy', $distribution);
        $this->assertArrayHasKey('fluency', $distribution);
    }
}
