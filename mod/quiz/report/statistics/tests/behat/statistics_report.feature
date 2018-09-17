@mod @mod_quiz @quiz @quiz_statistics
Feature: Statistics Report
  In order to evaluate students attempts
  As a teacher
  I need to quiz with attempts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student0@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext        |
      | Test questions   | truefalse | Question A | This is question 01 |
      | Test questions   | truefalse | Question B | This is question 02 |
      | Test questions   | truefalse | Question C | This is question 03 |
    And the following "activities" exist:
      | activity   | name   | course | idnumber |
      | quiz       | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question   | page |
      | Question A | 1    |
      | Question B | 1    |
      | Question C | 2    |
    And I attempt quiz "Quiz 1" as "student1" with the following responses:
      | slot | response |
      |   1  | True     |
      |   2  | False    |
      |   3  | False    |

  @javascript
  Scenario: view stats
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Quiz 1"
    And I navigate to "Results > Statistics" in current page administration
    And "1" row "Attempts" column of "questionstatistics" table should contain "1"
    And "1" row "Random guess score" column of "questionstatistics" table should contain "50.00 %"
    And "1" row "Intended weight" column of "questionstatistics" table should contain "33.33%"
