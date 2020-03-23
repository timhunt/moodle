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
 * Decode links quiz restore tests.
 *
 * @package    core_backup
 * @copyright  2020 Ilya Tregubov <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include all the needed stuff.
global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');

/**
 * restore_decode tests (both rule and content)
 */
class restore_quiz_decode_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test restore_decode_rule class
     */
    public function test_restore_quiz_decode_links() {
        global $DB, $CFG, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            array('format' => 'topics', 'numsections' => 3,
                'enablecompletion' => COMPLETION_ENABLED),
            array('createsections' => true));
        $quiz = $generator->create_module('quiz', array(
            'course' => $course->id));

        // Create questions.

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $context = context_course::instance($course->id);
        $cat = $questiongenerator->create_question_category(array('contextid' => $context->id));
        $question = $questiongenerator->create_question('multichoice', null, array('category' => $cat->id));

        // Add to the quiz.
        quiz_add_quiz_question($question->id, $quiz);
        $DB->set_field('question_answers', 'answer', $CFG->wwwroot . '/course/view.php?id=' . $course->id);
        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($quiz->cmid));

        $sql = "SELECT qa.answer
                  FROM {quiz} q
             LEFT JOIN {quiz_slots} qs ON qs.quizid = q.id
             LEFT JOIN {question_answers} qa ON qa.question = qs.questionid
                 WHERE q.id = :quizid";
        $params = array('quizid' => $newcm->instance);
        $answers = $DB->get_fieldset_sql($sql, $params);
        foreach ($answers as $answer) {
            $this->assertEquals($CFG->wwwroot . '/course/view.php?id=' . $course->id, $answer);
        }
    }
}
