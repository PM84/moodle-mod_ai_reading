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
 * Unit tests for attempt manager
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aireading\attempt_manager
 */

namespace mod_aireading;

/**
 * Test cases for attempt manager
 *
 * @package    mod_aireading
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class attempt_manager_test extends \advanced_testcase {
    /**
     * Test creating a new attempt
     *
     * @covers \mod_aireading\attempt_manager::create_attempt
     * @return void
     */
    public function test_create_attempt(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create aireading instance.
        $aireading = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
            'name' => 'Test Reading',
            'maxattempts' => 3,
        ]);

        $manager = new attempt_manager();
        $attempt = $manager->create_attempt($aireading->id, $user->id);

        // Assert attempt was created.
        $this->assertNotEmpty($attempt);
        $this->assertEquals($aireading->id, $attempt->aireading_id);
        $this->assertEquals($user->id, $attempt->userid);
        $this->assertEquals(1, $attempt->attempt);
        $this->assertEquals(0, $attempt->status); // In progress.
    }

    /**
     * Test max attempts limit
     *
     * @covers \mod_aireading\attempt_manager::create_attempt
     * @covers \mod_aireading\attempt_manager::can_user_attempt
     * @return void
     */
    public function test_max_attempts_limit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $aireading = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
            'name' => 'Test Reading',
            'maxattempts' => 2,
        ]);

        $manager = new attempt_manager();

        // Create first attempt.
        $attempt1 = $manager->create_attempt($aireading->id, $user->id);
        $this->assertEquals(1, $attempt1->attempt);

        // Create second attempt.
        $attempt2 = $manager->create_attempt($aireading->id, $user->id);
        $this->assertEquals(2, $attempt2->attempt);

        // Try to create third attempt - should fail.
        $canadd = $manager->can_user_attempt($aireading->id, $user->id);
        $this->assertFalse($canadd);
    }

    /**
     * Test get user attempts
     *
     * @covers \mod_aireading\attempt_manager::get_user_attempts
     * @covers \mod_aireading\attempt_manager::create_attempt
     * @return void
     */
    public function test_get_user_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $aireading = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $manager = new attempt_manager();

        // Create attempts for user1.
        $manager->create_attempt($aireading->id, $user1->id);
        $manager->create_attempt($aireading->id, $user1->id);

        // Create attempt for user2.
        $manager->create_attempt($aireading->id, $user2->id);

        // Get attempts for user1.
        $attempts = $manager->get_user_attempts($aireading->id, $user1->id);
        $this->assertCount(2, $attempts);

        // Get attempts for user2.
        $attempts = $manager->get_user_attempts($aireading->id, $user2->id);
        $this->assertCount(1, $attempts);
    }

    /**
     * Test marking attempt as error
     *
     * @covers \mod_aireading\attempt_manager::mark_attempt_error
     * @covers \mod_aireading\attempt_manager::create_attempt
     * @return void
     */
    public function test_mark_attempt_error(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $aireading = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
        ]);

        $manager = new attempt_manager();
        $attempt = $manager->create_attempt($aireading->id, $user->id);

        // Mark as error.
        $manager->mark_attempt_error($attempt->id, 'test_error', 'Test error message');

        // Verify status changed.
        $updated = $DB->get_record('aireading_attempts', ['id' => $attempt->id]);
        $this->assertEquals(3, $updated->status); // Error status.
    }

    /**
     * Test unlimited attempts
     *
     * @covers \mod_aireading\attempt_manager::can_user_attempt
     * @covers \mod_aireading\attempt_manager::create_attempt
     * @return void
     */
    public function test_unlimited_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $aireading = $this->getDataGenerator()->create_module('aireading', [
            'course' => $course->id,
            'maxattempts' => 0, // Unlimited.
        ]);

        $manager = new attempt_manager();

        // Create 5 attempts.
        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($manager->can_user_attempt($aireading->id, $user->id));
            $manager->create_attempt($aireading->id, $user->id);
        }

        // Should still be able to create more.
        $this->assertTrue($manager->can_user_attempt($aireading->id, $user->id));
    }
}
