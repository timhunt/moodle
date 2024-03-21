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
        global $DB;
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $roles = $DB->get_records('role', [], '', 'shortname, id');
        self::setUser($user);

        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $closedmodgen = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $qgen = self::getDataGenerator()->get_plugin_generator('core_question');
        $course1 = self::getDataGenerator()->create_course();
        $openmodgen->create_instance(['course' => $course1]);
        $quiz1 = $closedmodgen->create_instance(['course' => $course1]);
        $quiz2 = $closedmodgen->create_instance(['course' => $course1]);
        $quiz1context = \context_module::instance($quiz1->cmid);
        $quiz1qcat = $qgen->create_question_category(['contextid' => $quiz1context->id]);
        $quiz1question = $qgen->create_question('shortanswer', null, ['category' => $quiz1qcat->id, 'idnumber' => 'quizq1']);
        quiz_add_quiz_question($quiz1question->id, $quiz1);
        $quiz2context = \context_module::instance($quiz2->cmid);
        $quiz2qcat = $qgen->create_question_category(['contextid' => $quiz2context->id]);
        $quiz2question = $qgen->create_question('shortanswer', null, ['category' => $quiz2qcat->id, 'idnumber' => 'quizq2']);
        quiz_add_quiz_question($quiz2question->id, $quiz2);

        // User only has access to quiz 1.
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($quiz1->cmid));

        // Expect 1 plugin that has open instances in this course.
        [$openinstances, ] = helper::get_course_open_instances($course1->id);
        $this->assertCount(1, $openinstances);
        $this->assertArrayHasKey('qbank_' . $course1->id, $openinstances);
        $this->assertCount(1, $openinstances['qbank_' . $course1->id]);

        // Make sure no closed mod instances were returned.
        $this->assertArrayNotHasKey('quiz_' . $course1->id, $openinstances);

        // Expect 1 plugin that has closed instances in this course.
        $closedinstances = helper::get_course_closed_instances($course1->id, ['moodle/question:add']);
        $this->assertCount(1, $closedinstances);
        $this->assertArrayHasKey('quiz_' . $course1->id, $closedinstances);
        $this->assertCount(1, $closedinstances['quiz_' . $course1->id]);

        // Make sure no open mod instances were returned.
        $this->assertArrayNotHasKey('qbank_' . $course1->id, $closedinstances);
    }

    public function test_get_all_open_instances(): void {
        global $DB;

        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $roles = $DB->get_records('role', [], '', 'shortname, id');
        self::setUser($user);

        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $closedmodgen = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course3 = self::getDataGenerator()->create_course(['category' => $category2->id]);


        $openmod1 = $openmodgen->create_instance(['course' => $course1]);
        $closedmodgen->create_instance(['course' => $course1]);
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($openmod1->cmid));

        $openmod2 = $openmodgen->create_instance(['course' => $course2]);
        $closedmodgen->create_instance(['course' => $course2]);
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($openmod2->cmid));

        // User doesn't have the capability on this one.
        $openmod3 = $openmodgen->create_instance(['course' => $course3]);
        $closedmodgen->create_instance(['course' => $course3]);

        [$categoryinstances, ] = helper::get_all_open_instances([], ['moodle/question:add']);

        // Expect top level count of 2 items, 1 per category that has a course containing an open module instance.
        $this->assertCount(2, $categoryinstances);
        foreach ($categoryinstances as $key => $courseinstances) {
            // Should be 1 instance per category and course.
            $this->assertCount(1, $courseinstances);
            foreach ($courseinstances as $cminfo) {
                // The key of the top level array is "{pluginname}_{courseid}"
                $this->assertEquals($key, $cminfo->modname . '_' . $cminfo->course);
                // All instances must be of the qbank type.
                $this->assertEquals('qbank', $cminfo->modname);
                // Make sure we don't have the module they can't access.
                $this->assertNotEquals($openmod3->name, $cminfo->name);
            }
        }
    }

        public function test_create_default_open_instance(): void {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create the instance and assert default values.
        helper::create_default_open_instance($course, $course->fullname);
        $modinfo = get_fast_modinfo($course);
        $cminfos = $modinfo->get_instances_of('qbank');
        $this->assertCount(1, $cminfos);
        $cminfo = reset($cminfos);
        $this->assertEquals($course->fullname, $cminfo->get_name());
        $this->assertEquals(0, $cminfo->sectionnum);
        $modrecord = $DB->get_record('qbank', ['id' => $cminfo->instance]);
        $this->assertEquals(helper::STANDARD, $modrecord->type);
        $this->assertEmpty($cminfo->idnumber);
        $this->assertEmpty($cminfo->content);

        // Create a system type bank.
        helper::create_default_open_instance($course, 'System bank 1', helper::SYSTEM);

        // Try and create another system type bank.
        helper::create_default_open_instance($course, 'System bank 2', helper::SYSTEM);

        $modinfo = get_fast_modinfo($course);
        $cminfos = $modinfo->get_instances_of('qbank');
        $cminfos = array_filter($cminfos, static function($cminfo) {
            global $DB;
            return $DB->record_exists('qbank', ['id' => $cminfo->instance, 'type' => helper::SYSTEM]);
        });

        // Can only be 1 system 'type' bank per course.
        $this->assertCount(1, $cminfos);
        $cminfo = reset($cminfos);
        $this->assertEquals('System bank 1', $cminfo->get_name());
        $moddata = $DB->get_record('qbank', ['id' => $cminfo->instance]);
        $this->assertEquals(get_string('systembankdescription', 'mod_qbank'), $moddata->intro);
        $this->assertEquals(1, $cminfo->showdescription);
    }

    public function test_get_default_open_instance_system_type() {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $modinfo = get_fast_modinfo($course);
        $qbanks = $modinfo->get_instances_of('qbank');
        $this->assertCount(0, $qbanks);
        $qbank = helper::get_default_open_instance_system_type($course);
        $this->assertFalse($qbank);
        $qbank = helper::get_default_open_instance_system_type($course, true);
        $this->assertEquals("{$course->fullname} system bank", $qbank->get_name());
        $modrecord = $DB->get_record('qbank', ['id' => $qbank->instance]);
        $this->assertEquals(helper::SYSTEM, $modrecord->type);
    }
}