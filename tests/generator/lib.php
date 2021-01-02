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
 * Generator for the gradingforum_guide plugin.
 *
 * @package    gradingform_btec
 * @category   test
 * @copyright  2020 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/btec.php');
require_once(__DIR__ . '/criterion.php');

use tests\gradingform_btec\generator\btec;
use tests\gradingform_btec\generator\criterion;

/**
 * Generator for the btec_grading plugintype.
 *
 * @package    gradingform_btec
 * @category   test
 * @copyright  2020 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_btec_generator extends component_generator_base {

    /**
     * Create an instance of a marking btec_grading.
     *
     * @param context $context
     * @param string $component
     * @param string $area
     * @param string $name
     * @param string $description
     * @param array $criteria The list of criteria to add to the generated guide
     * @return gradingform_btec_controller
     */
    public function create_instance(
        context $context,
        string $component,
        string $area,
        string $name,
        string $description,
        array $criteria
    ): gradingform_btec_controller {
        global $USER;

        if ($USER->id === 0) {
            throw new \coding_exception('Creation of a btec must currently be run as a user.');
        }

        // Fetch the controller for this context/component/area.
        $generator = \testing_util::get_data_generator();
        $gradinggenerator = $generator->get_plugin_generator('core_grading');
        $controller = $gradinggenerator->create_instance($context, $component, $area, 'btec');

        // Generate a definition for the supplied guide.
        $btec = $this->get_btec($name, $description);
        foreach ($criteria as $name => $options) {
            $btec->add_criteria($this->get_criterion(
                $name,
                $options['sortorder'],
                $options['shortname'],
                $options['description']
            ));
        }

        // Update the controller wih the btec definition.
        $controller->update_definition($btec->get_definition());

        return $controller;
    }

    /**
     * Get a new btec for use with the btec controller.
     *
     * Note: This is just a helper class used to build a new definition. It does not persist the data.
     *
     * @param string $name
     * @param string $description
     * @return generator_btec
     */
    protected function get_btec(string $name, string $description): btec {
        return new \tests\gradingform_btec\generator\btec($name, $description);
    }

    /**
     * Get a new criterion for use with a guide.
     *
     * Note: This is just a helper class used to build a new definition. It does not persist the data.
     *
     * @param string $shortname The shortname for the criterion
     * @param string $description The description for the criterion
         * @return criterion
     */
    protected function get_criterion(
        string $name,
        string $sortorder,
        string $shortname,
        string $description
    ): criterion {
        return new criterion($name, $sortorder, $shortname, $description);
    }

    /**
     * Given a controller instance, fetch the level and criterion information for the specified values.
     *
     * @param gradingform_controller $controller
     * @param string $shortname The shortname to match the criterion on
     * @return stdClass
     */
    public function get_criterion_for_values(gradingform_controller $controller, string $shortname): ?stdClass {
        $definition = $controller->get_definition();
        $criteria = $definition->btec_criteria;

        $criterion = array_reduce($criteria, function($carry, $criterion) use ($shortname) {
            if ($criterion['shortname'] === $shortname) {
                $carry = (object) $criterion;
            }

            return $carry;
        }, null);

        return $criterion;
    }

    /**
     * Get submitted form data
     *
     * @param gradingform_guide_controller $controller
     * @param int $itemid
     * @param array $values A set of array values where the array key is the name of the criterion, and the value is an
     * array with the desired score, and any remark.
     */
    public function get_submitted_form_data(gradingform_guide_controller $controller, int $itemid, array $values): array {
        $result = [
            'itemid' => $itemid,
            'criteria' => [],
        ];
        foreach ($values as $criterionname => ['score' => $score, 'remark' => $remark]) {
            $criterion = $this->get_criterion_for_values($controller, $criterionname);
            $result['criteria'][$criterion->id] = [
                'score' => $score,
                'remark' => $remark,
            ];
        }

        return $result;
    }

    /**
     * Generate a guide controller with sample data required for testing of this class.
     *
     * @param context_module $context
     * @return gradingform_guide_controller
     */
    public function get_test_btec(
        context_module $context,
        string $component = 'mod_assign',
        string $areaname = 'submission'
    ): gradingform_guide_controller {
        $generator = \testing_util::get_data_generator();
        $gradinggenerator = $generator->get_plugin_generator('core_grading');
        $controller = $gradinggenerator->create_instance($context, $component, $areaname, 'guide');

        $generator = \testing_util::get_data_generator();
        $btecgenerator = $generator->get_plugin_generator('gradingform_btec');

        $btec = $btecgenerator->get_btec('testbtec', 'Description text');

        $btec->add_criteria($btecgenerator->get_criterion(
            'Spelling mistakes',
            'Full marks will be given for no spelling mistakes.',
            'Deduct 5 points per spelling mistake made.',
            25
        ));
        $btec->add_criteria($btecgenerator->get_criterion(
            'Pictures',
            'Full marks will be given for including 3 pictures.',
            'Give 5 points for each picture present',
            15
        ));
        $controller->update_definition($btec->get_definition());

        return $controller;
    }

    /**
     * Fetch a set of sample data.
     *
     * @param gradingform_btec_controller $controller
     * @param int $itemid
     * @param float $spellingscore
     * @param string $spellingremark
     * @param float $picturescore
     * @param string $pictureremark
     * @return array
     */
    public function get_test_form_data(
        gradingform_btec_controller $controller,
        int $itemid,
        float $spellingscore,
        string $spellingremark,
        float $picturescore,
        string $pictureremark
    ): array {
        $generator = \testing_util::get_data_generator();
        $btecgenerator = $generator->get_plugin_generator('gradingform_btec');
        return $btecgenerator->get_submitted_form_data($controller, $itemid, [
            'Spelling mistakes' => [
                'score' => $spellingscore,
                'remark' => $spellingremark,
            ],
            'Pictures' => [
                'score' => $picturescore,
                'remark' => $pictureremark,
            ],
        ]);
    }
}
