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
 * moodlecore question bank sharing backup/restore tests.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace sharing;

use backup;
use core_question\sharing\helper;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/question/tests/backup_restore_trait.php';

class backup_test extends \advanced_testcase {

    use \core_question\backup_restore_trait;

    /**
     * Boilerplate setup for the tests. Creates a course, a quiz, and a qbank module. It adds a category to each module context
     * and adds a question to each category. Finally, it adds the 2 questions to the quiz.
     *
     * @return \stdClass
     */
    private function add_course_quiz_and_qbank() {
        $qgen = self::getDataGenerator()->get_plugin_generator('core_question');

        // Create a new course.
        $course = self::getDataGenerator()->create_course();

        // Create a question bank module instance, a category for that module, and a question for that category.
        $qbank = self::getDataGenerator()->create_module('qbank', ['type' => helper::STANDARD, 'course' => $course->id]);
        $qbankcontext = \context_module::instance($qbank->cmid);
        $bankqcat = $qgen->create_question_category(['contextid' => $qbankcontext->id]);
        $bankquestion = $qgen->create_question('shortanswer',
                null,
                ['name' => 'bank question', 'category' => $bankqcat->id, 'idnumber' => 'bankq1']
        );

        // Create a quiz module instance, a category for that module, and a question for that category.
        $quiz = self::getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $quizcontext = \context_module::instance($quiz->cmid);
        $quizqcat = $qgen->create_question_category(['contextid' => $quizcontext->id]);
        $quizquestion = $qgen->create_question('shortanswer',
                null,
                ['name' => 'quiz question', 'category' => $quizqcat->id, 'idnumber' => 'quizq1']
        );

        quiz_add_quiz_question($bankquestion->id, $quiz);
        quiz_add_quiz_question($quizquestion->id, $quiz);

        $data = new \stdClass();
        $data->course = $course;
        $data->qbank = $qbank;
        $data->qbankcategory = $bankqcat;
        $data->qbankquestion = $bankquestion;
        $data->quiz = $quiz;
        $data->quizcategory = $quizqcat;
        $data->quizquestion = $quizquestion;

        return $data;
    }

    /**
     * If the backup contains ONLY a quiz but that quiz uses questions from a qbank module and itself,
     * then the non-quiz context categories and questions should restore to a default qbank module on the new course.
     *
     * @return void
     */
    public function test_quiz_activity_restore_to_new_course() {
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        // Create a course to make a backup.
        $data = $this->add_course_quiz_and_qbank();
        $oldquiz = $data->quiz;

        // Backup ONLY the quiz module.
        $backupid = $this->backup_course($oldquiz->cmid, \backup::TYPE_1ACTIVITY);

        // Create a new course to restore to.
        $newcourse = self::getDataGenerator()->create_course();

        $this->restore_to_course($backupid, $newcourse->id);
        $modinfo = get_fast_modinfo($newcourse);

        // Assert we have our quiz including the category and question.
        $newquizzes = $modinfo->get_instances_of('quiz');
        $this->assertCount(1, $newquizzes);
        $newquiz = reset($newquizzes);
        $newquizcontext = \context_module::instance($newquiz->id);

        $quizcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $newquizcontext->id]
        );
        $this->assertCount(1, $quizcats);
        $quizcat = reset($quizcats);
        $quizcatqs = get_questions_category($quizcat, false);
        $this->assertCount(1, $quizcatqs);
        $quizq = reset($quizcatqs);
        $this->assertEquals('quiz question', $quizq->name);

        // Backup did not contain the qbank but is dependant, so make sure the categories and questions got restored
        // to a 'system' type default qbank module on the course.
        $defaultbanks = $modinfo->get_instances_of('qbank');
        $this->assertCount(1, $defaultbanks);
        $defaultbank = reset($defaultbanks);
        $defaultbankcontext = \context_module::instance($defaultbank->id);
        $bankcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $defaultbankcontext->id]
        );
        $bankcat = reset($bankcats);
        $bankqs = get_questions_category($bankcat, false);
        $this->assertCount(1, $bankqs);
        $bankq = reset($bankqs);
        $this->assertEquals('bank question', $bankq->name);
    }

    /**
     * If the backup contains BOTH a quiz and a qbank module and the quiz uses questions from the qbank module and itself,
     * then we need to restore those categories and questions to the qbank and quiz modules included in the backup on the new course.
     *
     * @return void
     */
    public function test_bank_and_quiz_activity_restore_to_new_course() {
        // Create a new course.
        global $DB;

        $this->resetAfterTest();
        self::setAdminUser();

        // Create a course to make a backup from.
        $data = $this->add_course_quiz_and_qbank();
        $oldcourse = $data->course;

        // Backup the course.
        $backupid = $this->backup_course($oldcourse->id, \backup::TYPE_1COURSE);

        // Create a new course to restore to.
        $newcourse = self::getDataGenerator()->create_course();

        // Restore it.
        $this->restore_to_course($backupid, $newcourse->id);

        // Assert the quiz got its question catregories restored.
        $modinfo = get_fast_modinfo($newcourse);
        $newquizzes = $modinfo->get_instances_of('quiz');
        $this->assertCount(1, $newquizzes);
        $newquiz = reset($newquizzes);
        $newquizcontext = \context_module::instance($newquiz->id);
        $quizcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $newquizcontext->id]
        );
        $quizcat = reset($quizcats);
        $quizcatqs = get_questions_category($quizcat, false);
        $this->assertCount(1, $quizcatqs);
        $quizcatq = reset($quizcatqs);
        $this->assertEquals('quiz question', $quizcatq->name);

        // Assert the qbank got its questions restored to the module in the backup.
        $qbanks = $modinfo->get_instances_of('qbank');
        $qbanks = array_filter($qbanks, static function($bank) {
            global $DB;
            $modrecord = $DB->get_record('qbank', ['id' => $bank->instance]);
            return $modrecord->type === helper::STANDARD;
        });
        $this->assertCount(1, $qbanks);
        $qbank = reset($qbanks);
        $bankcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => \context_module::instance($qbank->id)->id]
        );
        $bankcat = reset($bankcats);
        $bankqs = get_questions_category($bankcat, false);
        $this->assertCount(1, $bankqs);
        $bankq = reset($bankqs);
        $this->assertEquals('bank question', $bankq->name);
    }

    /**
     * The course backup file contains question banks and a quiz module.
     * There is 1 question bank category per deprecated context level i.e. CONTEXT_SYSTEM, CONTEXT_COURSECAT, and CONTEXT_COURSE.
     * The quiz included in the backup uses a question in each category.
     *
     * @return void
     */
    public function test_pre_45_course_restore_to_new_course() {
        global $DB, $USER;
        self::setAdminUser();
        $this->resetAfterTest();

        $backupid = 'question_category_43_format';
        $backuppath = make_backup_temp_directory($backupid);
        get_file_packer('application/vnd.moodle.backup')->extract_to_pathname(
                __DIR__ . "/fixtures/{$backupid}.mbz", $backuppath);

        // Do restore to new course with default settings.
        $categoryid = $DB->get_field_sql("SELECT MIN(id) FROM {course_categories}");
        $newcourseid = restore_dbops::create_new_course('Test fullname', 'Test shortname', $categoryid);
        $rc = new restore_controller($backupid, $newcourseid,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id,
                backup::TARGET_NEW_COURSE
        );

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $modinfo = get_fast_modinfo($newcourseid);

        $qbanks = $modinfo->get_instances_of('qbank');
        $qbanks = array_filter($qbanks, static function($bank) {
            global $DB;
            $modrecord = $DB->get_record('qbank', ['id' => $bank->instance]);
            return $modrecord->type === helper::SYSTEM;
        });
        $this->assertCount(1, $qbanks);
        $qbank = reset($qbanks);
        $qbankcontext = \context_module::instance($qbank->id);
        $bankcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $qbankcontext->id],
                'name ASC'
        );
        // The categories and questions in the 3 deprecated contexts
        // all got moved to the new default qbank module instance on the new course.
        $this->assertCount(3, $bankcats);
        $expectedidentifiers = [
                'Default for Category 1',
                'Default for System',
                'Default for Test Course 1',
                'Default for Quiz'
        ];
        $i = 0;

        foreach ($bankcats as $bankcat) {
            $identifer = $expectedidentifiers[$i];
            $this->assertEquals($identifer, $bankcat->name);
            $bankcatqs = get_questions_category($bankcat, false);
            $this->assertCount(1, $bankcatqs);
            $bankcatq = reset($bankcatqs);
            $this->assertEquals($identifer, $bankcatq->name);
            $i++;
        }

        // The question category and question attached to the quiz got restored to its own context correctly.
        $newquizzes = $modinfo->get_instances_of('quiz');
        $this->assertCount(1, $newquizzes);
        $newquiz = reset($newquizzes);
        $newquizcontext = \context_module::instance($newquiz->id);
        $quizcats = $DB->get_records_select('question_categories',
                'parent <> 0 AND contextid = :contextid',
                ['contextid' => $newquizcontext->id]
        );
        $quizcat = reset($quizcats);
        $quizcatqs = get_questions_category($quizcat, false);
        $this->assertCount(1, $quizcatqs);
        $quizcatq = reset($quizcatqs);
        $this->assertEquals($expectedidentifiers[$i], $quizcatq->name);
    }
}