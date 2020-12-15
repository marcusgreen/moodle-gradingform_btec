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
 * JavaScript code for the gradingform_btec
 *
 * @package    gradingform_btec
 * @copyright  2017 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 define(['jquery'], function($) {
  return {
    init: function() {
      debugger
      $("[name='grade[modgrade_type]']").change(function(){
        var el = $("[name='grade[modgrade_scale]']");
        var selected = el.find(":selected").text();
        if(selected =='BTEC'){
          $("[name='advancedgradingmethod_submissions']").val('btec');
        }
      });
      $("[name='grade[modgrade_scale]']").change(function() {
          var el = $( this );
          var selected = el.find(":selected").text();
          if (selected =='BTEC'){
            $("[name='advancedgradingmethod_submissions']").val('btec');
          }
      });
      $("[name='advancedgradingmethod_submissions']").change(function() {
        var el = $( this );
        if (el.val() =='btec'){
          $("#id_grade_modgrade_scale option:contains('BTEC')").prop("selected", true);
          var scale = $("[name='grade[modgrade_scale]']");
          $("[name='grade[modgrade_type]']").val('scale');
        }


      });

    }
  };

});
