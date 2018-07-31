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
 * Unit tests for the drag-and-drop words into sentences question definition class.
 *
 * @package   qtype_ddwtos
 * @copyright 2018 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/question/type/ddwtos/tests/fixtures/testable_edit_ddwtos_form.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/ddwtos/edit_ddwtos_form.php');

/**
 * Unit tests for Stack question editing form.
 *
 * @copyright  2012 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_edit_form_test extends advanced_testcase {
    /**
     * Helper method.
     * @return edit_ddwtos_form_testable a new form instance that can be tested.
     */
    protected function get_form() {
        $this->setAdminUser();
        $this->resetAfterTest();

        return new edit_ddwtos_form_testable();
    }

    /**
     * Test generate array with elements for a choice group.
     *
     * @return void Array for form elements
     */
    public function test_choice_group_with_optional_param() {
        $mform = new MoodleQuickForm('fakeform', 'POST', new moodle_url('/'));
        $form = $this->get_form();
        $arrayformelements = $form->choice_group($mform, 10);
        $quickformselect = new MoodleQuickForm_select();
        foreach ($arrayformelements as $arrayformelement) {
            if ($arrayformelement instanceof MoodleQuickForm_select) {
                $quickformselect = $arrayformelement;
                break;
            }
        }
        $this->assertEquals(10, count($quickformselect->_options));
    }

    /**
     * Test generate array with elements for a choice group.
     *
     * @return void Array for form elements
     */
    public function test_choice_group_without_optional_param() {
        $mform = new MoodleQuickForm('fakeform', 'POST', new moodle_url('/'));
        $form = $this->get_form();
        $arrayformelements = $form->choice_group($mform);
        $quickformselect = new MoodleQuickForm_select();
        foreach ($arrayformelements as $arrayformelement) {
            if ($arrayformelement instanceof MoodleQuickForm_select) {
                $quickformselect = $arrayformelement;
                break;
            }
        }
        $this->assertEquals(8, count($quickformselect->_options));
    }
}
