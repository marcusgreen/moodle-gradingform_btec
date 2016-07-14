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
 * Contains the btec grading form renderer in all of its glory
 *
 * @package    gradingform_btec
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class gradingform_btec_renderer extends plugin_renderer_base {

    /**
     * This function returns html code for displaying criterion. Depending on $mode it may be the
     * code to edit btec, to preview the btec, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_btec() to display the whole btec, or it can be
     * called by itself to return a template used by JavaScript to add new empty criteria to the
     * btec being designed.
     * In this case it will use macros like {NAME}, {LEVELS}, {CRITERION-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode btec display mode, one of gradingform_btec_controller::DISPLAY_* {@link gradingform_btec_controller()}
     * @param array $options An array of options.
     *      showmarkspercriterionstudents (bool) If true adds the current score to the display
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array $criterion criterion data
     * @param array $value (only in view mode) teacher's feedback on this criterion
     * @param array $validationerrors An array containing validation errors to be shown
     * @return string
     */
    public function criterion_template($mode, array $options, $elementname =
            '{NAME}', $criterion = null, $value = null, $validationerrors = null) {
        if ($criterion === null || !is_array($criterion) || !array_key_exists('id', $criterion)) {
            $criterion = array('id' => '{CRITERION-id}',
                'description' => '{CRITERION-description}',
                'sortorder' => '{CRITERION-sortorder}',
                'class' => '{CRITERION-class}',
                'descriptionmarkers' => '{CRITERION-descriptionmarkers}',
                'shortname' => '{CRITERION-shortname}',
                'maxscore' => '{CRITERION-maxscore}');
        } else {
            foreach (array('sortorder', 'description', 'class', 'shortname', 'descriptionmarkers', 'maxscore') as $key) {
                // Set missing array elements to empty strings to avoid warnings.
                if (!array_key_exists($key, $criterion)) {
                    $criterion[$key] = '';
                }
            }
        }
        $criteriontemplate = html_writer::start_tag('tr', array('class' => 'criterion' . $criterion['class'],
                    'id' => '{NAME}-criteria-{CRITERION-id}'));
        $descriptionclass = 'description';
        /* Added for debugging purposes */
          /*
          switch ($mode) {
          case gradingform_btec_controller::DISPLAY_EDIT_FULL:
          echo ' editor editable';
          break;
          case gradingform_btec_controller::DISPLAY_EDIT_FROZEN:
          echo ' editor frozen';
          break;
          case gradingform_btec_controller::DISPLAY_PREVIEW:
          case gradingform_btec_controller::DISPLAY_PREVIEW_GRADED:
          echo ' editor preview';
          break;
          case gradingform_btec_controller::DISPLAY_EVAL:
          echo ' evaluate editable';
          break;
          case gradingform_btec_controller::DISPLAY_EVAL_FROZEN:
          echo ' evaluate frozen';
          break;
          case gradingform_btec_controller::DISPLAY_REVIEW:
          echo ' review';
          break;
          case gradingform_btec_controller::DISPLAY_VIEW:
          echo ' view';
          break;
          }
          */
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL) {
            $criteriontemplate .= html_writer::start_tag('td', array('class' => 'controls'));
            foreach (array('moveup', 'delete', 'movedown') as $key) {
                $value = get_string('criterion' . $key, 'gradingform_btec');
                $button = html_writer::empty_tag('input', array('type' => 'submit',
                            'name' => '{NAME}[criteria][{CRITERION-id}][' . $key . ']',
                            'id' => '{NAME}-criteria-{CRITERION-id}-' . $key, 'value' => $value,
                            'title' => $value, 'tabindex' => -1));
                $criteriontemplate .= html_writer::tag('div', $button, array('class' => $key));
            }
            $criteriontemplate .= html_writer::end_tag('td'); // Controls.
            $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                        'name' => '{NAME}[criteria][{CRITERION-id}][sortorder]', 'value' => $criterion['sortorder']));

            /* mavg1 shortname gets reused on the next line */
            $shortname = html_writer::empty_tag('input', array('type' => 'text',
                        'name' => '{NAME}[criteria][{CRITERION-id}][shortname]', 'id' => 'shortname',
                        'style' => '', 'class' => 'criterionname',
                        'value' => htmlspecialchars($criterion['shortname'])));

            $shortname = html_writer::tag('div', $shortname, array('name' => 'criterionshortname', 'class' => 'criterionname'));

            $description = html_writer::tag('textarea', htmlspecialchars($criterion['description']),
                    array('name' => '{NAME}[criteria][{CRITERION-id}][description]',
                        'id' => '{NAME}[criteria][{CRITERION-id}][description]',
                        'cols' => '65', 'rows' => '5'));
            $description = html_writer::tag('div', $description, array('class' => 'criteriondesc'));
        } else {
            if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN) {
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[criteria][{CRITERION-id}][sortorder]', 'value' => $criterion['sortorder']));
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[criteria][{CRITERION-id}][shortname]', 'value' => $criterion['shortname']));
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[criteria][{CRITERION-id}][description]', 'value' => $criterion['description']));
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[criteria][{CRITERION-id}][descriptionmarkers]',
                            'value' => $criterion['descriptionmarkers']));
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[criteria][{CRITERION-id}][maxscore]', 'value' => $criterion['maxscore']));
            } else if ($mode == gradingform_btec_controller::DISPLAY_EVAL ||
                    $mode == gradingform_btec_controller::DISPLAY_VIEW) {
                $descriptionclass = 'descriptionreadonly';
            }

            $shortname = html_writer::tag('div', $criterion['shortname'],
                    array('class' => 'criterionshortname',
                        'name' => '{NAME}[criteria][{CRITERION-id}][shortname]'));
            $descmarkerclass = '';
            $descstudentclass = '';
            if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
                if (!get_user_preferences('gradingform_btec-showmarkerdesc', true)) {
                    $descmarkerclass = ' hide';
                }
                if (!get_user_preferences('gradingform_btec-showstudentdesc', true)) {
                    $descstudentclass = ' hide';
                }
            }
            $description = html_writer::tag('div', $criterion['description'],
                    array('class' => 'criteriondescription' . $descstudentclass,
                    'name' => '{NAME}[criteria][{CRITERION-id}][descriptionmarkers]'));
            $descriptionmarkers = html_writer::tag('div', $criterion['descriptionmarkers'],
                    array('class' => 'criteriondescriptionmarkers' . $descmarkerclass,
                        'name' => '{NAME}[criteria][{CRITERION-id}][descriptionmarkers]'));
            $maxscore = html_writer::tag('div', $criterion['maxscore'],
                    array('class' => 'criteriondescriptionscore',
                        'name' => '{NAME}[criteria][{CRITERION-id}][maxscore]'));
        }

        if (isset($criterion['error_description'])) {
            $descriptionclass .= ' error';
        }

        $title = html_writer::tag('label', get_string('criterion', 'gradingform_btec'),
                array('for' => '{NAME}[criteria][{CRITERION-id}][shortname]', 'class' => 'criterionnamelabel'));
        $title .= $shortname;
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL ||
                $mode == gradingform_btec_controller::DISPLAY_PREVIEW) {
            /* change descriptionstudents for criteriarequirement */
            $title .= html_writer::tag('label', get_string('criteriarequirements', 'gradingform_btec'),
                    array('for' => '{NAME}[criteria][{CRITERION-id}][description]'));
            $title .= $description;
        } else if ($mode == gradingform_btec_controller::DISPLAY_PREVIEW_GRADED ||
                $mode == gradingform_btec_controller::DISPLAY_VIEW) {
            $title .= $description;
        } else {
            $title .= $description . $descriptionmarkers;
        }

        $currentremark = '';
        $currentscore = '';
        if (isset($value['remark'])) {
            $currentremark = $value['remark'];
        }
        if (isset($value['score'])) {
            $currentscore = $value['score'];
        }
        if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
            /* Insert yes/no achieved marking options */
            if (isset($currentscore)) {
                /* the No column */
                $prefix = '';
                $checked = '';
                if ($currentscore == 0) {
                    $prefix = 'checked';
                    $checked = 'checked';
                }
                $radio = html_writer::tag('input', get_string('no', 'gradingform_btec') . " ", array('type' => 'radio',
                            'name' => '{NAME}[criteria][{CRITERION-id}][score]',
                            'class' => 'markno',
                            'value' => 0, $prefix => $checked));
                $criteriontemplate .= html_writer::tag('td', $radio, array('class' => 'markingbtecyesno'));
                /* the Yes column */
                $prefix = '';
                $checked = '';
                if ($currentscore == 1) {
                    $prefix = 'checked';
                    $checked = 'checked';
                }
                $radio = html_writer::tag('input', get_string('yes', 'gradingform_btec'), array('type' => 'radio',
                            'name' => '{NAME}[criteria][{CRITERION-id}][score]',
                            'class' => 'markyes',
                            'value' => 1, $prefix => $checked));
                $criteriontemplate .= html_writer::tag('td', $radio, array('class' => 'markingbtecyesno'));
            } else {
                $radio = html_writer::tag('input', get_string('no', 'gradingform_btec') . " ", array('type' => 'radio',
                            'name' => '{NAME}[criteria][{CRITERION-id}][score]',
                            'class' => 'markno',
                            'value' => 0, 'checked' => 'checked'));
                $criteriontemplate .= html_writer::tag('td', $radio, array('class' => 'markingbtecyesno'));
                $radio = html_writer::tag('input', get_string('yes', 'gradingform_btec'), array('type' => 'radio',
                            'name' => '{NAME}[criteria][{CRITERION-id}][score]',
                            'class' => 'markyes',
                            'value' => 1));
                $criteriontemplate .= html_writer::tag('td', $radio, array('class' => 'markingbtecyesno'));
            }
        }

        $criteriontemplate .= html_writer::tag('td', $title, array('class' => $descriptionclass,
                    'id' => '{NAME}-criteria-{CRITERION-id}-shortname'));

        $currentremark = '';
        $currentscore = '';
        if (isset($value['remark'])) {
            $currentremark = $value['remark'];
        }
        if (isset($value['score'])) {
            $currentscore = $value['score'];
        }

        if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
            $scoreclass = '';
            if (!empty($validationerrors[$criterion['id']]['score'])) {
                $scoreclass = 'error';
                $currentscore = $validationerrors[$criterion['id']]['score']; // Show invalid score in form.
            }
            $input = html_writer::tag('textarea', htmlspecialchars($currentremark),
                    array('name' => '{NAME}[criteria][{CRITERION-id}][remark]',
                        'cols' => '65', 'rows' => '5',
                        'class' => 'markingbtecremark'));
            $criteriontemplate .= html_writer::tag('td', $input, array('class' => 'remark'));
        } else if ($mode == gradingform_btec_controller::DISPLAY_EVAL_FROZEN) {
            $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                        'name' => '{NAME}[criteria][{CRITERION-id}][remark]', 'value' => $currentremark));
        } else if ($mode == gradingform_btec_controller::DISPLAY_REVIEW ||
                $mode == gradingform_btec_controller::DISPLAY_VIEW) {
            $criteriontemplate .= html_writer::tag('td', $currentremark, array('class' => 'remark'));
            if (!empty($options['showmarkspercriterionstudents'])) {
                /* replace score out of with in/complete */
                if ($currentscore) {
                    $criteriontemplate .= html_writer::tag('td', 'Completed', array('class' => 'score'));
                } else {
                    $criteriontemplate .= html_writer::tag('td', 'Incomplete', array('class' => 'score'));
                }
            }
        }
        $criteriontemplate .= html_writer::end_tag('tr'); // Criterion.
        $criteriontemplate = str_replace('{NAME}', $elementname, $criteriontemplate);
        $criteriontemplate = str_replace('{CRITERION-id}', $criterion['id'], $criteriontemplate);
        return $criteriontemplate;
    }

    /**
     * This function returns html code for displaying criterion. Depending on $mode it may be the
     * code to edit btec, to preview the btec, to evaluate somebody or to review the evaluation.
     *
     * This function may be called from display_btec() to display the whole btec, or it can be
     * called by itself to return a template used by JavaScript to add new empty criteria to the
     * btec being designed.
     * In this case it will use macros like {NAME}, {LEVELS}, {CRITERION-id}, etc.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode btec display mode, one of gradingform_btec_controller::DISPLAY_* {@link gradingform_btec_controller}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array $comment
     * @return string
     */
    public function comment_template($mode, $elementname = '{NAME}', $comment = null) {
        if ($comment === null || !is_array($comment) || !array_key_exists('id', $comment)) {
            $comment = array('id' => '{COMMENT-id}',
                'description' => '{COMMENT-description}',
                'sortorder' => '{COMMENT-sortorder}',
                'class' => '{COMMENT-class}');
        } else {
            foreach (array('sortorder', 'description', 'class') as $key) {
                // Set missing array elements to empty strings to avoid warnings.
                if (!array_key_exists($key, $comment)) {
                    $criterion[$key] = '';
                }
            }
        }
        $criteriontemplate = html_writer::start_tag('tr', array('class' => 'criterion' . $comment['class'],
                    'id' => '{NAME}-comments-{COMMENT-id}'));
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL) {
            $criteriontemplate .= html_writer::start_tag('td', array('class' => 'frequent controls'));
            foreach (array('moveup', 'delete', 'movedown') as $key) {
                $value = get_string('comments' . $key, 'gradingform_btec');
                $button = html_writer::empty_tag('input', array('type' => 'submit',
                            'name' => '{NAME}[comments][{COMMENT-id}][' . $key . ']',
                            'id' => '{NAME}-comments-{COMMENT-id}-' . $key,
                            'value' => $value, 'title' => $value, 'tabindex' => -1));
                $criteriontemplate .= html_writer::tag('div', $button, array('class' => $key));
            }
            $criteriontemplate .= html_writer::end_tag('td'); // Controls.
            $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                        'name' => '{NAME}[comments][{COMMENT-id}][sortorder]', 'value' => $comment['sortorder']));
            $description = html_writer::tag('textarea', htmlspecialchars($comment['description']),
                    array('name' => '{NAME}[comments][{COMMENT-id}][description]', 'cols' => '65', 'rows' => '5'));
            $description = html_writer::tag('div', $description, array('class' => 'criteriondesc'));
        } else {
            if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN) {
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[comments][{COMMENT-id}][sortorder]', 'value' => $comment['sortorder']));
                $criteriontemplate .= html_writer::empty_tag('input', array('type' => 'hidden',
                            'name' => '{NAME}[comments][{COMMENT-id}][description]', 'value' => $comment['description']));
            }
            if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
                $description = html_writer::tag('span', htmlspecialchars($comment['description']),
                        array('name' => '{NAME}[comments][{COMMENT-id}][description]',
                            'title' => get_string('clicktocopy', 'gradingform_btec'),
                            'id' => '{NAME}[comments][{COMMENT-id}]', 'class' => 'markingbteccomment'));
            } else {
                $description = $comment['description'];
            }
        }
        $descriptionclass = 'description';
        if (isset($comment['error_description'])) {
            $descriptionclass .= ' error';
        }
        $criteriontemplate .= html_writer::tag('td', $description, array('class' => $descriptionclass,
                    'id' => '{NAME}-comments-{COMMENT-id}-description'));
        $criteriontemplate .= html_writer::end_tag('tr'); // Criterion.

        $criteriontemplate = str_replace('{NAME}', $elementname, $criteriontemplate);
        $criteriontemplate = str_replace('{COMMENT-id}', $comment['id'], $criteriontemplate);
        return $criteriontemplate;
    }

    /**
     * This function returns html code for displaying btec template (content before and after
     * criteria list). Depending on $mode it may be the code to edit btec, to preview the btec,
     * to evaluate somebody or to review the evaluation.
     *
     * This function is called from display_btec() to display the whole btec.
     *
     * When overriding this function it is very important to remember that all elements of html
     * form (in edit or evaluate mode) must have the name $elementname.
     *
     * Also JavaScript relies on the class names of elements and when developer changes them
     * script might stop working.
     *
     * @param int $mode btec display mode, one of gradingform_btec_controller::DISPLAY_* {@link gradingform_btec_controller}
     * @param array $options An array of options provided to {@link gradingform_btec_renderer::btec_edit_options()}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param string $criteriastr evaluated templates for this btec's criteria
     * @param string $commentstr
     * @return string
     */
    protected function btec_template($mode, $options, $elementname, $criteriastr, $commentstr) {
        $classsuffix = ''; // CSS suffix for class of the main div. Depends on the mode.
        switch ($mode) {
            case gradingform_btec_controller::DISPLAY_EDIT_FULL:
                $classsuffix = ' editor editable';
                break;
            case gradingform_btec_controller::DISPLAY_EDIT_FROZEN:
                $classsuffix = ' editor frozen';
                break;
            case gradingform_btec_controller::DISPLAY_PREVIEW:
            case gradingform_btec_controller::DISPLAY_PREVIEW_GRADED:
                $classsuffix = ' editor preview';
                break;
            case gradingform_btec_controller::DISPLAY_EVAL:
                $classsuffix = ' evaluate editable';
                break;
            case gradingform_btec_controller::DISPLAY_EVAL_FROZEN:
                $classsuffix = ' evaluate frozen';
                break;
            case gradingform_btec_controller::DISPLAY_REVIEW:
                $classsuffix = ' review';
                break;
            case gradingform_btec_controller::DISPLAY_VIEW:
                $classsuffix = ' view';
                break;
        }

        $btectemplate = html_writer::start_tag('div', array('id' => 'btec-{NAME}',
                    'class' => 'clearfix gradingform_btec' . $classsuffix));
        $btectemplate .= html_writer::tag('table', $criteriastr, array('class' => 'criteria', 'id' => '{NAME}-criteria'));
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL) {
            $value = get_string('addcriterion', 'gradingform_btec');
            $input = html_writer::empty_tag('input', array('type' => 'submit', 'name' => '{NAME}[criteria][addcriterion]',
                        'id' => '{NAME}-criteria-addcriterion', 'value' => $value, 'title' => $value));
            $btectemplate .= html_writer::tag('div', $input, array('class' => 'addcriterion'));
        }

        if (!empty($commentstr)) {
            $btectemplate .= html_writer::tag('label', get_string('comments', 'gradingform_btec'),
                    array('for' => '{NAME}-comments', 'class' => 'commentheader'));
            $btectemplate .= html_writer::tag('table', $commentstr,
                    array('class' => 'comments', 'id' => '{NAME}-comments'));
        }
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL) {
            $value = get_string('addcomment', 'gradingform_btec');
            $input = html_writer::empty_tag('input', array('type' => 'submit',
                        'name' => '{NAME}[comments][addcomment]',
                        'id' => '{NAME}-comments-addcomment', 'value' => $value, 'title' => $value));
            $btectemplate .= html_writer::tag('div', $input, array('class' => 'addcomment'));
        }

        $btectemplate .= $this->btec_edit_options($mode, $options);
        $btectemplate .= html_writer::end_tag('div');

        return str_replace('{NAME}', $elementname, $btectemplate);
    }

    /**
     * Generates html template to view/edit the btec options. Expression {NAME} is used in
     * template for the form element name

     * @param int $mode btec display mode, one of gradingform_btec_controller::DISPLAY_* {@link gradingform_btec_controller}
     * @param array $options
     * @return string
     */
    protected function btec_edit_options($mode, $options) {
        if ($mode != gradingform_btec_controller::DISPLAY_EDIT_FULL &&
                $mode != gradingform_btec_controller::DISPLAY_EDIT_FROZEN &&
                $mode != gradingform_btec_controller::DISPLAY_PREVIEW) {
            // Options are displayed only for people who can manage.
            return;
        }
        $html = html_writer::start_tag('div', array('class' => 'optionsheader'));
        $html .= print_collapsible_region_start('btecoptions', uniqid('btecoptions'),
                get_string('btecoptions', 'gradingform_btec'), '', true, true);
        $attrs = array('type' => 'hidden', 'name' => '{NAME}[options][optionsset]',
            'value' => 1);
        $html .= html_writer::empty_tag('input', $attrs);
        /* mavg */
        foreach ($options as $option => $value) {
            $html .= html_writer::start_tag('div', array('class' => 'option ' . $option));
            $attrs = array('name' => '{NAME}[options][' . $option . ']',
                'id' => '{NAME}-options-' . $option);
            switch ($option) {
                case 'sortlevelsasc':

                    // Display option as dropdown.
                    $html .= html_writer::tag('span', get_string($option, 'gradingform_btec'), array('class' => 'label'));
                    $value = (int) (!!$value); // Make sure $value is either 0 or 1.
                    if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL) {
                        $selectoptions = array(0 => get_string($option . '0', 'gradingform_btec'),
                            1 => get_string($option . '1', 'gradingform_btec'));
                        $valuestr = html_writer::select($selectoptions, $attrs['name'], $value,
                                false, array('id' => $attrs['id']));
                        $html .= html_writer::tag('span', $valuestr, array('class' => 'value'));
                        // TODO add here button 'Sort levels'.
                    } else {
                        $html .= html_writer::tag('span', get_string($option . $value, 'gradingform_btec'),
                                array('class' => 'value'));
                        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN) {
                            $html .= html_writer::empty_tag('input', $attrs + array('type' => 'hidden',
                                        'value' => $value));
                        }
                    }
                    break;
                default:

                    if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN && $value) {
                        $html .= html_writer::empty_tag('input', $attrs + array('type' => 'hidden', 'value' => $value));
                    }
                    // Display option as checkbox.
                    $attrs['type'] = 'checkbox';
                    $attrs['value'] = 1;
                    if ($value) {
                        $attrs['checked'] = 'checked';
                    }
                    if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN ||
                            $mode == gradingform_btec_controller::DISPLAY_PREVIEW) {
                        $attrs['disabled'] = 'disabled';
                        unset($attrs['name']);
                    }
                    $html .= html_writer::empty_tag('input', $attrs);
                    $html .= html_writer::tag('label', get_string($option, 'gradingform_btec'), array('for' => $attrs['id']));
                    break;
            }
            $html .= html_writer::end_tag('div'); // Option.
        }

        $html .= html_writer::end_tag('div'); // Options.
        $html .= print_collapsible_region_end(true);

        return $html;
    }

    /**
     * This function returns html code for displaying btec. Depending on $mode it may be the code
     * to edit btec, to preview the btec, to evaluate somebody or to review the evaluation.
     *
     * It is very unlikely that this function needs to be overriden by theme. It does not produce
     * any html code, it just prepares data about btec design and evaluation, adds the CSS
     * class to elements and calls the functions level_template, criterion_template and
     * btec_template
     *
     * @param array $criteria data about the btec design
     * @param array $comments
     * @param array $options
     * @param int $mode btec display mode, one of gradingform_btec_controller::DISPLAY_* {@link gradingform_btec_controller}
     * @param string $elementname the name of the form element (in editor mode) or the prefix for div ids (in view mode)
     * @param array $values evaluation result
     * @param array $validationerrors
     * @return string
     */
    public function display_btec($criteria, $comments, $options, $mode,
            $elementname = null, $values = null, $validationerrors = null) {
        $criteriastr = "";
        if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
            $criteriastr = "<tr><td class = 'markingbtecyesno'>";
            $criteriastr .= "<input type = 'radio' title='toggle all to no'"
                    . " name=yesno class = 'setyesno' value='no'>" . get_string('no', 'gradingform_btec');
            $criteriastr .= "<td class = 'markingbtecyesno'>";
            $criteriastr .= "<input type = 'radio' title = 'toggle all to yes'"
                    . " name=yesno class = 'setyesno' value='yes'>" . get_string('yes', 'gradingform_btec');
            $criteriastr .= "</td><td colspan = 4></td></tr>";
        }

        $cnt = 0;
        foreach ($criteria as $id => $criterion) {
            $criterion['class'] = $this->get_css_class_suffix($cnt++, count($criteria) - 1);
            $criterion['id'] = $id;
            if (isset($values['criteria'][$id])) {
                $criterionvalue = $values['criteria'][$id];
            } else {
                $criterionvalue = null;
            }
            $criteriastr .= $this->criterion_template($mode, $options, $elementname,
                    $criterion, $criterionvalue, $validationerrors);
        }
        $cnt = 0;
        $commentstr = '';
        // Check if comments should be displayed.
        if ($mode == gradingform_btec_controller::DISPLAY_EDIT_FULL ||
                $mode == gradingform_btec_controller::DISPLAY_EDIT_FROZEN ||
                $mode == gradingform_btec_controller::DISPLAY_PREVIEW ||
                $mode == gradingform_btec_controller::DISPLAY_EVAL ||
                $mode == gradingform_btec_controller::DISPLAY_EVAL_FROZEN) {

            foreach ($comments as $id => $comment) {
                $comment['id'] = $id;
                $comment['class'] = $this->get_css_class_suffix($cnt++, count($comments) - 1);
                $commentstr .= $this->comment_template($mode, $elementname, $comment);
            }
        }

        $output = $this->btec_template($mode, $options, $elementname, $criteriastr, $commentstr);

        if ($mode == gradingform_btec_controller::DISPLAY_EVAL) {
            $showdesc = get_user_preferences('gradingform_btec-showmarkerdesc', true);
            $showdescstud = get_user_preferences('gradingform_btec-showstudentdesc', true);
            $checked1 = array();
            $checked2 = array();
            $checkeds1 = array();
            $checkeds2 = array();
            $checked = array('checked' => 'checked');
            if ($showdesc) {
                $checked1 = $checked;
            } else {
                $checked2 = $checked;
            }
            if ($showdescstud) {
                $checkeds1 = $checked;
            } else {
                $checkeds2 = $checked;
            }

            $radio = html_writer::tag('input', get_string('showmarkerdesc', 'gradingform_btec'),
                    array('type' => 'radio', 'style' => 'padding:25px;',
                        'name' => 'showmarkerdesc',
                        'value' => "true") + $checked1);
            $radio .= html_writer::tag('input', get_string('hidemarkerdesc', 'gradingform_btec'),
                    array('type' => 'radio', 'style' => 'padding:25px;',
                        'name' => 'showmarkerdesc',
                        'value' => "false") + $checked2);
            $output .= html_writer::tag('div', $radio, array('class' => 'showmarkerdesc'));

            $radio = html_writer::tag('input', get_string('showstudentdesc', 'gradingform_btec'),
                    array('type' => 'radio',
                        'name' => 'showstudentdesc',
                        'value' => "true") + $checkeds1);
            $radio .= html_writer::tag('input', get_string('hidestudentdesc', 'gradingform_btec'),
                    array('type' => 'radio',
                        'name' => 'showstudentdesc',
                        'value' => "false") + $checkeds2);
            $output .= html_writer::tag('div', $radio, array('class' => 'showstudentdesc'));
        }
        return $output;
    }

    /**
     * Help function to return CSS class names for element (first/last/even/odd) with leading space
     *
     * @param int $idx index of this element in the row/column
     * @param int $maxidx maximum index of the element in the row/column
     * @return string
     */
    protected function get_css_class_suffix($idx, $maxidx) {
        $class = '';
        if ($idx == 0) {
            $class .= ' first';
        }
        if ($idx == $maxidx) {
            $class .= ' last';
        }
        if ($idx % 2) {
            $class .= ' odd';
        } else {
            $class .= ' even';
        }
        return $class;
    }

    /**
     * Displays for the student the list of instances or default content if no instances found
     *
     * @param array $instances array of objects of type gradingform_btec_instance
     * @param string $defaultcontent default string that would be displayed without advanced grading
     * @param bool $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function display_instances($instances, $defaultcontent, $cangrade) {
        $return = '';
        if (count($instances)) {
            $return .= html_writer::start_tag('div', array('class' => 'advancedgrade'));
            $idx = 0;
            foreach ($instances as $instance) {
                $return .= $this->display_instance($instance, $idx++, $cangrade);
            }
            $return .= html_writer::end_tag('div');
        }
        /* TODO the next few lines are inelegant */
        return $defaultcontent . '<tr><td></td><td>' . $return . '</td></tr>';
    }

    /**
     * Displays one grading instance
     *
     * @param gradingform_btec_instance $instance
     * @param int $idx unique number of instance on page
     * @param bool $cangrade whether current user has capability to grade in this context
     */
    public function display_instance(gradingform_btec_instance $instance, $idx, $cangrade) {
        $criteria = $instance->get_controller()->get_definition()->btec_criteria;
        $options = $instance->get_controller()->get_options();
        $values = $instance->get_btec_filling();
        if ($cangrade) {
            $mode = gradingform_btec_controller::DISPLAY_REVIEW;
        } else {
            $mode = gradingform_btec_controller::DISPLAY_VIEW;
        }

        $output = $this->box($instance->get_controller()->get_formatted_description(), 'gradingform_btec-description') .
                $this->display_btec($criteria, array(), $options, $mode, 'btec' . $idx, $values);
        return $output;
    }

    /**
     * Displays a confirmation message after a regrade has occured
     *
     * @param string $elementname
     * @param int $changelevel
     * @param int $value The regrade option that was used
     * @return string
     */
    public function display_regrade_confirmation($elementname, $changelevel, $value) {
        $html = html_writer::start_tag('div', array('class' => 'gradingform_btec-regrade'));
        if ($changelevel <= 2) {
            $html .= get_string('regrademessage1', 'gradingform_btec');
            $selectoptions = array(
                0 => get_string('regradeoption0', 'gradingform_btec'),
                1 => get_string('regradeoption1', 'gradingform_btec')
            );
            $html .= html_writer::select($selectoptions, $elementname . '[regrade]', $value, false);
        } else {
            $html .= get_string('regrademessage5', 'gradingform_btec');
            $html .= html_writer::empty_tag('input', array('name' => $elementname . '[regrade]', 'value' => 1, 'type' => 'hidden'));
        }
        $html .= html_writer::end_tag('div');
        return $html;
    }

    /**
     * Generates and returns HTML code to display information box about how btec score is converted to the grade
     *
     * @param array $scores
     * @return string
     */
    public function display_btec_mapping_explained($scores) {
        $html = '';
        if (!$scores) {
            return $html;
        }
        if (isset($scores['modulegrade']) && $scores['maxscore'] != $scores['modulegrade']) {
            $html .= $this->box(html_writer::tag('div', get_string('btecmappingexplained', 'gradingform_btec', (object) $scores))
                    , 'generalbox gradingform_btec-error');
        }

        return $html;
    }

}
