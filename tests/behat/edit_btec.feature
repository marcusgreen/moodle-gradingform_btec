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

 And I click on "Save BTEC marking and make it ready" "button"

And I wait "20" seconds
   

   # # Editing a rubric with significant changes.
   # And I log in as "teacher1"
   # And I follow "Course 1"
   # And I go to "Test assignment 1 name" advanced grading definition page
   # And I click on "Move down" "button" in the "Criterion 2" "table_row"
   # And I replace "1" rubric level with "11" in "Criterion 1" criterion
   # And I press "Save"
   # And I should see "You are about to save significant changes to a rubric that has already been used for grading. The gradebook value will be unchanged, but the rubric will be hidden from students until their item is regraded."
   # And I press "Continue"
   # And I log out
   # # Check that the student doesn't see the grade.
   # And I log in as "student1"
   # And I follow "Course 1"
   # And I follow "Test assignment 1 name"
   # And I should see "22.62" in the ".feedback" "css_element"
   # And the level with "20" points is not selected for the rubric criterion "Criterion 1"
   # And I log out
   # # Regrade student.
   # And I log in as "teacher1"
   # And I follow "Course 1"
   # And I follow "Test assignment 1 name"
   # And I go to "Student 1" "Test assignment 1 name" activity advanced grading page
   # And I should see "The rubric definition was changed after this student had been graded. The student can not see this rubric until you check the rubric and update the grade."
   # And I save the advanced grading form
   # And I log out
   # # Check that the student sees the grade again.
   # And I log in as "student1"
   # And I follow "Course 1"
   # And I follow "Test assignment 1 name"
   # And I should see "12.16" in the ".feedback" "css_element"
   # And the level with "20" points is not selected for the rubric criterion "Criterion 1"
   # # Hide all rubric info for students
   # And I log out
   # And I log in as "teacher1"
   # And I follow "Course 1"
   # And I go to "Test assignment 1 name" advanced grading definition page
   # And I set the field "Allow users to preview rubric used in the module (otherwise rubric will only become visible after grading)" to ""
   # And I set the field "Display rubric description during evaluation" to ""
   # And I set the field "Display rubric description to those being graded" to ""
   