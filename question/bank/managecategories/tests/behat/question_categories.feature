@qbank @qbank_managecategories @javascript
Feature: A teacher can put questions in categories in the question bank
  In order to organize my questions
  As a teacher
  I create and edit categories, move questions between them and filter question using category filter

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | weeks |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the following "activities" exist:
      | activity | name           | course | idnumber |
      | qbank    | Qbank 1        | C1     | qbank1   |
    And the following "question categories" exist:
      | contextlevel    | reference | questioncategory   | name               |
      | Activity module | qbank1    | Top                | top                |
      | Activity module | qbank1    | top                | Default for qbank1 |
      | Activity module | qbank1    | Default for qbank1 | Subcategory        |
      | Activity module | qbank1    | Default for qbank1 | Another subcat     |
      | Activity module | qbank1    | top                | Used category      |
      | Activity module | qbank1    | top                | Default & testing  |
    And the following "questions" exist:
      | questioncategory | qtype | name                      | questiontext                  |
      | Used category    | essay | Test question to be moved | Write about whatever you want |
      | Another subcat  | essay | Question 1                | Write about whatever you want |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage

  Scenario: A new question category can be created
    When I am on the "Qbank 1" "core_question > question categories" page
    And I follow "Add category"
    And I set the following fields to these values:
      | Name            | 'Test' & 'display'                                       |
      | Parent category | Default & testing                                        |
      | Category info   | Created for testing category, HTML entity & its encoding |
      | ID number       | newcatidnumber                                           |
    And I press "submitbutton"
    Then I should see "Default & testing"
    And I should see "ID number"
    And I should see "newcatidnumber"
    And I should see "(0)"
    And I should see "Created for testing category, HTML entity & its encoding" in the "'Test' & 'display'" "list_item"
    And I follow "Add category"
    And the "Parent category" select box should contain "'Test' & 'display' [newcatidnumber]"

  Scenario: A question category can be edited
    When I am on the "Qbank 1" "core_question > question categories" page
    # There have been bugs which only happened if a question category was not empty, so add a question.
    And the following "questions" exist:
      | questioncategory | qtype | name                                | questiontext                  |
      | Subcategory      | essay | Test question for renaming category | Write about whatever you want |
    And I click on "Edit this category" "link" in the "Subcategory" "list_item"
    And the field "parent" matches value "&nbsp;&nbsp;&nbsp;Default for qbank1"
    And I set the following fields to these values:
      | Name            | New name     |
      | Category info   | I was edited |
    And I press "Save changes"
    Then I should see "New name"
    And I should see "I was edited" in the "New name" "list_item"

  Scenario: An empty question category can be deleted
    When I am on the "Qbank 1" "core_question > question categories" page
    And I click on "Delete" "link" in the "Subcategory" "list_item"
    Then I should not see "Subcategory"

  Scenario: An non-empty question category can be deleted if you move the contents elsewhere
    When I am on the "Qbank 1" "core_question > question categories" page
    And I click on "Delete" "link" in the "Used category" "list_item"
    And I should see "The category 'Used category' contains 1 questions"
    And I select "Default for qbank1" from the "Category" singleselect
    And I press "Save in category"
    Then I should not see "Used category"
    And I follow "Add category"
    And I should see "Default for qbank1 (1)"

  @_file_upload
  Scenario: Multi answer questions with their child questions can be moved to another category when the current category is deleted
    When I am on the "Qbank 1" "core_question > question import" page
    And I set the field "id_format_xml" to "1"
    And I upload "question/format/xml/tests/fixtures/multianswer.xml" file to "Import" filemanager
    And I press "id_submitbutton"
    And I press "Continue"
    And I am on the "Qbank 1" "core_question > question categories" page
    And I click on "Delete" "link" in the "Default for Test images in backup" "list_item"
    And I should see "The category 'Default for Test images in backup' contains 1 questions"
    And I select "Used category" from the "Category" singleselect
    And I press "Save in category"
    Then I should not see "Default for Test images in backup"
    And I follow "Add category"
    And I should see "Used category (2)"

  Scenario: Filter questions by category and subcategories
    When I am on the "Qbank 1" "core_question > question bank" page
    And I apply question bank filter "Category" with value "Default for qbank1"
    Then I should not see "Question 1"
    When I set the field "Also show questions from subcategories" to "1"
    And I click on "Apply filters" "button"
    Then I should see "Question 1" in the "categoryquestions" "table"
