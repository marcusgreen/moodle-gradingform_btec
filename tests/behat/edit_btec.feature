@gradingform @gradingform_btec
Feature: BTEC advanced grading forms can be created and edited
  In order to use and refine btec to grade students
  As a teacher
  I need to edit previously used btec forms

  @javascript
  Scenario: I can use rubrics to grade and edit them later updating students grades
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | BTEC Test assignment 1 name |
      | Description | Test assignment description |
      | Type | Scale |
      | Scale | BTEC |
      | Grading method | BTEC marking |
   And I go to "BTEC Test assignment 1 name" advanced grading definition page
   And I set the following fields to these values:
      |Name | Assignment 1 BTEC |
      | Description | BTEC test description |
   
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID1][shortname]" to "P1"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID1][description]" to "P2 Description"

    

  
  And I click on "Add criterion" "button"
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID2][shortname]" to "P2"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID2][description]" to "P2 description"




And I click on "Add criterion" "button"
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID3][shortname]" to "M1"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID3][description]" to "M1 description"

And I click on "Add criterion" "button"
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID4][shortname]" to "M2"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID4][description]" to "M2 description"

And I click on "Add criterion" "button"
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID5][shortname]" to "D1"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID5][description]" to "D1 description"

And I click on "Add criterion" "button"
    And I click on "Click to edit level" "text" 
    And I set the field "btec[criteria][NEWID6][shortname]" to "D2"
    And I click on "Requirements for completing" "text" in the "//tbody//tr[position()=last()]" "xpath_element"
    And I set the field "btec[criteria][NEWID6][description]" to "D2 description"

And I wait "20" seconds

And I click on "Save BTEC marking and make it ready" "button"

   

   r