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
 * Grading method controller for the btec plugin
 *
 * @package    gradingform_btec
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/grading/form/lib.php');

/**
 * This controller encapsulates the btec grading logic
 *
 * @package    gradingform_btec
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_btec_controller extends gradingform_controller {

    // Modes of displaying the btec (used in gradingform_btec_renderer).
    /** btec display mode: For editing (moderator or teacher creates a btec) */
    const DISPLAY_EDIT_FULL = 1;

    /** btec display mode: Preview the btec design with hidden fields */
    const DISPLAY_EDIT_FROZEN = 2;

    /** btec display mode: Preview the btec design (for person with manage permission) */
    const DISPLAY_PREVIEW = 3;

    /** btec display mode: Preview the btec (for people being graded) */
    const DISPLAY_PREVIEW_GRADED = 8;

    /** btec display mode: For evaluation, enabled (teacher grades a student) */
    const DISPLAY_EVAL = 4;

    /** btec display mode: For evaluation, with hidden fields */
    const DISPLAY_EVAL_FROZEN = 5;

    /** btec display mode: Teacher reviews filled btec */
    const DISPLAY_REVIEW = 6;

    /** btec display mode: Dispaly filled btec (i.e. students see their grades) */
    const DISPLAY_VIEW = 7;

    /** @var stdClass|false the definition structure */
    protected $moduleinstance = false;

    /* These constants map to BTEC scale created at install time; */

    const REFER = 1;
    const PASS = 2;
    const MERIT = 3;
    const DISTINCTION = 4;

    /* This originally did a call to the database to check that
     * the key words were Pass, Merit and Distinction and converted
     * them to the equivalent letters by chopping of the leading letter
     * This seems to have caused problems and has been simplified, at the
     * potential loss of easy internationalisation.
     */

    public static function get_scale_letters() {
        $scaleletters = array('p' => 'p', 'm' => 'm', 'd' => 'd');
        return $scaleletters;
    }

    /**
     * Extends the module settings navigation with the btec grading settings
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
     * and there is an area with the active grading method set to 'btec'.
     *
     * @param settings_navigation $settingsnav {@link settings_navigation}
     * @param navigation_node $node {@link navigation_node}
     */
    public function extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node = null) {
        $node->add(get_string('definemarkingbtec', 'gradingform_btec'), $this->get_editor_url(),
                settings_navigation::TYPE_CUSTOM, null, null, new pix_icon('icon', '', 'gradingform_btec'));
    }

    /**
     * Extends the module navigation
     *
     * This function is called when the context for the page is an activity module with the
     * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
     *
     * @param global_navigation $navigation {@link global_navigation}
     * @param navigation_node $node {@link navigation_node}
     * @return void
     */
    public function extend_navigation(global_navigation $navigation, navigation_node $node = null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            // No need for preview if user can manage forms, he will have link to manage.php in settings instead.
            return;
        }
        if ($this->is_form_defined() && ($options = $this->get_options()) && !empty($options['alwaysshowdefinition'])) {
            $node->add(get_string('gradingof', 'gradingform_btec',
                    get_grading_manager($this->get_areaid())->get_area_title()),
                    new moodle_url('/grade/grading/form/' . $this->get_method_name() .
                            '/preview.php', array('areaid' => $this->get_areaid())), settings_navigation::TYPE_CUSTOM);
        }
    }

    /**
     * Saves the btec definition into the database
     *
     * @see parent::update_definition()
     * @param stdClass $newdefinition btec definition data as coming from gradingform_btec_editbtec::get_data()
     * @param int $usermodified optional userid of the author of the definition, defaults to the current user
     */
    public function update_definition(stdClass $newdefinition, $usermodified = null) {
        $this->update_or_check_btec($newdefinition, $usermodified, true);
        if (isset($newdefinition->btec['regrade']) && $newdefinition->btec['regrade']) {
            $this->mark_for_regrade();
        }
    }

    /**
     * Either saves the btec definition into the database or check if it has been changed.
     *
     * Returns the level of changes:
     * 0 - no changes
     * 1 - only texts or criteria sortorders are changed, students probably do not require re-grading
     * 2 - added levels but maximum score on btec is the same, students still may not require re-grading
     * 3 - removed criteria or changed number of points, students require re-grading but may be re-graded automatically
     * 4 - removed levels - students require re-grading and not all students may be re-graded automatically
     * 5 - added criteria - all students require manual re-grading
     *
     * @param stdClass $newdefinition btec definition data as coming from gradingform_btec_editbtec::get_data()
     * @param int|null $usermodified optional userid of the author of the definition, defaults to the current user
     * @param bool $doupdate if true actually updates DB, otherwise performs a check
     * @return int
     */
    public function update_or_check_btec(stdClass $newdefinition, $usermodified = null, $doupdate = false) {
        global $DB;

        // Firstly update the common definition data in the {grading_definition} table.
        if ($this->definition === false) {
            if (!$doupdate) {
                // If we create the new definition there is no such thing as re-grading anyway.
                return 5;
            }
            // If definition does not exist yet, create a blank one
            // (we need id to save files embedded in description).
            parent::update_definition(new stdClass(), $usermodified);
            parent::load_definition();
        }
        if (!isset($newdefinition->btec['options'])) {
            $newdefinition->btec['options'] = self::get_default_options();
        }
        $newdefinition->options = json_encode($newdefinition->btec['options']);
        $editoroptions = self::description_form_field_options($this->get_context());
        $newdefinition = file_postupdate_standard_editor($newdefinition, 'description',
                $editoroptions, $this->get_context(), 'grading', 'description', $this->definition->id);

        // Reload the definition from the database.
        $currentdefinition = $this->get_definition(true);

        // Update btec data.
        $haschanges = array();
        if (empty($newdefinition->btec['criteria'])) {
            $newcriteria = array();
        } else {
            $newcriteria = $newdefinition->btec['criteria']; // New ones to be saved.
        }
        foreach ($newcriteria as $key => $value) {
            /* strip any leading or trailing whitespace */
            $newcriteria[$key]['shortname'] = trim($newcriteria[$key]['shortname']);
            /* strip any white space from within the string */
            $newcriteria[$key]['shortname'] = str_replace(' ', '', $newcriteria[$key]['shortname']);
        }
        $currentcriteria = $currentdefinition->btec_criteria;
        $criteriafields = array('sortorder', 'description', 'descriptionformat', 'descriptionmarkers',
            'descriptionmarkersformat', 'shortname');
        foreach ($newcriteria as $id => $criterion) {
            if (preg_match('/^NEWID\d+$/', $id)) {
                // Insert criterion into DB.
                $data = array('definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE,
                    'descriptionmarkersformat' => FORMAT_MOODLE); // TODO format is not supported yet.
                foreach ($criteriafields as $key) {
                    if (array_key_exists($key, $criterion)) {
                        $data[$key] = $criterion[$key];
                    }
                }
                if ($doupdate) {
                    $id = $DB->insert_record('gradingform_btec_criteria', $data);
                }
                $haschanges[5] = true;
            } else {
                // Update criterion in DB.
                $data = array();
                foreach ($criteriafields as $key) {
                    if (array_key_exists($key, $criterion) && $criterion[$key] != $currentcriteria[$id][$key]) {
                        $data[$key] = $criterion[$key];
                    }
                }
                if (!empty($data)) {
                    // Update only if something is changed.
                    $data['id'] = $id;
                    if ($doupdate) {
                        $DB->update_record('gradingform_btec_criteria', $data);
                    }
                    $haschanges[1] = true;
                }
            }
        }
        // Remove deleted criteria from DB.
        foreach (array_keys($currentcriteria) as $id) {
            if (!array_key_exists($id, $newcriteria)) {
                if ($doupdate) {
                    $DB->delete_records('gradingform_btec_criteria', array('id' => $id));
                }
                $haschanges[3] = true;
            }
        }
        // Now handle comments.
        if (empty($newdefinition->btec['comments'])) {
            $newcomment = array();
        } else {
            $newcomment = $newdefinition->btec['comments']; // New ones to be saved.
        }
        $currentcomments = $currentdefinition->btec_comment;
        $commentfields = array('sortorder', 'description');
        foreach ($newcomment as $id => $comment) {
            if (preg_match('/^NEWID\d+$/', $id)) {
                // Insert criterion into DB.
                $data = array('definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE);
                foreach ($commentfields as $key) {
                    if (array_key_exists($key, $comment)) {
                        $data[$key] = $comment[$key];
                    }
                }
                if ($doupdate) {
                    $id = $DB->insert_record('gradingform_btec_comments', $data);
                }
            } else {
                // Update criterion in DB.
                $data = array();
                foreach ($commentfields as $key) {
                    if (array_key_exists($key, $comment) && $comment[$key] != $currentcomments[$id][$key]) {
                        $data[$key] = $comment[$key];
                    }
                }
                if (!empty($data)) {
                    // Update only if something is changed.
                    $data['id'] = $id;
                    if ($doupdate) {
                        $DB->update_record('gradingform_btec_comments', $data);
                    }
                }
            }
        }
        // Remove deleted criteria from DB.
        foreach (array_keys($currentcomments) as $id) {
            if (!array_key_exists($id, $newcomment)) {
                if ($doupdate) {
                    $DB->delete_records('gradingform_btec_comments', array('id' => $id));
                }
            }
        }
        // End comments handle.
        foreach (array('status', 'description', 'descriptionformat', 'name', 'options') as $key) {
            if (isset($newdefinition->$key) && $newdefinition->$key != $this->definition->$key) {
                $haschanges[1] = true;
            }
        }
        if ($usermodified && $usermodified != $this->definition->usermodified) {
            $haschanges[1] = true;
        }
        if (!count($haschanges)) {
            return 0;
        }
        if ($doupdate) {
            parent::update_definition($newdefinition, $usermodified);
            $this->load_definition();
        }
        // Return the maximum level of changes.
        $changelevels = array_keys($haschanges);
        sort($changelevels);
        return array_pop($changelevels);
    }

    /**
     * Marks all instances filled with this btec with the status INSTANCE_STATUS_NEEDUPDATE
     */
    public function mark_for_regrade() {
        global $DB;
        if ($this->has_active_instances()) {
            $conditions = array('definitionid' => $this->definition->id,
                'status' => gradingform_instance::INSTANCE_STATUS_ACTIVE);
            $DB->set_field('grading_instances', 'status', gradingform_instance::INSTANCE_STATUS_NEEDUPDATE, $conditions);
        }
    }

    /**
     * Loads the btec form definition if it exists
     *
     * There is a new array called 'btec_criteria' appended to the list of parent's definition properties.
     */
    protected function load_definition() {
        global $DB;

        // Check to see if the user prefs have changed - putting here as this function is called on post even when
        // validation on the page fails. - hard to find a better place to locate this as it is specific to the btec.
        $showdesc = optional_param('showmarkerdesc', null, PARAM_BOOL); // Check if we need to change pref.
        $showdescstudent = optional_param('showstudentdesc', null, PARAM_BOOL); // Check if we need to change pref.
        if ($showdesc !== null) {
            set_user_preference('gradingform_btec-showmarkerdesc', $showdesc);
        }
        if ($showdescstudent !== null) {
            set_user_preference('gradingform_btec-showstudentdesc', $showdescstudent);
        }

        // Get definition.
        $definition = $DB->get_record('grading_definitions', array('areaid' => $this->areaid,
            'method' => $this->get_method_name()), '*');
        if (!$definition) {
            // The definition doesn't have to exist. It may be that we are only now creating it.
            $this->definition = false;
            return false;
        }

        $this->definition = $definition;
        // Now get criteria.
        $this->definition->btec_criteria = array();
        $this->definition->btec_comment = array();
        $criteria = $DB->get_recordset('gradingform_btec_criteria', array('definitionid' => $this->definition->id), 'sortorder');
        foreach ($criteria as $criterion) {
            foreach (array('id', 'sortorder', 'description', 'descriptionformat',
            'descriptionmarkers', 'descriptionmarkersformat', 'shortname') as $fieldname) {
                if ($fieldname == 'maxscore') {  // Strip any trailing 0.
                    $this->definition->btec_criteria[$criterion->id][$fieldname] = (float) $criterion->{$fieldname};
                } else {
                    $this->definition->btec_criteria[$criterion->id][$fieldname] = $criterion->{$fieldname};
                }
            }
        }
        $criteria->close();

        // Now get comments.
        $comments = $DB->get_recordset('gradingform_btec_comments', array('definitionid' => $this->definition->id), 'sortorder');
        foreach ($comments as $comment) {
            foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {
                $this->definition->btec_comment[$comment->id][$fieldname] = $comment->{$fieldname};
            }
        }
        $comments->close();
        if (empty($this->moduleinstance)) { // Only set if empty.
            $modulename = $this->get_component();
            $context = $this->get_context();
            if (strpos($modulename, 'mod_') === 0) {
                $dbman = $DB->get_manager();
                $modulename = substr($modulename, 4);
                if ($dbman->table_exists($modulename)) {
                    $cm = get_coursemodule_from_id($modulename, $context->instanceid);
                    if (!empty($cm)) { // This should only occur when the course is being deleted.
                        $this->moduleinstance = $DB->get_record($modulename, array("id" => $cm->instance));
                    }
                }
            }
        }
    }

    /**
     * Returns the default options for the btec display
     *
     * @return array
     */
    public static function get_default_options() {
        $options = array(
            'alwaysshowdefinition' => 1,
            'showmarkspercriterionstudents' => 1,
            'showdescriptionstudent' => 1,
        );
        return $options;
    }

    /**
     * Gets the options of this btec definition, fills the missing options with default values
     *
     * @return array
     */
    public function get_options() {
        $options = self::get_default_options();
        if (!empty($this->definition->options)) {
            $thisoptions = json_decode($this->definition->options);
            foreach ($thisoptions as $option => $value) {
                $options[$option] = $value;
            }
        }
        return $options;
    }

    /**
     * Converts the current definition into an object suitable for the editor form's set_data()
     *
     * @param bool $addemptycriterion whether to add an empty criterion if the btec is completely empty (just being created)
     * @return stdClass
     */
    public function get_definition_for_editing($addemptycriterion = false) {
        $definition = $this->get_definition();
        $properties = new stdClass();
        $properties->areaid = $this->areaid;
        if (isset($this->moduleinstance->grade)) {
            $properties->modulegrade = $this->moduleinstance->grade;
        }
        if ($definition) {
            foreach (array('id', 'name', 'description', 'descriptionformat', 'status') as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor($properties, 'description',
                    $options, $this->get_context(), 'grading', 'description', $definition->id);
        }
        $properties->btec = array('criteria' => array(), 'options' => $this->get_options(), 'comments' => array());
        if (!empty($definition->btec_criteria)) {
            $properties->btec['criteria'] = $definition->btec_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->btec['criteria'] = array('addcriterion' => 1);
        }
        if (!empty($definition->btec_comment)) {
            $properties->btec['comments'] = $definition->btec_comment;
        } else if (!$definition && $addemptycriterion) {
            $properties->btec['comments'] = array('addcomment' => 1);
        }
        return $properties;
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's {@link update_definition()}
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->btec = array('criteria' => array(), 'options' => $old->btec['options'], 'comments' => array());
        $newcritid = 1;
        foreach ($old->btec['criteria'] as $oldcritid => $oldcrit) {
            unset($oldcrit['id']);
            $new->btec['criteria']['NEWID' . $newcritid] = $oldcrit;
            $newcritid++;
        }
        $newcomid = 1;
        foreach ($old->btec['comments'] as $oldcritid => $oldcom) {
            unset($oldcom['id']);
            $new->btec['comments']['NEWID' . $newcomid] = $oldcom;
            $newcomid++;
        }
        return $new;
    }

    /**
     * Options for displaying the btec description field in the form
     *
     * @param context $context
     * @return array options for the form description field
     */
    public static function description_form_field_options($context) {
        global $CFG;
        return array(
            'maxfiles' => -1,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes),
            'context' => $context,
        );
    }

    /**
     * Formats the definition description for display on page
     *
     * @return string
     */
    public function get_formatted_description() {
        if (!isset($this->definition->description)) {
            return '';
        }
        $context = $this->get_context();

        $options = self::description_form_field_options($this->get_context());
        $description = file_rewrite_pluginfile_urls($this->definition->description,
                'pluginfile.php', $context->id, 'grading', 'description', $this->definition->id, $options);

        $formatoptions = array(
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context
        );
        return format_text($description, $this->definition->descriptionformat, $formatoptions);
    }

    /**
     * Returns the btec plugin renderer
     *
     * @param moodle_page $page the target page
     * @return gradingform_btec_renderer
     */
    public function get_renderer(moodle_page $page) {
        return $page->get_renderer('gradingform_' . $this->get_method_name());
    }

    /**
     * Returns the HTML code displaying the preview of the grading form
     *
     * @param moodle_page $page the target page
     * @return string
     */
    public function render_preview(moodle_page $page) {

        if (!$this->is_form_defined()) {
            throw new coding_exception('It is the caller\'s responsibility to make sure that the form is actually defined');
        }

        $output = $this->get_renderer($page);
        $criteria = $this->definition->btec_criteria;
        $comments = $this->definition->btec_comment;
        $options = $this->get_options();
        $btec = '';
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $showdescription = true;
        } else {
            if (empty($options['alwaysshowdefinition'])) {
                // Ensure we don't display unless show rubric option enabled.
                return '';
            }
            $showdescription = $options['showdescriptionstudent'];
        }
        if ($showdescription) {
            $btec .= $output->box($this->get_formatted_description(), 'gradingform_btec-description');
        }
        if (has_capability('moodle/grade:managegradingforms', $page->context)) {
            $btec .= $output->display_btec($criteria, $comments, $options, self::DISPLAY_PREVIEW, 'btec');
        } else {
            $btec .= $output->display_btec($criteria, $comments, $options, self::DISPLAY_PREVIEW_GRADED, 'btec');
        }

        return $btec;
    }

    /**
     * Deletes the btec definition and all the associated information
     */
    protected function delete_plugin_definition() {
        global $DB;

        // Get the list of instances.
        $instances = array_keys($DB->get_records('grading_instances', array('definitionid' => $this->definition->id), '', 'id'));
        // Delete all fillings.
        $DB->delete_records_list('gradingform_btec_fillings', 'instanceid', $instances);
        // Delete instances.
        $DB->delete_records_list('grading_instances', 'id', $instances);
        // Get the list of criteria records.
        $criteria = array_keys($DB->get_records('gradingform_btec_criteria',
                array('definitionid' => $this->definition->id), '', 'id'));
        // Delete critera.
        $DB->delete_records_list('gradingform_btec_criteria', 'id', $criteria);
        // Delete comments.
        $DB->delete_records('gradingform_btec_comments', array('definitionid' => $this->definition->id));
    }

    /**
     * If instanceid is specified and grading instance exists and it is created by this rater for
     * this item, this instance is returned.
     * If there exists a draft for this raterid+itemid, take this draft (this is the change from parent)
     * Otherwise new instance is created for the specified rater and itemid
     *
     * @param int $instanceid
     * @param int $raterid
     * @param int $itemid
     * @return gradingform_instance
     */
    public function get_or_create_instance($instanceid, $raterid, $itemid) {
        global $DB;
        if ($instanceid &&
                $instance = $DB->get_record('grading_instances', array('id' => $instanceid, 'raterid' => $raterid,
            'itemid' => $itemid), '*', IGNORE_MISSING)) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            if ($rs = $DB->get_records('grading_instances', array('raterid' => $raterid,
                'itemid' => $itemid), 'timemodified DESC', '*', 0, 1)) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if ($record->status == gradingform_btec_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))) {
                    $record->isrestored = true;
                    return $this->get_instance($record);
                }
            }
        }
        return $this->create_instance($raterid, $itemid);
    }

    /**
     * Returns html code to be included in student's feedback.
     *
     * @param moodle_page $page
     * @param int $itemid
     * @param array $gradinginfo result of function grade_get_grades
     * @param string $defaultcontent default string to be returned if no active grading is found
     * @param bool $cangrade whether current user has capability to grade in this context
     * @return string
     */
    public function render_grade($page, $itemid, $gradinginfo, $defaultcontent, $cangrade) {
        return $this->get_renderer($page)->display_instances($this->get_active_instances($itemid), $defaultcontent, $cangrade);
    }

    // Full-text search support.

    /**
     * Prepare the part of the search query to append to the FROM statement
     *
     * @param string $gdid the alias of grading_definitions.id column used by the caller
     * @return string
     */
    public static function sql_search_from_tables($gdid) {
        return " LEFT JOIN {gradingform_btec_criteria} gc ON (gc.definitionid = $gdid)";
    }

    /**
     * Prepare the parts of the SQL WHERE statement to search for the given token
     *
     * The returned array cosists of the list of SQL comparions and the list of
     * respective parameters for the comparisons. The returned chunks will be joined
     * with other conditions using the OR operator.
     *
     * @param string $token token to search for
     * @return array An array containing two more arrays
     *     Array of search SQL fragments
     *     Array of params for the search fragments
     */
    public static function sql_search_where($token) {
        global $DB;

        $subsql = array();
        $params = array();

        // Search in btec criteria description.
        $subsql[] = $DB->sql_like('gc.description', '?', false, false);
        $params[] = '%' . $DB->sql_like_escape($token) . '%';

        return array($subsql, $params);
    }

    /* Calculates and returns the possible minimum and maximum score (in points) for this btec
     * @return array
     */
}

/**
 * Class to manage one btec grading instance. Stores information and performs actions like
 * update, copy, validate, submit, etc.
 *
 * @package    gradingform_btec
 * @copyright  2012 Dan Marsden <dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradingform_btec_instance extends gradingform_instance {

    /** @var array */
    protected $btec;

    /** @var array An array of validation errors */
    protected $validationerrors = array();

    /**
     * Deletes this (INCOMPLETE) instance from database.
     */
    public function cancel() {
        global $DB;
        parent::cancel();
        $DB->delete_records('gradingform_btec_fillings', array('instanceid' => $this->get_id()));
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
            $params = array('instanceid' => $instanceid, 'criterionid' => $criterionid,
                'score' => $record['score'], 'remark' => $record['remark'],
                'remarkformat' => $record['remarkformat']);
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
        if (!isset($elementvalue['criteria']) || !is_array($elementvalue['criteria']) ||
                count($elementvalue['criteria']) < count($criteria)) {
            return false;
        }
        // Reset validation errors.
        $this->validationerrors = null;
        foreach ($criteria as $id => $criterion) {
            if (!isset($elementvalue['criteria'][$id]['score']) ||
                    !is_numeric($elementvalue['criteria'][$id]['score']) ||
                    $elementvalue['criteria'][$id]['score'] < 0) {
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
            $records = $DB->get_records('gradingform_btec_fillings', array('instanceid' => $this->get_id()));
            $this->btec = array('criteria' => array());
            foreach ($records as $record) {
                $level = $DB->get_records('gradingform_btec_criteria', array('id' => $record->criterionid));
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
                $newrecord = array('instanceid' => $this->get_id(), 'criterionid' => $criterionid,
                    'score' => $record['score'], 'remarkformat' => FORMAT_MOODLE);

                if (isset($record['remark'])) {
                    $newrecord['remark'] = $record['remark'];
                }
                $DB->insert_record('gradingform_btec_fillings', $newrecord);
            } else {
                $newrecord = array('id' => $currentgrade['criteria'][$criterionid]['id']);

                foreach (array('score', 'remark'/* , 'remarkformat' TODO */) as $key) {
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
                $DB->delete_records('gradingform_btec_fillings', array('id' => $record['id']));
            }
        }
        $this->get_btec_filling(true);
    }

    /* This is called from outside btec grading so
     * it calls calculate_btec_grade to allow for the
     * creation of unit tests
     */

    public function get_grade() {
        $grade = $this->get_btec_filling();
        return $this->calculate_btec_grade($grade);
    }

    /* works out the overal grade */

    public function calculate_btec_grade(array $grade) {
        /* X initialises the level to assume it is not present.
         * X is checked later on to see if the level should be
         * ignored for not existing. Then the letters are
         * walked through to be set to P M or D if they do exist
         */
        $scaleletters = gradingform_btec_controller::get_scale_letters();
        $p = $scaleletters['p'];
        $m = $scaleletters['m'];
        $d = $scaleletters['d'];

        $levels = array($p => "X", $m => "X", $d => "X");
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
            $module = array('name' => 'gradingform_btec', 'fullpath' => '/grade/grading/form/btec/js/btec.js');
            $page->requires->js_init_call('M.gradingform_btec.init', array(
                array('name' => $gradingformelement->getName())), true, $module);
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
            $html .= html_writer::tag('div', get_string('btecnotcompleted', 'gradingform_btec'),
                    array('class' => 'gradingform_btec-error'));
            if (!empty($this->validationerrors)) {
                foreach ($this->validationerrors as $id => $err) {
                    $a = new stdClass();
                    $a->criterianame = $criteria[$id]['shortname'];
                    $a->maxscore = $criteria[$id]['maxscore'];
                    $html .= html_writer::tag('div', get_string('err_scoreinvalid', 'gradingform_btec', $a),
                            array('class' => 'gradingform_btec-error'));
                }
            }
        }
        $currentinstance = $this->get_current_instance();
        if ($currentinstance && $currentinstance->get_status() == gradingform_instance::INSTANCE_STATUS_NEEDUPDATE) {
            $html .= html_writer::tag('div', get_string('needregrademessage', 'gradingform_btec'),
                    array('class' => 'gradingform_btec-regrade'));
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
            $html .= html_writer::tag('div', get_string('restoredfromdraft', 'gradingform_btec'),
                    array('class' => 'gradingform_btec-restored'));
        }
        $html .= html_writer::tag('div', $this->get_controller()->get_formatted_description(),
                array('class' => 'gradingform_btec-description'));
        $html .= $this->get_controller()->get_renderer($page)->display_btec($criteria, $comments,
                $options, $mode, $gradingformelement->getName(), $value, $this->validationerrors);
        return $html;
    }

    public function has_config() {
        return true;
    }

}
