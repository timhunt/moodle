@core @core_question
Feature: Add a default question bank
  In order to manage shared questions
  As a teacher
  I need to create a default question bank

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Add a default question bank to a course
    Given I am on the "C1" "Course" page logged in as "teacher1"
    And I navigate to "Question bank" in current page administration
    Then I should see "This course does not have any question banks yet"
    And I should see "Add another question bank"
    When I click on "Create default question bank" "button"
    Then I should not see "This course does not have any question banks yet"
    And I should see "Default course question bank created"
    And I should see "Course 1 course question bank"
