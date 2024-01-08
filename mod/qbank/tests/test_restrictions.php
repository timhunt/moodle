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
 * Restriction tests for mod_qbank.
 *
 * @package    mod_qbank
 * @copyright  2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_qbank;

class test_restrictions extends \advanced_testcase {

    /**
     * Ensure that modules with feature flag FEATURE_PUBLISHES_QUESTIONS are never rendered to the course page.
     *
     * @return void
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function test_visibility_on_course(): void {
        global $USER;
        $this->resetAfterTest();
        self::setAdminUser();

        $modgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $course = self::getDataGenerator()->create_course();
        $module = $modgen->create_instance(['course' => $course], ['visible' => 1]);
        [$modrec, $cmrec] = get_module_from_cmid($module->cmid);

        $this->assertEquals(1, $cmrec->visible);

        $coursemodinfo = new \course_modinfo($course, $USER->id);
        $cminfo = $coursemodinfo->get_cm($module->cmid);

        $this->assertFalse($cminfo->get_user_visible());
        $this->assertFalse($cminfo->is_visible_on_course_page());
    }
}
