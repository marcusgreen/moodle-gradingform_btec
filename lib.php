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

    /**
     * fail, may attempt again
     */
    const REFER = 1;
    /**
     * lowest grade
     */
    const PASS = 2;
    /**
     * medium grade
     */
    const MERIT = 3;
    /**
     * highest grade
     */
    const DISTINCTION = 4;

    /**
     *
     * This originally did a call to the database to check that
     * the key words were Pass, Merit and Distinction and converted
     * them to the equivalent letters by chopping of the leading letter
     * This seems to have caused problems and has been simplified, at the
     * potential loss of easy internationalisation.
     */
    public static function get_scale_letters() {
        $scaleletters = ['p' => 'p', 'm' => 'm', 'd' => 'd'];
        return $scaleletters;
    }

     /**
      * Extends the module settings navigation with the btec grading settings.
      *
      * This function is called when the context for the page is an activity module with the
      * FEATURE_ADVANCED_GRADING, the user has the permission moodle/grade:managegradingforms
      * and there is an area with the active grading method set to 'btec'.
      *
      * @param settings_navigation $settingsnav Settings navigation instance.
      * @param navigation_node|null $node Active navigation node if present.
      */
    public function extend_settings_navigation(settings_navigation $settingsnav, ?navigation_node $node = null) {
        $node->add(
            get_string('definemarkingbtec', 'gradingform_btec'),
            $this->get_editor_url(),
            settings_navigation::TYPE_CUSTOM,
            null,
            null,
            new pix_icon('icon', '', 'gradingform_btec')
        );
    }

     /**
      * Extends the module navigation.
      *
      * This function is called when the context for the page is an activity module with the
      * FEATURE_ADVANCED_GRADING and there is an area with the active grading method set to the given plugin.
      *
      * @param global_navigation $navigation Global navigation instance.
      * @param navigation_node|null $node Active navigation node if present.
      * @return void
      */
    public function extend_navigation(global_navigation $navigation, ?navigation_node $node = null) {
        if (has_capability('moodle/grade:managegradingforms', $this->get_context())) {
            // No need for preview if user can manage forms, he will have link to manage.php in settings instead.
            return;
        }
        if ($this->is_form_defined() && ($options = $this->get_options()) && !empty($options['alwaysshowdefinition'])) {
            $node->add(
                get_string(
                    'gradingof',
                    'gradingform_btec',
                    get_grading_manager($this->get_areaid())->get_area_title()
                ),
                new moodle_url(
                    '/grade/grading/form/' . $this->get_method_name() .
                    '/preview.php',
                    ['areaid' => $this->get_areaid()]
                ),
                settings_navigation::TYPE_CUSTOM
            );
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
        $newdefinition = file_postupdate_standard_editor(
            $newdefinition,
            'description',
            $editoroptions,
            $this->get_context(),
            'grading',
            'description',
            $this->definition->id
        );

        // Reload the definition from the database.
        $currentdefinition = $this->get_definition(true);

        // Update btec data.
        $haschanges = [];
        if (empty($newdefinition->btec['criteria'])) {
            $newcriteria = [];
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
        $criteriafields = ['sortorder', 'description', 'descriptionformat', 'descriptionmarkers',
            'descriptionmarkersformat', 'shortname'];
        foreach ($newcriteria as $id => $criterion) {
            if (preg_match('/^NEWID\d+$/', $id)) {
                // Insert criterion into DB.
                $data = ['definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE,
                    'descriptionmarkersformat' => FORMAT_MOODLE]; // TODO format is not supported yet.
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
                $data = [];
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
                    $DB->delete_records('gradingform_btec_criteria', ['id' => $id]);
                }
                $haschanges[3] = true;
            }
        }
        // Now handle comments.
        if (empty($newdefinition->btec['comments'])) {
            $newcomment = [];
        } else {
            $newcomment = $newdefinition->btec['comments']; // New ones to be saved.
        }
        $currentcomments = $currentdefinition->btec_comment;
        $commentfields = ['sortorder', 'description'];
        foreach ($newcomment as $id => $comment) {
            if (preg_match('/^NEWID\d+$/', $id)) {
                // Insert criterion into DB.
                $data = ['definitionid' => $this->definition->id, 'descriptionformat' => FORMAT_MOODLE];
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
                $data = [];
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
                    $DB->delete_records('gradingform_btec_comments', ['id' => $id]);
                }
            }
        }
        // End comments handle.
        foreach (['status', 'description', 'descriptionformat', 'name', 'options'] as $key) {
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
            $conditions = ['definitionid' => $this->definition->id,
                'status' => gradingform_instance::INSTANCE_STATUS_ACTIVE];
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
        $definition = $DB->get_record('grading_definitions', ['areaid' => $this->areaid,
            'method' => $this->get_method_name()], '*');
        if (!$definition) {
            // The definition doesn't have to exist. It may be that we are only now creating it.
            $this->definition = false;
            return false;
        }

        $this->definition = $definition;
        // Now get criteria.
        $this->definition->btec_criteria = [];
        $this->definition->btec_comment = [];
        $criteria = $DB->get_recordset('gradingform_btec_criteria', ['definitionid' => $this->definition->id], 'sortorder');
        foreach ($criteria as $criterion) {
            foreach (
                ['id', 'sortorder', 'description', 'descriptionformat',
                'descriptionmarkers', 'descriptionmarkersformat', 'shortname'] as $fieldname
            ) {
                if ($fieldname == 'maxscore') {  // Strip any trailing 0.
                    $this->definition->btec_criteria[$criterion->id][$fieldname] = (float) $criterion->{$fieldname};
                } else {
                    $this->definition->btec_criteria[$criterion->id][$fieldname] = $criterion->{$fieldname};
                }
            }
        }
        $criteria->close();

        // Now get comments.
        $comments = $DB->get_recordset('gradingform_btec_comments', ['definitionid' => $this->definition->id], 'sortorder');
        foreach ($comments as $comment) {
            foreach (['id', 'sortorder', 'description', 'descriptionformat'] as $fieldname) {
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
                        $this->moduleinstance = $DB->get_record($modulename, ["id" => $cm->instance]);
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
        $options = [
            'alwaysshowdefinition' => 1,
            'showmarkspercriterionstudents' => 1,
            'showdescriptionstudent' => 1,
        ];
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
            foreach (['id', 'name', 'description', 'descriptionformat', 'status'] as $key) {
                $properties->$key = $definition->$key;
            }
            $options = self::description_form_field_options($this->get_context());
            $properties = file_prepare_standard_editor(
                $properties,
                'description',
                $options,
                $this->get_context(),
                'grading',
                'description',
                $definition->id
            );
        }
        $properties->btec = ['criteria' => [], 'options' => $this->get_options(), 'comments' => []];
        if (!empty($definition->btec_criteria)) {
            $properties->btec['criteria'] = $definition->btec_criteria;
        } else if (!$definition && $addemptycriterion) {
            $properties->btec['criteria'] = ['addcriterion' => 1];
        }
        if (!empty($definition->btec_comment)) {
            $properties->btec['comments'] = $definition->btec_comment;
        } else if (!$definition && $addemptycriterion) {
            $properties->btec['comments'] = ['addcomment' => 1];
        }
        return $properties;
    }

    /**
     * Returns the form definition suitable for cloning into another area
     *
     * @see parent::get_definition_copy()
     * @param gradingform_controller $target the controller of the new copy
     * @return stdClass definition structure to pass to the target's update_definition()
     */
    public function get_definition_copy(gradingform_controller $target) {

        $new = parent::get_definition_copy($target);
        $old = $this->get_definition_for_editing();
        $new->description_editor = $old->description_editor;
        $new->btec = ['criteria' => [], 'options' => $old->btec['options'], 'comments' => []];
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
        return [
            'maxfiles' => -1,
            'maxbytes' => get_max_upload_file_size($CFG->maxbytes),
            'context' => $context,
        ];
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
        $description = file_rewrite_pluginfile_urls(
            $this->definition->description,
            'pluginfile.php',
            $context->id,
            'grading',
            'description',
            $this->definition->id,
            $options
        );

        $formatoptions = [
            'noclean' => false,
            'trusted' => false,
            'filter' => true,
            'context' => $context,
        ];
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
        $instances = array_keys($DB->get_records('grading_instances', ['definitionid' => $this->definition->id], '', 'id'));
        // Delete all fillings.
        $DB->delete_records_list('gradingform_btec_fillings', 'instanceid', $instances);
        // Delete instances.
        $DB->delete_records_list('grading_instances', 'id', $instances);
        // Get the list of criteria records.
        $criteria = array_keys($DB->get_records(
            'gradingform_btec_criteria',
            ['definitionid' => $this->definition->id],
            '',
            'id'
        ));
        // Delete critera.
        $DB->delete_records_list('gradingform_btec_criteria', 'id', $criteria);
        // Delete comments.
        $DB->delete_records('gradingform_btec_comments', ['definitionid' => $this->definition->id]);
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
        if (
            $instanceid &&
                $instance = $DB->get_record('grading_instances', ['id' => $instanceid, 'raterid' => $raterid,
            'itemid' => $itemid], '*', IGNORE_MISSING)
        ) {
            return $this->get_instance($instance);
        }
        if ($itemid && $raterid) {
            if (
                $rs = $DB->get_records('grading_instances', ['raterid' => $raterid,
                'itemid' => $itemid], 'timemodified DESC', '*', 0, 1)
            ) {
                $record = reset($rs);
                $currentinstance = $this->get_current_instance($raterid, $itemid);
                if (
                    $record->status == gradingform_btec_instance::INSTANCE_STATUS_INCOMPLETE &&
                        (!$currentinstance || $record->timemodified > $currentinstance->get_data('timemodified'))
                ) {
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

        $subsql = [];
        $params = [];

        // Search in btec criteria description.
        $subsql[] = $DB->sql_like('gc.description', '?', false, false);
        $params[] = '%' . $DB->sql_like_escape($token) . '%';

        return [$subsql, $params];
    }

    /* Calculates and returns the possible minimum and maximum score (in points) for this btec
     * @return array
     */
}
