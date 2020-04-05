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
 * Test backup and restore of multiple choice questions.
 *
 * @package    qtype_multichoice
 * @copyright  2020 Ilya Tregubov <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');


/**
 * Test backup and restore of multiple choice questions.
 */
class qtype_multichoice_backup_testcase extends advanced_testcase {

    /**
     * Test that links in question answers are encoded on restore.
     */
    public function test_restoring_choices_with_links() {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course and a quiz.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);

        // Create a question in the course context - so it should be reused, not copied when we duplicate a quiz.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $context = context_course::instance($course->id);
        $cat = $questiongenerator->create_question_category(array('contextid' => $context->id));
        $question = $questiongenerator->create_question('multichoice', null, array('category' => $cat->id));

        // Change the first choice to be a link to the course, and the second to be a link to the quiz.
        $questiondata = question_bank::load_question_data($question->id);
        $firstanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/course/view.php?id=' . $course->id,
                ['id' => $firstanswer->id]);
        $secondanswer = array_shift($questiondata->options->answers);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/mod/quiz/view.php?id=' . $quiz->cmid,
                ['id' => $secondanswer->id]);

        // Add the question to the quiz so it is backed up.
        quiz_add_quiz_question($question->id, $quiz);

        // Perform a backup and restore.
        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($quiz->cmid));

        // Verify the links in the answer are right:
        $restoredquestionid = $DB->get_field('quiz_slots', 'questionid', ['quizid' => $newcm->instance]);
        $restoredquestiondata = question_bank::load_question_data($restoredquestionid);
        print_object($restoredquestiondata);

        $firstanswer = array_shift($restoredquestiondata->options->answers);
        $this->assertEquals($CFG->wwwroot . '/course/view.php?id=' . $course->id, $firstanswer->answer);
        $secondanswer = array_shift($restoredquestiondata->options->answers);
        $this->assertEquals($CFG->wwwroot . '/mod/quiz/view.php?id=' . $newcm->id, $secondanswer->answer);
    }
}
