@mod @mod_quiz @wip
Feature: Edit quiz page
  In order to create quizzes
  As a teacher
  I need the Edit quiz page to work

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Terry1    | Teacher1 | teacher1@moodle.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    When I log in as "teacher1"
    And I follow "Course 1"
    # Step 1
    And I turn editing mode on
    And I add a "Quiz" to section "1" and I fill the form with:
      | Name        | Quiz for editing |
      | Description | This quiz is used to test all aspects of the edit quiz page. |
    And I follow "Quiz for editing"
    # Step 2
    And I follow "Edit quiz"

  @javascript
  Scenario: Do lots of adding, reordering and removing questions.
    # Step 3
    Then I should see "Editing quiz: Quiz for editing"
    And I should see "Questions: 0"

    # Step 4
    And I follow "Add"
    And I follow "Add a question"
    And I set the field "True/False" to "1"
    And I press "Next"
    And I set the following fields to these values:
      | Question name | Question A          |
      | Question text | The answer is false |
    And I press "id_submitbutton"
    Then I should see "Editing quiz: Quiz for editing"
    And I should see "Question A"

    # Step 5
    # Step 6
    And I set the max mark for question "Question A" to "2.5"
    And I should see "2.5"
    And I should see "Total of marks: 2.50"

    # Step 7
    And I follow "Add"
    And I follow "Add a question"
    And I set the field "True/False" to "1"
    And I press "Next"
    And I set the following fields to these values:
      | Question name | Question B          |
      | Question text | Its false again     |
    And I press "id_submitbutton"
    Then I should see "Editing quiz: Quiz for editing"
    # TODO next step should verify it is on page 1.
    And I should see "Question B"
    # Step 8
    And I follow "Add"
    And I follow "Add a question"
    And I set the field "True/False" to "1"
    And I press "Next"
    And I set the following fields to these values:
      | Question name | Question C          |
      | Question text | Its false again     |
      |               |                     |
    And I press "id_submitbutton"
    Then I should see "Editing quiz: Quiz for editing"
    # TODO next step should verify it is on page 2.
    And I should see "Question C"
