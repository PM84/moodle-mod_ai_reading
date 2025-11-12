# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# AI Reading activity workflow tests
#
# @package    mod_aireading
# @copyright  2025 ISB Bayern
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@mod @mod_aireading @javascript
Feature: AI Reading activity workflow
  In order to practice reading fluency
  As a student
  I need to be able to record my reading and receive feedback

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Teacher creates an AI Reading activity
    Given I am on the "Course 1" "course" page logged in as "teacher1"
    When I turn editing mode on
    And I add a "AI Reading Trainer" to section "1" and I fill the form with:
      | Name             | Reading Test 1                           |
      | Reading text     | Der Wald ist dunkel und geheimnisvoll.   |
      | Language         | German (de)                              |
      | Target WPM       | 100                                      |
      | Maximum attempts | 3                                        |
      | Grading method   | Highest grade                            |
      | Silence threshold| 10                                       |
    Then I should see "Reading Test 1"
    And I am on the "Reading Test 1" "aireading activity" page
    And I should see "Der Wald ist dunkel und geheimnisvoll"

  Scenario: Teacher creates activity with pronunciation assessment
    Given I am on the "Course 1" "course" page logged in as "teacher1"
    When I turn editing mode on
    And I add a "AI Reading Trainer" to section "1" and I fill the form with:
      | Name                         | Pronunciation Test     |
      | Reading text                 | The cat sat on the mat |
      | Language                     | English (en)           |
      | Target WPM                   | 80                     |
      | Maximum attempts             | 5                      |
      | Enable pronunciation assessment | 1                   |
      | Minimum confidence           | 80%                    |
    Then I should see "Pronunciation Test"
    And I am on the "Pronunciation Test" "aireading activity" page
    And I should see "Pronunciation assessment: Enabled"
    And I should see "Minimum confidence: 80%"

  Scenario: Student views AI Reading activity
    Given the following "activities" exist:
      | activity    | name           | course | idnumber     | readingtext                          |
      | aireading  | Reading Test 1 | C1     | airead1      | Der Wald ist dunkel.                 |
    And I am on the "Reading Test 1" "aireading activity" page logged in as "student1"
    Then I should see "Reading Test 1"
    And I should see "Der Wald ist dunkel"
    And I should see "Start Recording"
    And I should see "Attempts: 0 / 3"

  Scenario: Student starts recording
    Given the following "activities" exist:
      | activity    | name           | course | idnumber     | readingtext          |
      | aireading  | Reading Test 1 | C1     | airead1      | Der Wald ist dunkel. |
    And I am on the "Reading Test 1" "aireading activity" page logged in as "student1"
    When I click on "Start Recording" "button"
    Then I should see "Recording"
    And the "Stop Recording" "button" should be visible
    And I should see "Timer:"

  Scenario: Student respects attempt limit
    Given the following "activities" exist:
      | activity    | name           | course | idnumber     | readingtext          | maxattempts |
      | aireading  | Reading Test 1 | C1     | airead1      | Der Wald ist dunkel. | 2           |
    And I am on the "Reading Test 1" "aireading activity" page logged in as "student1"
    And I should see "Attempts: 0 / 2"
    # TODO: Add steps for creating 2 attempts
    # Then I should see "Maximum attempts reached"
    # And the "Start Recording" "button" should not be visible

  Scenario: Teacher views all student attempts
    Given the following "activities" exist:
      | activity    | name           | course | idnumber     | readingtext          |
      | aireading  | Reading Test 1 | C1     | airead1      | Der Wald ist dunkel. |
    # TODO: Add steps for creating student attempts
    And I am on the "Reading Test 1" "aireading activity" page logged in as "teacher1"
    Then I should see "View all attempts"
    When I click on "View all attempts" "link"
    Then I should see "Student One"
    # And I should see attempt details

  Scenario: Teacher accesses word difficulty report
    Given the following "activities" exist:
      | activity    | name                | course | idnumber     | readingtext          | enablepronunciation |
      | aireading  | Pronunciation Test  | C1     | airead1      | Der Wald ist dunkel. | 1                   |
    And I am on the "Pronunciation Test" "aireading activity" page logged in as "teacher1"
    When I click on "Reports" "link"
    Then I should see "Word Difficulty Analysis"
    And I should see "Most difficult words"
    # And I should see pronunciation statistics per word
