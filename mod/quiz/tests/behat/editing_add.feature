@mod @mod_quiz
Feature: Edit quiz page - adding things
  In order to build the quiz I want my students to attempt
  As a teacher
  I need to be able to add questions to the quiz.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | T1        | Teacher1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity   | name   | intro                           | course | idnumber |
      | quiz       | Quiz 1 | Quiz 1 for testing the Add menu | C1     | quiz1    |

  @javascript
  Scenario: Add some new question to the quiz using '+ a new question' options of the 'Add' menu.
    Given I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"
    When I open the "last" add to quiz menu
    And I follow "a new question"
    And I set the field "item_qtype_essay" to "1"
    And I press "submitbutton"
    And I should see "Adding an Essay question"
    And I set the field "Question name" to "Essay 01 new"
    And I set the field "Question text" to "Please write 200 words about Essay 01"
    And I press "id_submitbutton"
    Then I should see "Editing quiz: Quiz 1"
    And I should see "Essay 01 new" on quiz page "1"

    And I open the "Page 1" add to quiz menu
    And I follow "a new question"
    And I set the field "item_qtype_essay" to "1"
    And I press "submitbutton"
    And I should see "Adding an Essay question"
    And I set the field "Question name" to "Essay 02 new"
    And I set the field "Question text" to "Please write 200 words about Essay 02"
    And I press "id_submitbutton"
    And I should see "Editing quiz: Quiz 1"
    And I should see "Essay 01 new" on quiz page "1"
    And I should see "Essay 02 new" on quiz page "1"

  @javascript
  Scenario: Add some new question to the quiz using '+ a new question' options of the 'Add' menu.
    Given the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name     | questiontext                          |
      | Test questions   | essay | Essay 01 | Please write 200 words about Essay 01 |
      | Test questions   | essay | Essay 02 | Please write 200 words about Essay 02 |
      | Test questions   | essay | Essay 03 | Please write 200 words about Essay 03 |
      | Test questions   | essay | Essay 04 | Please write 200 words about Essay 04 |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | Essay 01 | 1    |
      | Essay 02 | 1    |
      | Essay 03 | 2    |
      | Essay 04 | 2    |
    And I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"

    # Add a question to page 2.
    When I open the "Page 2" add to quiz menu
    And I choose "a new question" in the open action menu
    And I set the field "item_qtype_essay" to "1"
    And I press "submitbutton"
    And I should see "Adding an Essay question"
    And I set the field "Question name" to "Essay for page 2"
    And I set the field "Question text" to "Please write 200 words about Essay for page 2"
    And I press "id_submitbutton"
    Then I should see "Editing quiz: Quiz 1"
    And I should see "Essay 01 new" on quiz page "1"
    And I should see "Essay 02 new" on quiz page "1"
    And I should see "Essay 03 new" on quiz page "2"
    And I should see "Essay 04 new" on quiz page "2"
    And I should see "Essay for page 2" on quiz page "2"

  @javascript
  Scenario: Add questions from question bank to the quiz. In order to be able to
      add questions from question bank to the quiz, first we create some new questions
      in various categories and add them to the question bank.

    # Create a couple of sub categories.
    Given the following "question categories" exist:
      | contextlevel | reference | name       |
      | Course       | C1        | Category 1 |
      | Course       | C1        | Category 2 |
    And the following "questions" exist:
      | questioncategory | qtype     | name     | questiontext                          |
      | Category 1       | essay     | Essay 01 | Please write 200 words about Essay 01 |
      | Category 1       | essay     | Essay 02 | Please write 200 words about Essay 02 |
      | Category 2       | essay     | Essay 03 | Please write 200 words about Essay 03 |
    And I am on the "Quiz 1" "mod_quiz > Edit" page logged in as "teacher1"

    # Add questions from question bank using the Add menu.
    # Add Essay 03 from question bank.
    When I open the "last" add to quiz menu
    And I follow "from question bank"
    And I set the field "Select a category" to "Category 2"
    Then the "Add selected questions to the quiz" "button" should be disabled
    And I click on "Essay 03" "checkbox"
    And the "Add selected questions to the quiz" "button" should be enabled
    And I click on "Add to quiz" "link" in the "Essay 03" "table_row"
    And I should see "Editing quiz: Quiz 1"
    And I should see "Essay 03" on quiz page "1"

    # Add Essay 01 from question bank.
    And I open the "Page 1" add to quiz menu
    And I follow "from question bank"
    And I set the field "Select a category" to "Category 1"
    And I click on "Add to quiz" "link" in the "Essay 01" "table_row"
    And I should see "Editing quiz: Quiz 1"
    And I should see "Essay 03" on quiz page "1"
    And I should see "Essay 01" on quiz page "1"

    # Now repaginate.
    When I press "Repaginate"
    Then I should see "Repaginate with"
    And I set the field "menuquestionsperpage" to "1"
    When I press "Go"
    And I should see "Essay 03" on quiz page "1"
    And I should see "Essay 01" on quiz page "2"

    # Add a question to page 2.
    And I open the "Page 2" add to quiz menu
    And I follow "from question bank"
    And I click on "Add to quiz" "link" in the "Essay 02" "table_row"
    And I should see "Essay 03" on quiz page "1"
    And I should see "Essay 01" on quiz page "2"
    And I should see "Essay 02" on quiz page "2"
