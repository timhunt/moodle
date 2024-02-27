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
 * moodlecore sharing observer class tests.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace sharing;

use core_question\sharing\helper;

class observer_test extends \advanced_testcase {

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
            $event = \core\event\question_category_viewed::create_from_question_category_instance($cat, $context);
            $event->trigger();
        }

        $viewedorder = array_reverse($banks);
        $recentlyviewed = helper::get_recently_used_open_banks($user->id);

        // We only keep a record of 5 maximum.
        $this->assertCount(5, $recentlyviewed);
        foreach ($recentlyviewed as $order => $cminfo) {
            $this->assertEquals($viewedorder[$order]->cmid, $cminfo->id);
        }

        // Now if we view one of those again it should get bumped to the front of the list.
        $bank3cat = question_get_default_category(\context_module::instance($banks[2]->cmid)->id);
        $bank3context = \context::instance_by_id($bank3cat->contextid);
        $event = \core\event\question_category_viewed::create_from_question_category_instance($bank3cat, $bank3context);
        $event->trigger();

        $recentlyviewed = helper::get_recently_used_open_banks($user->id);

        // We should still have 5 maximum.
        $this->assertCount(5, $recentlyviewed);
        // The recently viewed on got bumped to the front.
        $this->assertEquals($banks[2]->cmid, $recentlyviewed[0]->id);
        // The others got sorted accordingly behind it.
        $this->assertEquals($banks[5]->cmid, $recentlyviewed[1]->id);
        $this->assertEquals($banks[4]->cmid, $recentlyviewed[2]->id);
        $this->assertEquals($banks[3]->cmid, $recentlyviewed[3]->id);
        $this->assertEquals($banks[1]->cmid, $recentlyviewed[4]->id);

        // Now create a quiz and trigger the bank view of it.
        $quiz = self::getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance(['course' => $course1]);
        $quizcat = question_make_default_category(\context_module::instance($quiz->cmid));
        $quizcontext = \context::instance_by_id($quizcat->contextid);
        $event = \core\event\question_category_viewed::create_from_question_category_instance($quizcat, $quizcontext);
        $event->trigger();

        $recentlyviewed = helper::get_recently_used_open_banks($user->id);
        // We should still have 5 maximum.
        $this->assertCount(5, $recentlyviewed);

        // Make sure that we only store bank views for plugins that support FEATURE_PUBLISHES_QUESTIONS.
        foreach ($recentlyviewed as $cminfo) {
            $this->assertNotEquals($quiz->cmid, $cminfo->id);
        }

        // Now delete one of the viewed bank modules and get the records again.
        course_delete_module($banks[2]->cmid);
        $recentlyviewed = helper::get_recently_used_open_banks($user->id);
        $this->assertCount(4, $recentlyviewed);

        // Check the order was retained.
        $this->assertEquals($banks[5]->cmid, $recentlyviewed[0]->id);
        $this->assertEquals($banks[4]->cmid, $recentlyviewed[1]->id);
        $this->assertEquals($banks[3]->cmid, $recentlyviewed[2]->id);
        $this->assertEquals($banks[1]->cmid, $recentlyviewed[3]->id);
    }
}
