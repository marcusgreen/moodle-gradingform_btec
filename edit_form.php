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
 * The form used at the btec editor page is defined here
 *
 * @package    gradingform_btec
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->libdir . '/grade/constants.php');
require_once(dirname(__FILE__).'/bteceditor.php');
MoodleQuickForm::registerElementType('bteceditor', $CFG->dirroot.'/grade/grading/form/btec/bteceditor.php',
    'moodlequickform_bteceditor');

/**
 * Defines the btec edit form
 *
 * @package    gradingform_btec
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_btec_editbtec extends moodleform {

    /**
     * Form element definition
     */
    public function definition() {
        global $DB;
        $form = $this->_form;
        $form->addElement('hidden', 'areaid');
        $form->setType('areaid', PARAM_INT);
        $form->addElement('hidden', 'returnurl');
        $form->setType('returnurl', PARAM_RAW);

        $form->addElement('header', 'btecheader', get_string('gradeheading' , 'gradingform_btec'));

        // Name.
        $form->addElement('text', 'name', get_string('name', 'gradingform_btec'), array('size' => 52));
        $form->addHelpButton('name', 'btecgrading', 'gradingform_btec');
        /*check grade type is scale and the scale is BTEC, if not present a warning */
        $areaid = optional_param('areaid', 0, PARAM_INT);
        $returnurl = optional_param('returnurl', 0, PARAM_TEXT);
        /*find the scale to check it is BTEC */
        $gradeitem = $DB->get_record('grade_items', array('iteminstance' => $areaid,
            'itemmodule' => 'assign', 'itemtype' => 'mod'), 'gradetype,scaleid', false);
        /* lookup the id of the BTEC scale */
        $btecscale = $DB->get_record('scale', array('name' => 'BTEC'), 'id', false);
        if (($gradeitem !== false) && ($gradeitem->scaleid != $btecscale->id)) {
            /* Get the id for assign, probably always 1 */
            $assignmodule = $DB->get_record('modules', array('name' => 'assign'), 'id');
            $cm = $DB->get_record('course_modules', array('instance' => $areaid, 'module' => $assignmodule->id), 'id');
            $form->addElement('static', 'error', get_string('warning', 'gradingform_btec'),
                    '<span class="error">' . get_string('scaletypewarning_text', 'gradingform_btec', $cm->id) . '</span>');
        }

        $form->addRule('name', get_string('required'), 'required');
        $form->setType('name', PARAM_TEXT);
        // Description.
        $options = gradingform_btec_controller::description_form_field_options($this->_customdata['context']);
        $form->addElement('editor', 'description_editor', get_string('description'), array('rows' => 6), $options);
        $form->setType('description_editor', PARAM_RAW);
        /* btec completion status. */
        $choices = array();
        $choices[gradingform_controller::DEFINITION_STATUS_DRAFT]    = html_writer::tag('span',
            get_string('statusdraft', 'core_grading'), array('class' => 'status draft'));
        $choices[gradingform_controller::DEFINITION_STATUS_READY]    = html_writer::tag('span',
            get_string('statusready', 'core_grading'), array('class' => 'status ready'));
        $form->addElement('select', 'status', get_string('btecstatus', 'gradingform_btec'), $choices)->freeze();

        /* btec editor. */
        $form->addElement('bteceditor', 'btec', get_string('pluginname', 'gradingform_btec'));

        $form->addHelpButton('btec', 'gradelevels', 'gradingform_btec');

        $form->setType('btec', PARAM_RAW);

        $buttonarray = array();
        $buttonarray[] = &$form->createElement('submit', 'savebtec', get_string('savebtec', 'gradingform_btec'));
        if ($this->_customdata['allowdraft']) {
            $buttonarray[] = &$form->createElement('submit', 'savebtecdraft', get_string('savebtecdraft', 'gradingform_btec'));
        }
        $editbutton = &$form->createElement('submit', 'editbtec', ' ');
        $editbutton->freeze();
        $buttonarray[] = &$editbutton;
        $buttonarray[] = &$form->createElement('cancel');
        $form->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $form->closeHeaderBefore('buttonar');
    }

    /**
     * Setup the form depending on current values. This method is called after definition(),
     * data submission and set_data().
     * All form setup that is dependent on form values should go in here.
     *
     * We remove the element status if there is no current status (i.e. btec is only being created)
     * so the users do not get confused
     */
    public function definition_after_data() {
        $form = $this->_form;
        $el = $form->getElement('status');
        if (!$el->getValue()) {
            $form->removeElement('status');
        } else {
            $vals = array_values($el->getValue());
            if ($vals[0] == gradingform_controller::DEFINITION_STATUS_READY) {
                $this->findbutton('savebtec')->setValue(get_string('save', 'gradingform_btec'));
            }
        }
    }

    /**
     * Form validation.
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $err = parent::validation($data, $files);
        $err = array();
        $form = $this->_form;
        $btecel = $form->getElement('btec');
        if ($btecel->non_js_button_pressed($data['btec'])) {
            // If JS is disabled and button such as 'Add criterion' is pressed - prevent from submit.
            $err['btecdummy'] = 1;
        } else if (isset($data['editbtec'])) {
            // Continue editing.
            $err['btecdummy'] = 1;
        } else if ((isset($data['savebtec']) && $data['savebtec']) ||
                   (isset($data['savebtecdraft']) && $data['savebtecdraft'])) {
            // If user attempts to make btec active - it needs to be validated.
            if ($btecel->validate($data['btec']) !== false) {
                $err['btecdummy'] = 1;
            }
        }
        return $err;
    }

    /**
     * Return submitted data if properly submitted or returns NULL if validation fails or
     * if there is no submitted data.
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        $data = parent::get_data();
        if (!empty($data->savebtec)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_READY;
        } else if (!empty($data->savebtecdraft)) {
            $data->status = gradingform_controller::DEFINITION_STATUS_DRAFT;
        }
        return $data;
    }

    /**
     * Check if there are changes in the btec and it is needed to ask user whether to
     * mark the current grades for re-grading. User may confirm re-grading and continue,
     * return to editing or cancel the changes
     *
     * @param gradingform_btec_controller $controller
     */
    public function need_confirm_regrading($controller) {
        $data = $this->get_data();
        if (isset($data->btec['regrade'])) {
            // We have already displayed the confirmation on the previous step.
            return false;
        }
        if (!isset($data->savebtec) || !$data->savebtec) {
            // We only need confirmation when button 'Save btec' is pressed.
            return false;
        }
        if (!$controller->has_active_instances()) {
            // Nothing to re-grade, confirmation not needed.
            return false;
        }
        $changelevel = $controller->update_or_check_btec($data);
        if ($changelevel == 0) {
            // No changes in the btec, no confirmation needed.
            return false;
        }

        // Freeze form elements and pass the values in hidden fields.
        // TODO description_editor does not freeze the normal way!
        $form = $this->_form;
        foreach (array('btec', 'name') as $fieldname) {
            $el =& $form->getElement($fieldname);
            $el->freeze();
            $el->setPersistantFreeze(true);
            if ($fieldname == 'btec') {
                $el->add_regrade_confirmation($changelevel);
            }
        }

        // Replace button text 'savebtec' and unfreeze 'Back to edit' button.
        $this->findbutton('savebtec')->setValue(get_string('continue'));
        $el =& $this->findbutton('editbtec');
        $el->setValue(get_string('backtoediting', 'gradingform_btec'));
        $el->unfreeze();

        return true;
    }

    /**
     * Returns a form element (submit button) with the name $elementname
     *
     * @param string $elementname
     * @return HTML_QuickForm_element
     */
    protected function &findbutton($elementname) {
        $form = $this->_form;
        $buttonar =& $form->getElement('buttonar');
        $elements =& $buttonar->getElements();
        foreach ($elements as $el) {
            if ($el->getName() == $elementname) {
                return $el;
            }
        }
        return null;
    }
}
