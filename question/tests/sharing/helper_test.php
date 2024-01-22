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
 * moodlecore sharing helper class tests.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace sharing;

use core_question\local\bank\question_edit_contexts;
use core_question\sharing\helper;

class helper_test extends \advanced_testcase {
    public function test_get_open_modules(): void {
        $openmods = helper::get_open_modules();
        $this->assertGreaterThanOrEqual(1, $openmods);
        $this->assertContains('qbank', $openmods);
        $this->assertNotContains('quiz', $openmods);
    }

    public function test_get_closed_modules(): void {
        $closedmods = helper::get_closed_modules();
        $this->assertGreaterThanOrEqual(1, $closedmods);
        $this->assertContains('quiz', $closedmods);
        $this->assertNotContains('qbank', $closedmods);
    }

    public function test_get_course_instances(): void {
        $this->resetAfterTest();

        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $closedmodgen = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $course = self::getDataGenerator()->create_course();
        $openmodgen->create_instance(['course' => $course]);
        $closedmodgen->create_instance(['course' => $course]);

        // Expect 1 plugin that has open instances in this course.
        $openinstances = helper::get_course_open_instances($course->id);
        $this->assertCount(1, $openinstances);
        $this->assertArrayHasKey('qbank', $openinstances);
        $this->assertCount(1, $openinstances['qbank']);

        // Make sure no closed mod instances were returned.
        $this->assertArrayNotHasKey('quiz', $openinstances);

        // Expect 1 plugin that has closed instances in this course.
        $closedinstances = helper::get_course_closed_instances($course->id);
        $this->assertCount(1, $closedinstances);
        $this->assertArrayHasKey('quiz', $closedinstances);
        $this->assertCount(1, $closedinstances['quiz']);

        // Make sure no open mod instances were returned.
        $this->assertArrayNotHasKey('qbank', $closedinstances);
    }

    public function test_get_all_open_instances(): void {
        $this->resetAfterTest();

        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $closedmodgen = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course3 = self::getDataGenerator()->create_course(['category' => $category2->id]);

        $openmodgen->create_instance(['course' => $course1]);
        $closedmodgen->create_instance(['course' => $course1]);

        $openmodgen->create_instance(['course' => $course2]);
        $closedmodgen->create_instance(['course' => $course2]);

        $openmodgen->create_instance(['course' => $course3]);
        $closedmodgen->create_instance(['course' => $course3]);

        $categoryinstances = helper::get_all_open_instances();

        // Expect top level count of 3 items, 1 per category that has a course containing an open module instance.
        $this->assertCount(3, $categoryinstances);
        foreach ($categoryinstances as $key => $courseinstances) {
            // Should be 1 instance per category and course.
            $this->assertCount(1, $courseinstances);
            foreach ($courseinstances as $cminfo) {
                // The key of the top level array is "{pluginname}_{courseid}"
                $this->assertEquals($key, $cminfo->modname . '_' . $cminfo->course);
                // All instances must be of the qbank type.
                $this->assertEquals('qbank', $cminfo->modname);
            }
        }
    }

    public function test_filter_by_question_tab_access(): void {
        global $DB;

        $this->resetAfterTest();

        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $course = self::getDataGenerator()->create_course();
        $openmod1 = $openmodgen->create_instance(['course' => $course]);
        $context = \context_module::instance($openmod1->cmid);

        $user = self::getDataGenerator()->create_and_enrol($course, 'student');
        self::setUser($user);

        $allopenmods = helper::get_course_open_instances($course->id);
        $filteredmods = helper::filter_by_question_edit_access(array_keys(question_edit_contexts::$caps), $allopenmods);

        // Make sure student can't see any of the tabs of the question/edit.php page on any of the mod instances.
        $this->assertCount(0, $filteredmods);

        // User now given editingteacher role on openmod1 context.
        $roles = $DB->get_records('role', [], '', 'shortname, id');
        role_assign($roles['editingteacher']->id, $user->id, $context->id);

        $filteredmods = helper::filter_by_question_edit_access(array_keys(question_edit_contexts::$caps), $allopenmods);

        $this->assertCount(1, $filteredmods['qbank']);
        $filteredmod = reset($filteredmods['qbank']);
        $this->assertEquals($openmod1->name, $filteredmod->name);
    }

    public function test_create_default_open_instance(): void {
        $this->resetAfterTest();
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create the instance and assert default values.
        helper::create_default_open_instance($course, $course->fullname);
        $modinfo = get_fast_modinfo($course);
        $cminfos = $modinfo->get_instances();
        $cminfo = reset($cminfos['qbank']);

        $this->assertCount(1, $cminfos['qbank']);
        $this->assertEquals("{$course->fullname} course question bank", $cminfo->get_name());
        $this->assertEquals(0, $cminfo->sectionnum);
        $this->assertEmpty($cminfo->idnumber);
        $this->assertEmpty($cminfo->content);
    }
}
