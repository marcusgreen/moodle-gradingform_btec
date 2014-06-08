<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Contains the helper class for the select missing words question type tests.
 *
 * @package    btec
 * @copyright  2012 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;

require_once($CFG->dirroot . '/grade/grading/form/btec/bteceditor.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/renderer.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

require_once($CFG->dirroot . '/lib/pagelib.php');

class btec_test extends basic_testcase {

    public function test_created_form() {

        $form = new moodlequickform_bteceditor('testbteceditor', 'elementlabel', null);
        $type = $form->getElementTemplateType();
        $this->assertEquals('default', $type);
    }

    public function test_grade_calculation() {

        /* If you dont get any criteria you get an overal REFER */
        $criteria = array('criteria' => array(
                array('score' => 0, 'level' => 'Pass'),
                array('score' => 0, 'level' => 'Pass'),
                array('score' => 0, 'level' => 'Merit'),
                array('score' => 0, 'level' => 'Merit'),
                array('score' => 0, 'level' => 'Distinction'),
                array('score' => 0, 'level' => 'Distinction')
        ));
        $controller = '';
        $data = '';
        $form = new gradingform_btec_instance($controller, $data);
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::REFER, $levelmet);

        /* If you only get the P criteria you get an oveall PASS */
        $criteria = array('criteria' => array(
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'pass'),
                array('score' => 0, 'level' => 'merit'),
                array('score' => 0, 'level' => 'merit'),
                array('score' => 0, 'level' => 'distinction'),
                array('score' => 0, 'level' => 'distinction')
        ));
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::PASS, $levelmet);

        /* if you get  everything except a Distinction you get a Merit */
        $criteria = array('criteria' => array(
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'distinction'),
                array('score' => 0, 'level' => 'distinction')
        ));
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::MERIT, $levelmet);

        /* if you get all criteria you get an overall DISTINCTION */
        $criteria = array('criteria' => array(
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'distinction'),
                array('score' => 1, 'level' => 'distinction')
        ));

        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::DISTINCTION, $levelmet);

        /* If there are no Merit criteria and you get all available
         * you get an overall DISTINCTION
         */
        $criteria = array('criteria' => array(
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'distinction'),
                array('score' => 1, 'level' => 'dstinction')
        ));
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::DISTINCTION, $levelmet);

        /* If you get all except one Pass criteria you
         * get an overall REFER
         */
        $criteria = array('criteria' => array(
                array('score' => 0, 'level' => 'pass'),
                array('score' => 1, 'level' => 'pass'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'merit'),
                array('score' => 1, 'level' => 'distinction'),
                array('score' => 1, 'level' => 'distinction')
        ));

        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::REFER, $levelmet);
    }

    public function test_created_renderer() {
        $PAGE = new moodle_page();
        $renderer = new gradingform_btec_renderer($PAGE, 1);
        $options = array();
        $criterion = 1;
        $value = 1;
        $validationerrors = 0;
        $template = $renderer->criterion_template(1, $options, 'btec', $options, $criterion, $validationerrors);
        $this->assertInternalType('string', $template, 'template returned should be of type string');
    }
    public function test_renderer_validation() {
        $PAGE = new moodle_page();
        $renderer = new gradingform_btec_renderer($PAGE, 1);
        $options = array();
        $criterion = 1;
        $value = 1;
        $validationerrors = 0;
        $template = $renderer->criterion_template(1, $options, 'btec', $options, $criterion, $validationerrors);
        $this->assertInternalType('string', $template, 'template returned should be of type string');
    }

    public function test_validate_grading_element() {
        /*setup element value
        this->assertEquals  (validate_grading_element($elementvalue) */
    }
}