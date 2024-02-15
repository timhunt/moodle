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

namespace core_question;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/question/tests/backup_restore_trait.php';

/**
 * Class core_question_backup_testcase
 *
 * @package    core_question
 * @category   test
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_test extends \advanced_testcase {

    use backup_restore_trait;

    /**
     * This function tests backup and restore of question tags.
     */
    public function test_backup_question_tags() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a new course category and a new course in that.
        $category1 = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $courseshortname = $course->shortname;
        $coursefullname = $course->fullname;

        // Create 2 questions.
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $context = \context_module::instance($qbank->cmid);
        $qcat = $qgen->create_question_category(['contextid' => $context->id]);
        $question1 = $qgen->create_question('shortanswer', null, ['category' => $qcat->id, 'idnumber' => 'q1']);
        $question2 = $qgen->create_question('shortanswer', null, ['category' => $qcat->id, 'idnumber' => 'q2']);

        // Tag the questions with 2 question tags.
        $qcontext = \context::instance_by_id($qcat->contextid);
        $coursecontext = \context_course::instance($course->id);
        \core_tag_tag::set_item_tags('core_question', 'question', $question1->id, $qcontext, ['qtag1', 'qtag2']);
        \core_tag_tag::set_item_tags('core_question', 'question', $question2->id, $qcontext, ['qtag3', 'qtag4']);

        // Create a quiz and add one of the questions to that.
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        quiz_add_quiz_question($question1->id, $quiz);

        // Backup the course twice for future use.
        $backupid1 = $this->backup_course($course->id);
        $backupid2 = $this->backup_course($course->id);

        // Now delete almost everything.
        delete_course($course, false);
        question_delete_question($question1->id);
        question_delete_question($question2->id);

        // Restore the backup we had made earlier into a new course.
        // Do restore to new course with default settings.
        $courseid2 = \restore_dbops::create_new_course($coursefullname, $courseshortname . '_2', $category1->id);
        $this->restore_to_course($backupid1, $courseid2);
        $modinfo = get_fast_modinfo($courseid2);
        $qbanks = $modinfo->get_instances_of('qbank');
        $qbanks = array_filter($qbanks, static fn($qbank) => $qbank->get_name() === 'Question bank 1');
        $this->assertCount(1, $qbanks);
        $qbank = reset($qbanks);
        $qbankcontext = \context_module::instance($qbank->id);
        $cats = $DB->get_records_select('question_categories' , 'parent <> 0', ['contextid' => $qbankcontext->id]);
        $this->assertCount(1, $cats);
        $cat = reset($cats);

        // The questions should be restored to a mod_qbank context in the new course.
        $sql = 'SELECT q.*,
                       qbe.idnumber
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid = ?
                 ORDER BY qbe.idnumber';
        $questions = $DB->get_records_sql($sql, [$cat->id]);
        $this->assertCount(2, $questions);

        // Retrieve tags for each question and check if they are assigned at the right context.
        $qcount = 1;
        foreach ($questions as $question) {
            $tags = \core_tag_tag::get_item_tags('core_question', 'question', $question->id);

            // Each question is tagged with 4 tags (2 question tags + 2 course tags).
            $this->assertCount(2, $tags);

            foreach ($tags as $tag) {
                $this->assertEquals($qbankcontext->id, $tag->taginstancecontextid);
            }

            // Also check idnumbers have been backed up and restored.
            $this->assertEquals('q' . $qcount, $question->idnumber);
            $qcount++;
        }

        // Now, again, delete everything including the course category.
        delete_course($courseid2, false);
        foreach ($questions as $question) {
            question_delete_question($question->id);
        }
        $category1->delete_full(false);

        // Create a new course category to restore the backup file into it.
        $category2 = $this->getDataGenerator()->create_category();

        // Restore to a new course in the new course category.
        $courseid3 = \restore_dbops::create_new_course($coursefullname, $courseshortname . '_3', $category2->id);
        $this->restore_to_course($backupid2, $courseid3);
        $modinfo = get_fast_modinfo($courseid3);
        $qbanks = $modinfo->get_instances_of('qbank');
        $qbanks = array_filter($qbanks, static fn($qbank) => $qbank->get_name() === 'Question bank 1');
        $this->assertCount(1, $qbanks);
        $qbank = reset($qbanks);
        $context = \context_module::instance($qbank->id);

        // The questions should have been moved to a question category that belongs to a course context.
        $questions = $DB->get_records_sql("SELECT q.*
                                                FROM {question} q
                                                JOIN {question_versions} qv ON qv.questionid = q.id
                                                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                               WHERE qc.contextid = ?", [$context->id]);
        $this->assertCount(2, $questions);

        // Now, retrieve tags for each question and check if they are assigned at the right context.
        foreach ($questions as $question) {
            $tags = \core_tag_tag::get_item_tags('core_question', 'question', $question->id);

            // Each question is tagged with 2 tags (all are question context tags now).
            $this->assertCount(2, $tags);

            foreach ($tags as $tag) {
                $this->assertEquals($context->id, $tag->taginstancecontextid);
            }
        }

    }

    /**
     * Test that the question author is retained when they are enrolled in to the course.
     */
    public function test_backup_question_author_retained_when_enrolled() {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course, a category and a user.
        $course = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $user = $this->getDataGenerator()->create_user();

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncategory = $questiongenerator->create_question_category();
        $overrides = ['name' => 'Test question', 'category' => $questioncategory->id,
                'createdby' => $user->id, 'modifiedby' => $user->id];
        $question = $questiongenerator->create_question('truefalse', null, $overrides);

        // Create a quiz and a questions.
        $quiz = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        quiz_add_quiz_question($question->id, $quiz);

        // Enrol user with a teacher role.
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id, 'manual');

        // Backup the course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        // Delete the original course and related question.
        delete_course($course, false);
        question_delete_question($question->id);

        // Restore the course.
        $restoredcourseid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_1', $category->id);
        $rc = new \restore_controller($backupid, $restoredcourseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Test the question author.
        $questions = $DB->get_records('question', ['name' => 'Test question']);
        $this->assertCount(1, $questions);
        $question3 = array_shift($questions);
        $this->assertEquals($user->id, $question3->createdby);
        $this->assertEquals($user->id, $question3->modifiedby);
    }

    /**
     * Test that the question author is retained when they are not enrolled in to the course,
     * but we are restoring the backup at the same site.
     */
    public function test_backup_question_author_retained_when_not_enrolled() {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course, a category and a user.
        $course = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $user = $this->getDataGenerator()->create_user();

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncategory = $questiongenerator->create_question_category();
        $overrides = ['name' => 'Test question', 'category' => $questioncategory->id,
                'createdby' => $user->id, 'modifiedby' => $user->id];
        $question = $questiongenerator->create_question('truefalse', null, $overrides);

        // Create a quiz and a questions.
        $quiz = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        quiz_add_quiz_question($question->id, $quiz);

        // Backup the course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        // Delete the original course and related question.
        delete_course($course, false);
        question_delete_question($question->id);

        // Restore the course.
        $restoredcourseid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_1', $category->id);
        $rc = new \restore_controller($backupid, $restoredcourseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Test the question author.
        $questions = $DB->get_records('question', ['name' => 'Test question']);
        $this->assertCount(1, $questions);
        $question = array_shift($questions);
        $this->assertEquals($user->id, $question->createdby);
        $this->assertEquals($user->id, $question->modifiedby);
    }

    /**
     * Test that the current user is set as a question author when we are restoring the backup
     * at the another site and the question author is not enrolled in to the course.
     */
    public function test_backup_question_author_reset() {
        global $DB, $USER, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create a course, a category and a user.
        $course = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        $user = $this->getDataGenerator()->create_user();

        // Create a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $questioncategory = $questiongenerator->create_question_category();
        $overrides = ['name' => 'Test question', 'category' => $questioncategory->id,
                'createdby' => $user->id, 'modifiedby' => $user->id];
        $question = $questiongenerator->create_question('truefalse', null, $overrides);

        // Create a quiz and a questions.
        $quiz = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id));
        quiz_add_quiz_question($question->id, $quiz);

        // Backup the course.
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_SAMESITE, $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/' . $backupid;
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        // Delete the original course and related question.
        delete_course($course, false);
        question_delete_question($question->id);

        // Emulate restoring to a different site.
        set_config('siteidentifier', random_string(32) . 'not the same site');

        // Restore the course.
        $restoredcourseid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_1', $category->id);
        $rc = new \restore_controller($backupid, $restoredcourseid, \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE, $USER->id, \backup::TARGET_NEW_COURSE);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Test the question author.
        $questions = $DB->get_records('question', ['name' => 'Test question']);
        $this->assertCount(1, $questions);
        $question = array_shift($questions);
        $this->assertEquals($USER->id, $question->createdby);
        $this->assertEquals($USER->id, $question->modifiedby);
    }
}
