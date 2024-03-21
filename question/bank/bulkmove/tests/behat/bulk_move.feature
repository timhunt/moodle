@qbank @qbank_bulkmove
Feature: Use the qbank plugin manager page for bulkmove
  In order to check the plugin behaviour with enable and disable

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
      | Course 3 | C3        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user | course | role           |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C2 | editingteacher |
    And the following "activities" exist:
      | activity    | name            | course | idnumber  |
      | quiz        | Test quiz       | C1     | quiz1     |
      | qbank       | Question bank 1 | C1     | qbank1    |
      | qbank       | Question bank 2 | C2     | qbank2    |
      | qbank       | Question bank 3 | C3     | qbank3    |
    And the following "question categories" exist:
      | contextlevel    | reference  | name              |
      | Activity module | quiz1      | Test questions 1  |
      | Activity module | qbank1     | Test questions 2  |
      | Activity module | qbank2     | Test questions 3  |
      | Activity module | qbank3     | Test questions 4  |
      | Activity module | qbank1     | Test questions 5  |
      | Activity module | quiz1      | Test questions 6  |
    And the following "questions" exist:
      | questioncategory   | qtype     | name            | questiontext               |
      | Test questions 1   | truefalse | First question  | Answer the first question  |
      | Test questions 2   | truefalse | Second question | Answer the second question |
      | Test questions 3   | truefalse | Third question  | Answer the third question  |
      | Test questions 4   | truefalse | Fourth question | Answer the fourth question |
      | Test questions 5   | truefalse | Fifth question  | Answer the fifth question  |
      | Test questions 6   | truefalse | Sixth question  | Answer the sixth question  |

  @javascript
  Scenario: Enable/disable bulk move questions bulk action from the base view
    Given I log in as "admin"
    When I navigate to "Plugins > Question bank plugins > Manage question bank plugins" in site administration
    And I should see "Bulk move questions"
    And I click on "Disable" "link" in the "Bulk move questions" "table_row"
    And I am on the "Test quiz" "mod_quiz > question bank" page
    And I click on "First question" "checkbox"
    And I click on "With selected" "button"
    Then I should not see question bulk action "move"
    And I navigate to "Plugins > Question bank plugins > Manage question bank plugins" in site administration
    And I click on "Enable" "link" in the "Bulk move questions" "table_row"
    And I am on the "Test quiz" "mod_quiz > question bank" page
    And I click on "First question" "checkbox"
    And I click on "With selected" "button"
    And I should see question bulk action "move"

  @javascript
  Scenario: Search for shared question banks I have access to.
    Given I log in as "teacher1"
    And I am on the "Test quiz" "mod_quiz > question bank" page
    And I click on "First question" "checkbox"
    And I click on "With selected" "button"
    And I click on "move" "button"
    Then I should see "Move the selected questions to..." in the ".modal-title" "css_element"
    And I should see "Test quiz" in the ".search-banks" "css_element"
    And I should see "Test questions 1" in the ".search-categories" "css_element"
    When I open the autocomplete suggestions list in the ".search-banks" "css_element"
    Then I should see "Question bank 1"
    And I should see "Question bank 2"
    But I should not see "Question bank 3"

  @javascript
  Scenario: Search for shared question bank categories I have access to.
    Given I log in as "teacher1"
    And I am on the "Question bank 1" "mod_qbank > question bank" page
    And I click on "Second question" "checkbox"
    And I click on "With selected" "button"
    And I click on "move" "button"
    Then I should see "Move the selected questions to..." in the ".modal-title" "css_element"
    When I open the autocomplete suggestions list in the ".search-categories" "css_element"
    Then I should see "Test questions 5"
    And I should not see "Test questions 4"
    But I should not see "Test questions 3"
    But I should not see "Test questions 1"

  @javascript
  Scenario: Selecting a shared question bank shortens the available categories list to that bank.
    Given I log in as "teacher1"
    And I am on the "Test quiz" "mod_quiz > question bank" page
    And I click on "First question" "checkbox"
    And I click on "With selected" "button"
    And I click on "move" "button"
    And I open the autocomplete suggestions list in the ".search-categories" "css_element"
    And "Test questions 2" "autocomplete_suggestions" should not exist
    And "Test questions 3" "autocomplete_suggestions" should not exist
    And "Test questions 4" "autocomplete_suggestions" should not exist
    And "Test questions 5" "autocomplete_suggestions" should not exist
    But "Test questions 6" "autocomplete_suggestions" should exist
    And I should see "Test questions 1" in the ".search-categories" "css_element"
    When I open the autocomplete suggestions list in the ".search-banks" "css_element"
    And I click on "Question bank 1" item in the autocomplete list
    Then I should not see "Test questions 1" in the ".search-categories" "css_element"
    And I open the autocomplete suggestions list in the ".search-categories" "css_element"
    And "Test questions 2" "autocomplete_suggestions" should exist
    And "Test questions 3" "autocomplete_suggestions" should not exist
    And "Test questions 4" "autocomplete_suggestions" should not exist
    And "Test questions 5" "autocomplete_suggestions" should exist

  @javascript
  Scenario: Move a question from one bank category to another.
    Given I log in as "teacher1"
    And I am on the "Test quiz" "mod_quiz > question bank" page
    And I click on "First question" "checkbox"
    And I click on "With selected" "button"
    And I click on "move" "button"
    And I open the autocomplete suggestions list in the ".search-categories" "css_element"
    And I click on "Test questions 6" item in the autocomplete list
    And I click on "Move questions" "button"
    Then I should see "Are you sure you want to move these questions?"
    And I click on "Confirm" "button"
    And I wait until the page is ready
    Then I should see "Questions successfully moved"
