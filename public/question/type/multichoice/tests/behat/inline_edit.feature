@qtype @qtype_multichoice
Feature: Inline editing of a multiple-choice question
  As a teacher
  To easily create multiple-choice questions
  I want a WYSIWYG editing interface

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name              | course | idnumber |
      | quiz     | Editing test quiz | C1     | quiz1    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | module       | quiz1     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name         | template    |
      | Test questions   | multichoice | Multi-choice | one_of_four |

  @javascript
  Scenario: Initial view of editing a multiple-choice question
    When I am on the "Multi-choice" "qtype_multichoice > edit test" page logged in as teacher
    Then I should see "Edit test page"
    And I should see "Multiple choice question"
    And I should see "Which is the oddest number?"
    And "One" "list_item" should exist
    And "Two" "list_item" should exist
    And "Three" "list_item" should exist
    And "Four" "list_item" should exist
