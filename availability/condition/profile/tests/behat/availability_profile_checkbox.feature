@availability @availability_profile
Feature: Conditional availability based on checkbox custom profile fileds
  In order to control student access to activities
  As a teacher
  I need to set profile conditions which prevent student access

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name           |
      | checkbox | flag      | Profile flag   |
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | course | name                                   | availability                                            |
      | page     | C1     | Page for users with the flag equal 1   | {"op":"&","c":[{"type":"profile","cf":"flag","op":"isequalto","v":"1"}],"showc":[false]} |
      | page     | C1     | Page for users with the flag equal 0   | {"op":"&","c":[{"type":"profile","cf":"flag","op":"isequalto","v":"0"}],"showc":[false]} |
      | page     | C1     | Page for users with the flag not empty | {"op":"&","c":[{"type":"profile","cf":"flag","op":"isnotempty"}],"showc":[false]}        |
      | page     | C1     | Page for users with the flag empty     | {"op":"&","c":[{"type":"profile","cf":"flag","op":"isempty"}],"showc":[false]}           |

  Scenario: Use with the field not set at all
    Given the following "users" exist:
      | username |
      | student  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    When I am on the "Course 1" course page logged in as student
    Then I should not see "Page for users with the flag equal 1"
    And I should see "Page for users with the flag equal 0"
    And I should not see "Page for users with the flag not empty"
    And I should see "Page for users with the flag empty"

  Scenario: Use with the field set to be not checked
    Given the following "users" exist:
      | username | profile_field_flag |
      | student  | 0                  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    When I am on the "Course 1" course page logged in as student
    Then I should not see "Page for users with the flag equal 1"
    And I should see "Page for users with the flag equal 0"
    And I should not see "Page for users with the flag not empty"
    And I should see "Page for users with the flag empty"

  Scenario: Use with the field not set
    Given the following "users" exist:
      | username | profile_field_flag |
      | student  | 1                  |
    And the following "course enrolments" exist:
      | user    | course | role    |
      | student | C1     | student |
    When I am on the "Course 1" course page logged in as student
    Then I should see "Page for users with the flag equal 1"
    And I should not see "Page for users with the flag equal 0"
    And I should see "Page for users with the flag not empty"
    And I should not see "Page for users with the flag empty"
