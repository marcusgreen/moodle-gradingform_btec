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
 * Privacy class for requesting user data.
 *
 * @package    gradingform_btec
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradingform_btec\privacy;

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\writer;

/**
 * Privacy class for requesting user data.
 *
 * @copyright  2018 Sara Arjona <sara@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param   collection $collection The initialised collection to add items to.
     * @return  collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_user_preference(
            'gradingform_btec-showmarkerdesc',
            'privacy:metadata:preference:showmarkerdesc'
        );
        $collection->add_user_preference(
            'gradingform_btec-showstudentdesc',
            'privacy:metadata:preference:showstudentdesc'
        );

        $collection->add_database_table('gradingform_btec_criteria', [
            'definitionid' => 'privacy:metadata:workshopid',
            'sortorder' => 'privacy:metadata:authorid',
            'example' => 'privacy:metadata:example',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
            'title' => 'privacy:metadata:submissiontitle',
            'content' => 'privacy:metadata:submissioncontent',
            'contentformat' => 'privacy:metadata:submissioncontentformat',
            'grade' => 'privacy:metadata:submissiongrade',
            'gradeover' => 'privacy:metadata:submissiongradeover',
            'feedbackauthor' => 'privacy:metadata:feedbackauthor',
            'feedbackauthorformat' => 'privacy:metadata:feedbackauthorformat',
            'published' => 'privacy:metadata:published',
            'late' => 'privacy:metadata:late',
        ], 'privacy:metadata:workshopsubmissions');
        return $collection;
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $prefvalue = get_user_preferences('gradingform_btec-showmarkerdesc', null, $userid);
        if ($prefvalue !== null) {
            $transformedvalue = transform::yesno($prefvalue);
            writer::export_user_preference(
                'gradingform_btec',
                'gradingform_btec-showmarkerdesc',
                $transformedvalue,
                get_string('privacy:metadata:preference:showmarkerdesc', 'gradingform_btec')
            );
        }

        $prefvalue = get_user_preferences('gradingform_btec-showstudentdesc', null, $userid);
        if ($prefvalue !== null) {
            $transformedvalue = transform::yesno($prefvalue);
            writer::export_user_preference(
                'gradingform_btec',
                'gradingform_btec-showstudentdesc',
                $transformedvalue,
                get_string('privacy:metadata:preference:showstudentdesc', 'gradingform_btec')
            );
        }
    }
}
