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
 * Support for backup API
 *
 * @package    gradingform_btec
 * @copyright  2014 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines marking btec backup structures
 *
 * @package    gradingform_btec
 * @copyright  2014 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_gradingform_btec_plugin extends backup_gradingform_plugin {

    /**
     * Declares marking btec structures to append to the grading form definition
     * @return backup_plugin_element
     */
    protected function define_definition_plugin_structure() {

        // Append data only if the grand-parent element has 'method' set to 'btec'.
        $plugin = $this->get_plugin_element(null, '../../method', 'btec');

        // Create a visible container for our data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect our visible container to the parent.
        $plugin->add_child($pluginwrapper);

        // Define our elements.

        $criteria = new backup_nested_element('bteccriteria');

        $criterion = new backup_nested_element('bteccriterion', ['id'], [
            'sortorder', 'shortname', 'description', 'descriptionformat',
            'descriptionmarkers', 'descriptionmarkersformat', 'maxscore']);

        $comments = new backup_nested_element('bteccomments');

        $comment = new backup_nested_element('bteccomment', ['id'], [
            'sortorder', 'description', 'descriptionformat']);

        // Build elements hierarchy.

        $pluginwrapper->add_child($criteria);
        $criteria->add_child($criterion);
        $criteria->add_child($comments);
        $comments->add_child($comment);

        // Set sources to populate the data.

        $criterion->set_source_table('gradingform_btec_criteria',
                ['definitionid' => backup::VAR_PARENTID]);

        $comment->set_source_table('gradingform_btec_comments',
                ['definitionid' => backup::VAR_PARENTID]);

        // No need to annotate ids or files yet (one day when criterion definition supports
        // embedded files, they must be annotated here).

        return $plugin;
    }

    /**
     * Declares marking btec structures to append to the grading form instances
     * @return backup_plugin_element
     */
    protected function define_instance_plugin_structure() {

        // Append data only if the ancestor 'definition' element has 'method' set to 'btec'.
        $plugin = $this->get_plugin_element(null, '../../../../method', 'btec');

        // Create a visible container for our data.
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect our visible container to the parent.
        $plugin->add_child($pluginwrapper);

        // Define our elements.

        $fillings = new backup_nested_element('fillings');

        $filling = new backup_nested_element('filling', ['id'], [
            'criterionid', 'remark', 'remarkformat', 'score']);

        // Build elements hierarchy.

        $pluginwrapper->add_child($fillings);
        $fillings->add_child($filling);

        // Set sources to populate the data.

        $filling->set_source_table('gradingform_btec_fillings',
            ['instanceid' => backup::VAR_PARENTID]);

        // No need to annotate ids or files yet (one day when remark field supports
        // embedded fileds, they must be annotated here).

        return $plugin;
    }
}
