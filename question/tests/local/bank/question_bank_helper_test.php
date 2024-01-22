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
 * question bank helper class tests.
 *
 * @package    core_question
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question;

use core_question\local\bank\question_bank_helper;

class question_bank_helper_test extends \advanced_testcase {
    public function test_get_open_modules(): void {
        $openmods = question_bank_helper::get_open_modules();
        $this->assertGreaterThanOrEqual(1, $openmods);
        $this->assertContains('qbank', $openmods);
        $this->assertNotContains('quiz', $openmods);
    }

    public function test_get_closed_modules(): void {
        $closedmods = question_bank_helper::get_closed_modules();
        $this->assertGreaterThanOrEqual(1, $closedmods);
        $this->assertContains('quiz', $closedmods);
        $this->assertNotContains('qbank', $closedmods);
    }

    public function test_get_instances(): void {
        global $DB;

        $this->resetAfterTest();

        $qgen = self::getDataGenerator()->get_plugin_generator('core_question');
        $openmodgen = self::getDataGenerator()->get_plugin_generator('mod_qbank');
        $closedmodgen = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();
        $course1 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course2 = self::getDataGenerator()->create_course(['category' => $category1->id]);
        $course3 = self::getDataGenerator()->create_course(['category' => $category2->id]);
        $course4 = self::getDataGenerator()->create_course(['category' => $category2->id]);

        $openmod1 = $openmodgen->create_instance(['course' => $course1]);
        $openmod1context = \context_module::instance($openmod1->cmid);
        $openmod1qcat1 = $qgen->create_question_category(['contextid' => $openmod1context->id]);
        $openmod1qcat2 = $qgen->create_question_category(['contextid' => $openmod1context->id]);
        $closedmod1 = $closedmodgen->create_instance(['course' => $course1]);
        $closedmod1context = \context_module::instance($closedmod1->cmid);
        $closedmod1qcat1 = $qgen->create_question_category(['contextid' => $closedmod1context->id]);
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($openmod1->cmid));
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($closedmod1->cmid));

        $openmod2 = $openmodgen->create_instance(['course' => $course2]);
        $openmod2context = \context_module::instance($openmod2->cmid);
        $openmod2qcat1 = $qgen->create_question_category(['contextid' => $openmod2context->id]);
        $openmod2qcat2 = $qgen->create_question_category(['contextid' => $openmod2context->id]);
        $closedmod2 = $closedmodgen->create_instance(['course' => $course2]);
        $closedmod2context = \context_module::instance($closedmod2->cmid);
        $closedmod1qcat1 = $qgen->create_question_category(['contextid' => $closedmod2context->id]);
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($openmod2->cmid));
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($closedmod2->cmid));

        // User doesn't have the capability on this one.
        $openmod3 = $openmodgen->create_instance(['course' => $course3]);
        $closedmod3 = $closedmodgen->create_instance(['course' => $course3]);

        // Exclude this course in the results despite having the capability.
        $openmod4 = $openmodgen->create_instance(['course' => $course4]);
        role_assign($roles['editingteacher']->id, $user->id, \context_module::instance($openmod4->cmid));

        $sharedbankiterable = question_bank_helper::get_instances(
            question_bank_helper::OPEN,
            [],
            [$course4->id],
            ['moodle/question:add'],
            true
        );

        $count = 0;
        foreach ($sharedbankiterable as $courseinstance) {
            // Must all be mod_qbanks.
            $this->assertEquals('qbank', $courseinstance->cminfo->modname);
            // Must have 2 categories each bank.
            $this->assertCount(2, $courseinstance->questioncategories);
            // Must not include the bank the user does not have access to.
            $this->assertNotEquals($openmod3->name, $courseinstance->name);
            $this->assertNotEquals($closedmod3->name, $courseinstance->name);
            $count++;
        }
        // Expect count of 2 bank instances.
        $this->assertEquals(2, $count);

        $closedbankiterable = question_bank_helper::get_instances(
            question_bank_helper::CLOSED,
            [$course1->id],
            [],
            ['moodle/question:add'],
            true
        );

        $count = 0;
        foreach ($closedbankiterable as $courseinstance) {
            // Must all be mod_quiz.
            $this->assertEquals('quiz', $courseinstance->cminfo->modname);
            // Must have 1 category in each bank.
            $this->assertCount(1, $courseinstance->questioncategories);
            // Must only include the bank from course 1;
            $this->assertNotContains($courseinstance->cminfo->course, [$course2->id, $course3->id, $course4->id]);
            $count++;
        }
        // Expect count of 1 bank instances.
        $this->assertEquals(1, $count);
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
        question_bank_helper::create_default_open_instance($course, $course->fullname);
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
