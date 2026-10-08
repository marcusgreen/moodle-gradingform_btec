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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * Manage one btec grading instance. Performs actions like update,copy,validate, subit etc
 *
 * @package    gradingform_btec
 * @copyright  2012 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_btec_instance extends gradingform_instance {
    /** @var array */
    protected $btec;

    /** @var array An array of validation errors */
    protected $validationerrors = [];

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_btec_fillings', ['instanceid' => $this->get_id()]);
    }

    /**
     * Duplicates the instance before editing (optionally substitutes raterid and/or itemid with
     * the specified values)
     *
     * @param int $raterid value for raterid in the duplicate
     * @param int $itemid value for itemid in the duplicate
     * @return int id of the new instance
     */
    public function copy($raterid, $itemid) {
        global $DB;
        $instanceid = parent::copy($raterid, $itemid);
        $currentgrade = $this->get_btec_filling();
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            $params = ['instanceid' => $instanceid, 'criterionid' => $criterionid,
                'score' => $record['score'], 'remark' => $record['remark'],
                'remarkformat' => $record['remarkformat']];
            $DB->insert_record('gradingform_btec_fillings', $params);
        }
        return $instanceid;
    }

    /**
     * Validates that btec is fully completed and contains valid grade on each criterion
     *
     * @param array $elementvalue value of element as came in form submit
     * @return boolean true if the form data is validated and contains no errors
     */
    public function validate_grading_element($elementvalue) {
        $criteria = $this->get_controller()->get_definition()->btec_criteria;
        if (
            !isset($elementvalue['criteria']) || !is_array($elementvalue['criteria']) ||
                count($elementvalue['criteria']) < count($criteria)
        ) {
            return false;
        }
        // Reset validation errors.
        $this->validationerrors = null;
        foreach ($criteria as $id => $criterion) {
            if (
                !isset($elementvalue['criteria'][$id]['score']) ||
                    !is_numeric($elementvalue['criteria'][$id]['score']) ||
                    $elementvalue['criteria'][$id]['score'] < 0
            ) {
                $this->validationerrors[$id]['score'] = $elementvalue['criteria'][$id]['score'];
            }
        }
        if (!empty($this->validationerrors)) {
            return false;
        }
        return true;
    }

    /**
     * Retrieves from DB and returns the data how this btec was filled
     *
     * @param bool $force whether to force DB query even if the data is cached
     * @return array
     */
    public function get_btec_filling($force = false) {
        global $DB;
        if ($this->btec === null || $force) {
            $records = $DB->get_records('gradingform_btec_fillings', ['instanceid' => $this->get_id()]);
            $this->btec = ['criteria' => []];
            foreach ($records as $record) {
                $level = $DB->get_records('gradingform_btec_criteria', ['id' => $record->criterionid]);
                $record->score = (float) $record->score; // Strip trailing 0.
                $this->btec['criteria'][$record->criterionid] = (array) $record;
                $this->btec['criteria'][$record->criterionid]['level'] = strtolower($level[$record->criterionid]->shortname);
            }
        }
        return $this->btec;
    }

    /**
     * Updates the instance with the data received from grading form. This function may be
     * called via AJAX when grading is not yet completed, so it does not change the
     * status of the instance.
     *
     * @param array $data
     */
    public function update($data) {
        global $DB;
        $currentgrade = $this->get_btec_filling();
        parent::update($data);

        foreach ($data['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $currentgrade['criteria'])) {
                $newrecord = ['instanceid' => $this->get_id(), 'criterionid' => $criterionid,
                    'score' => $record['score'], 'remarkformat' => FORMAT_MOODLE];

                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                $DB->insert_record('gradingform_btec_fillings', $newrecord);
            } else {
                $newrecord = ['id' => $currentgrade['criteria'][$criterionid]['id']];

                foreach (['score', 'remark'/* , 'remarkformat' TODO */] as $key) {
                    if (isset($record[$key]) && $currentgrade['criteria'][$criterionid][$key] != $record[$key]) {
                        $newrecord[$key] = $record[$key];
                    }
                }
                if (count($newrecord) > 1) {
                    $DB->update_record('gradingform_btec_fillings', $newrecord);
                }
            }
        }
        foreach ($currentgrade['criteria'] as $criterionid => $record) {
            if (!array_key_exists($criterionid, $data['criteria'])) {
                $DB->delete_records('gradingform_btec_fillings', ['id' => $record['id']]);
            }
        }
        $this->get_btec_filling(true);
    }

    /**
     *
     * This is called from outside btec grading so
     * it calls calculate_btec_grade to allow for the
     * creation of unit tests
     *
     * @return int
     */
    public function get_grade() {
        $grade = $this->get_btec_filling();
        return $this->calculate_btec_grade($grade);
    }

    /**
     * Works out the overall grade
     *
     * X initialises the level to assume it is not present.
     * X is checked later on to see if the level should be
     * ignored for not existing. Then the letters are
     * walked through to be set to P M or D if they do exist
     *
     * @param array $grade
     * @return int
     */
    public function calculate_btec_grade(array $grade) {

        $scaleletters = gradingform_btec_controller::get_scale_letters();
        $p = $scaleletters['p'];
        $m = $scaleletters['m'];
        $d = $scaleletters['d'];

        $levels = [$p => "X", $m => "X", $d => "X"];
        /* mark levels with an 1 if they are available */
        foreach ($grade['criteria'] as $record) {
            $letter = (substr($record['level'], 0, 1));
            if ($letter == $p) {
                $levels[$p] = 1;
            }
            if ($letter == $m) {
                $levels[$m] = 1;
            }
            if ($letter == $d) {
                $levels[$d] = 1;
            }
        }
        /* This records if all criteria at each level have been met
         * ready to use to check for the final overall grade in the
         * sequence of if statements that follow
         */
        foreach ($grade['criteria'] as $record) {
            $letter = (substr($record['level'], 0, 1));
            $score = $record['score'];
            /* if you dont get a P you cannot get anything higher */
            if (( $score == 0) && ($letter == $p)) {
                $levels[$p] = 0;
                $levels[$m] = 0;
                $levels[$d] = 0;
            }
            /* if you don't get an M you cannot get anything higher */
            if (( $score == 0) && ($letter == $m)) {
                $levels[$m] = 0;
                $levels[$d] = 0;
            }
            if (( $score == 0) && ($letter == $d)) {
                $levels[$d] = 0;
            }
            /* There is nothing higher than D so no third if block */
        }

        /* $levels["letter"]==1 means that all criteria at the level letter has been met
         * X indicates that there are no criteria at that level. $level met is the overall
         * grade achieved. You could make an argument for additional grades to indicate
         * if the overall grade means every available criteria has been met, e.g. PAM,MAM and DAM
         * for Pass (all met), Merit
         * */
        $levelmet = gradingform_btec_controller::REFER;
        if ($levels[$p] == 1) {
            $levelmet = gradingform_btec_controller::PASS;
        }
        if (($levels[$p] == 1) && ($levels[$m] == 1)) {
            $levelmet = gradingform_btec_controller::MERIT;
        }
        if (($levels[$p] == "X") && ($levels[$m] == 1)) {
            $levelmet = gradingform_btec_controller::MERIT;
        }
        if (($levels[$p] == 1) && ($levels[$m] == 1) && $levels[$d] == 1) {
            $levelmet = gradingform_btec_controller::DISTINCTION;
        }
        if (($levels[$p] == "X") && ($levels[$m] == 1) && $levels[$d] == 1) {
            $levelmet = gradingform_btec_controller::DISTINCTION;
        }
        if (($levels[$p] == 1) && ($levels[$m] == "X") && $levels[$d] == 1) {
            $levelmet = gradingform_btec_controller::DISTINCTION;
        }
        if (($levels[$p] == "X") && ($levels[$m] == "X") && $levels[$d] == 1) {
            $levelmet = gradingform_btec_controller::DISTINCTION;
        }
        return $levelmet;
    }

    /**
     * Returns html for form element of type 'grading'.
     *
     * @param moodle_page $page
     * @param MoodleQuickForm_grading $gradingformelement
     * @return string
     */
    public function render_grading_element($page, $gradingformelement) {
        if (!$gradingformelement->_flagFrozen) {
            $module = ['name' => 'gradingform_btec', 'fullpath' => '/grade/grading/form/btec/js/btec.js'];
            $page->requires->js_init_call('M.gradingform_btec.init', [
                ['name' => $gradingformelement->getName()]], true, $module);
            $mode = gradingform_btec_controller::DISPLAY_EVAL;
        } else {
            if ($gradingformelement->_persistantFreeze) {
                $mode = gradingform_btec_controller::DISPLAY_EVAL_FROZEN;
            } else {
                $mode = gradingform_btec_controller::DISPLAY_REVIEW;
            }
        }
        $criteria = $this->get_controller()->get_definition()->btec_criteria;
        $comments = $this->get_controller()->get_definition()->btec_comment;
        $options = $this->get_controller()->get_options();
        $value = $gradingformelement->getValue();
        $html = '';
        if ($value === null) {
            $value = $this->get_btec_filling();
        } else if (!$this->validate_grading_element($value)) {
            $html .= html_writer::tag(
                'div',
                get_string('btecnotcompleted', 'gradingform_btec'),
                ['class' => 'gradingform_btec-error']
            );
            if (!empty($this->validationerrors)) {
                foreach ($this->validationerrors as $id => $err) {
                    $a = new stdClass();
                    $a->criterianame = $criteria[$id]['shortname'];
                    $a->maxscore = $criteria[$id]['maxscore'];
                    $html .= html_writer::tag(
                        'div',
                        get_string('err_scoreinvalid', 'gradingform_btec', $a),
                        ['class' => 'gradingform_btec-error']
                    );
                }
            }
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::tag(
                'div',
                get_string('needregrademessage', 'gradingform_btec'),
                ['class' => 'gradingform_btec-regrade']
            );
        }
        $haschanges = false;
        if ($currentinstance) {
            $curfilling = $currentinstance->get_btec_filling();
            foreach ($curfilling['criteria'] as $criterionid => $curvalues) {
                $value['criteria'][$criterionid]['score'] = $curvalues['score'];
                $newremark = null;
                $newscore = null;
                if (isset($value['criteria'][$criterionid]['remark'])) {
                    $newremark = $value['criteria'][$criterionid]['remark'];
                }
                if (isset($value['criteria'][$criterionid]['score'])) {
                    $newscore = $value['criteria'][$criterionid]['score'];
                }
                if ($newscore != $curvalues['score'] || $newremark != $curvalues['remark']) {
                    $haschanges = true;
                }
            }
        }
        if ($this->get_data('isrestored') && $haschanges) {
            $html .= html_writer::tag(
                'div',
                get_string('restoredfromdraft', 'gradingform_btec'),
                ['class' => 'gradingform_btec-restored']
            );
        }
        $html .= html_writer::tag(
            'div',
            $this->get_controller()->get_formatted_description(),
            ['class' => 'gradingform_btec-description']
        );
        $html .= $this->get_controller()->get_renderer($page)->display_btec(
            $criteria,
            $comments,
            $options,
            $mode,
            $gradingformelement->getName(),
            $value,
            $this->validationerrors
        );
        return $html;
    }
    /**
     * TODO what does this function do?
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }
}
