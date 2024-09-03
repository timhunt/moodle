<?php
namespace core_course;

use advanced_testcase;
use backup_controller;
use restore_controller;
use quiz_question_helper_test_trait;
use backup;

defined('MOODLE_INTERNAL') or die();
global $CFG;

// Require necessary libraries for the test.
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/tests/quiz_question_helper_test_trait.php');

/**
 * Test case for verifying Moodle's course import functionality uses original questions after second import.
 */
class quiz_question_duplication_bug_test extends advanced_testcase {
    use quiz_question_helper_test_trait;

    /**
     * Setup routine executed before each test method.
     */
    protected function setUp(): void {
        global $USER;
        parent::setUp();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $USER;
        $this->resetAfterTest(true);
    }

    /**
     * Tests the course import process by simulating a course backup from one course and
     * restoring it into another twice, verifying that quiz questions are duplicated the
     * first time and reused from the original course the second time.
     */
    public function test_course_import() {
        // Step 1: Create two courses and a user with editing teacher capabilities.
        $generator = $this->getDataGenerator();
        $course1 = $this->course;
        $course2 = $generator->create_course();
        $teacher = $this->user;
        $generator->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $course2->id, 'editingteacher');

        
        // Create a quiz with questions in the first course.
        $quiz = $this->create_test_quiz($course1);
        $coursecontext = \context_course::instance($course1->id);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        // Create a question category.
        $cat = $questiongenerator->create_question_category(['contextid' => $coursecontext->id]);
    
        // Create a short answer question.
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        // Optionally update the question to simulate editing.
        $questiongenerator->update_question($saq);
        // Add question to quiz.
        quiz_add_quiz_question($saq->id, $quiz);
    
        // Create a numerical question.
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        // Optionally update the question to simulate multiple versions.
        $questiongenerator->update_question($numq);
        $questiongenerator->update_question($numq);
        // Add question to quiz.
        quiz_add_quiz_question($numq->id, $quiz);

        // Create a true false question.
        $tfq = $questiongenerator->create_question('truefalse', null, array('category' => $cat->id));
        // Optionally update the question to simulate multiple versions.
        $questiongenerator->update_question($tfq);
        $questiongenerator->update_question($tfq);
        // Add question to quiz.
        quiz_add_quiz_question($tfq->id, $quiz);


        // Capture original question IDs for verification after import.
        $modules1 = get_fast_modinfo($course1->id)->get_instances_of('quiz');
        $module1 = reset($modules1);
        $questionsCourse1 = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $module1->instance, $module1->context);

        $questionIdsOriginal = [];
        foreach ($questionsCourse1 as $slot) {
            array_push($questionIdsOriginal, intval($slot->questionid));
        }

        // Step 2: Backup the first course.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course1->id, backup::FORMAT_MOODLE,
                            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Step 3: Import the backup into the second course.
        $rc = new restore_controller($backupid, $course2->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT, 
                                        $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify the question ids from the quiz in the original course are different from the question ids in the duplicated quiz in the second course.
        $modules2 = get_fast_modinfo($course2->id)->get_instances_of('quiz');
        $module2 = reset($modules2);
        $questionsCourse2FirstImport = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $module2->instance, $module2->context);

        foreach ($questionsCourse2FirstImport as $slot) {
            $this->assertNotContains(intval($slot->questionid), $questionIdsOriginal, "Question ID {$slot->questionid} should not be in the original course's question IDs.");
        }

        // Repeat the backup and import process to simulate a second import.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course1->id, backup::FORMAT_MOODLE,
                            backup::INTERACTIVE_NO, backup::MODE_IMPORT, $teacher->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $rc = new restore_controller($backupid, $course2->id, backup::INTERACTIVE_NO, backup::MODE_IMPORT, 
                                        $teacher->id, backup::TARGET_CURRENT_ADDING);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        // Verify the question ids from the quiz in the original course are the same as the question ids in the second duplicated quiz in the second course.
        $modules3 = get_fast_modinfo($course2->id)->get_instances_of('quiz');
        $module3 = end($modules3);
        $questionsCourse2SecondImport = \mod_quiz\question\bank\qbank_helper::get_question_structure(
                $module3->instance, $module3->context);

        foreach ($questionsCourse2SecondImport as $slot) {
            $this->assertContains(intval($slot->questionid), $questionIdsOriginal, "Question ID {$slot->questionid} should be in the original course's question IDs.");
        }
    }
}
