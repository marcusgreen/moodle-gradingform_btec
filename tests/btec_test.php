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
 * @package    gradingform_btec
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_btec;

defined('MOODLE_INTERNAL') || die();

use gradinform_moodle;
use moodle_page;
use moodlequickform_bteceditor;
use gradingform_btec_controller;

global $CFG;

require_once($CFG->dirroot . '/grade/grading/form/btec/bteceditor.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/renderer.php');
require_once($CFG->dirroot . '/grade/grading/form/btec/lib.php');

require_once($CFG->dirroot . '/lib/pagelib.php');

/**
 * Main class for testing the BTEC grading plugin
 * @package    gradingform_btec
 * @copyright  2022 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class btec_test extends \basic_testcase {
    /**
     * Test the behaviour moodlequickform_bteceditor::constructor method.
     *
     * @covers ::moodlequickform_bteceditor::constructor
     */
    public function test_created_form(): void {
        $form = new moodlequickform_bteceditor('testbteceditor', 'elementlabel', null);
        $type = $form->getElementTemplateType();
        $this->assertEquals('default', $type);
    }
    /**
     * Test the behaviour of calculate_btec_grade() method.
     *
     * @covers ::calculate_btec_grade
     */
    public function test_grade_calculation(): void {

        /* If you dont get any criteria you get an overal REFER */
        $criteria = ['criteria' => [
                ['score' => 0, 'level' => 'Pass'],
                ['score' => 0, 'level' => 'Pass'],
                ['score' => 0, 'level' => 'Merit'],
                ['score' => 0, 'level' => 'Merit'],
                ['score' => 0, 'level' => 'Distinction'],
                ['score' => 0, 'level' => 'Distinction'],
        ]];
        $controller = '';
        $data = '';
        $form = new \gradingform_btec_instance($controller, $data);
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::REFER, $levelmet);

        /* If you only get the P criteria you get an oveall PASS */
        $criteria = ['criteria' => [
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'pass'],
                ['score' => 0, 'level' => 'merit'],
                ['score' => 0, 'level' => 'merit'],
                ['score' => 0, 'level' => 'distinction'],
                ['score' => 0, 'level' => 'distinction'],
        ]];
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::PASS, $levelmet);

        /* if you get  everything except a Distinction you get a Merit */
        $criteria = ['criteria' => [
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'distinction'],
                ['score' => 0, 'level' => 'distinction'],
        ]];
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::MERIT, $levelmet);

        /* if you get all criteria you get an overall DISTINCTION */
        $criteria = ['criteria' => [
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'distinction'],
                ['score' => 1, 'level' => 'distinction'],
        ]];

        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::DISTINCTION, $levelmet);

        /* If there are no Merit criteria and you get all available
         * you get an overall DISTINCTION
         */
        $criteria = ['criteria' => [
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'distinction'],
                ['score' => 1, 'level' => 'dstinction'],
        ]];
        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::DISTINCTION, $levelmet);

        /* If you get all except one Pass criteria you
         * get an overall REFER
         */
        $criteria = ['criteria' => [
                ['score' => 0, 'level' => 'pass'],
                ['score' => 1, 'level' => 'pass'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'merit'],
                ['score' => 1, 'level' => 'distinction'],
                ['score' => 1, 'level' => 'distinction'],
        ]];

        $levelmet = $form->calculate_btec_grade($criteria);
        $this->assertEquals(gradingform_btec_controller::REFER, $levelmet);
    }
    /**
     * Test the behaviour of gradingform_btec_renderer() method.
     *
     * @covers ::gradingform_btec_renderer
     */
    public function test_created_renderer(): void {
        $PAGE = new moodle_page();
        $renderer = new \gradingform_btec_renderer($PAGE, 1);
        $options = [];
        $criterion = 1;
        $value = 1;
        $validationerrors = 0;
        $template = $renderer->criterion_template(1, $options, 'btec', $options, $criterion, $validationerrors);
        $this->assertIsString($template, 'template returned should be of type string');
    }
}
