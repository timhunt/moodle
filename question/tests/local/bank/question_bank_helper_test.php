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
        $user = self::getDataGenerator()->create_user();
        $roles = $DB->get_records('role', [], '', 'shortname, id');
        self::setUser($user);

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

        public function test_create_default_open_instance(): void {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        // Create the instance and assert default values.
        question_bank_helper::create_default_open_instance($course, $course->fullname);
        $modinfo = get_fast_modinfo($course);
        $cminfos = $modinfo->get_instances_of('qbank');
        $this->assertCount(1, $cminfos);
        $cminfo = reset($cminfos);
        $this->assertEquals($course->fullname, $cminfo->get_name());
        $this->assertEquals(0, $cminfo->sectionnum);
        $modrecord = $DB->get_record('qbank', ['id' => $cminfo->instance]);
        $this->assertEquals(question_bank_helper::STANDARD, $modrecord->type);
        $this->assertEmpty($cminfo->idnumber);
        $this->assertEmpty($cminfo->content);

        // Create a system type bank.
        question_bank_helper::create_default_open_instance($course, 'System bank 1', question_bank_helper::SYSTEM);

        // Try and create another system type bank.
        question_bank_helper::create_default_open_instance($course, 'System bank 2', question_bank_helper::SYSTEM);

        $modinfo = get_fast_modinfo($course);
        $cminfos = $modinfo->get_instances_of('qbank');
        $cminfos = array_filter($cminfos, static function($cminfo) {
            global $DB;
            return $DB->record_exists('qbank', ['id' => $cminfo->instance, 'type' => question_bank_helper::SYSTEM]);
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
        $qbank = question_bank_helper::get_default_open_instance_system_type($course);
        $this->assertFalse($qbank);
        $qbank = question_bank_helper::get_default_open_instance_system_type($course, true);
        $this->assertEquals(get_string('systembank', 'mod_qbank'), $qbank->get_name());
        $modrecord = $DB->get_record('qbank', ['id' => $qbank->instance]);
        $this->assertEquals(question_bank_helper::SYSTEM, $modrecord->type);
    }

    public function test_recently_viewed_question_banks() {
        $this->resetAfterTest();

        $user = self::getDataGenerator()->create_user();
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();
        $banks = [];
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course1->id]);
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course1->id]);
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course1->id]);
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course2->id]);
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course2->id]);
        $banks[] = self::getDataGenerator()->create_module('qbank', ['course' => $course2->id]);

        self::setUser($user);

        // Trigger bank view on each of them.
        foreach ($banks as $bank) {
            $cat = question_make_default_category(\context_module::instance($bank->cmid));
            $context = \context::instance_by_id($cat->contextid);
            question_bank_helper::add_category_to_recently_viewed($context);
        }

        $viewedorder = array_reverse($banks);
        // Check that the courseid filter works.
        $recentlyviewed = question_bank_helper::get_recently_used_open_banks($user->id, $course1->id);
        $this->assertCount(3, $recentlyviewed);

        $recentlyviewed = question_bank_helper::get_recently_used_open_banks($user->id);

        // We only keep a record of 5 maximum.
        $this->assertCount(5, $recentlyviewed);
        foreach ($recentlyviewed as $order => $record) {
            $this->assertEquals($viewedorder[$order]->cmid, $record->modid);
        }

        // Now if we view one of those again it should get bumped to the front of the list.
        $bank3cat = question_get_default_category(\context_module::instance($banks[2]->cmid)->id);
        $bank3context = \context::instance_by_id($bank3cat->contextid);
        question_bank_helper::add_category_to_recently_viewed($bank3context);

        $recentlyviewed = question_bank_helper::get_recently_used_open_banks($user->id);

        // We should still have 5 maximum.
        $this->assertCount(5, $recentlyviewed);
        // The recently viewed on got bumped to the front.
        $this->assertEquals($banks[2]->cmid, $recentlyviewed[0]->modid);
        // The others got sorted accordingly behind it.
        $this->assertEquals($banks[5]->cmid, $recentlyviewed[1]->modid);
        $this->assertEquals($banks[4]->cmid, $recentlyviewed[2]->modid);
        $this->assertEquals($banks[3]->cmid, $recentlyviewed[3]->modid);
        $this->assertEquals($banks[1]->cmid, $recentlyviewed[4]->modid);

        // Now create a quiz and trigger the bank view of it.
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course1]);
        $quizcat = question_make_default_category(\context_module::instance($quiz->cmid));
        $quizcontext = \context::instance_by_id($quizcat->contextid);
        question_bank_helper::add_category_to_recently_viewed($quizcontext);

        $recentlyviewed = question_bank_helper::get_recently_used_open_banks($user->id);
        // We should still have 5 maximum.
        $this->assertCount(5, $recentlyviewed);

        // Make sure that we only store bank views for plugins that support FEATURE_PUBLISHES_QUESTIONS.
        foreach ($recentlyviewed as $record) {
            $this->assertNotEquals($quiz->cmid, $record->modid);
        }

        // Now delete one of the viewed bank modules and get the records again.
        course_delete_module($banks[2]->cmid);
        $recentlyviewed = question_bank_helper::get_recently_used_open_banks($user->id);
        $this->assertCount(4, $recentlyviewed);

        // Check the order was retained.
        $this->assertEquals($banks[5]->cmid, $recentlyviewed[0]->modid);
        $this->assertEquals($banks[4]->cmid, $recentlyviewed[1]->modid);
        $this->assertEquals($banks[3]->cmid, $recentlyviewed[2]->modid);
        $this->assertEquals($banks[1]->cmid, $recentlyviewed[3]->modid);
    }
}