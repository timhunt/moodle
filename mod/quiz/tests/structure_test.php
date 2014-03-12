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
 * Quiz events tests.
 *
 * @package    mod_quiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');


class mod_quiz_testable_structure extends \mod_quiz\structure {
    public function set_quiz_slots(array $slots) {
        $this->slots = $slots;
    }

    public function set_quiz_sections(array $sections) {
        $this->sections = $sections;
    }

}


/**
 * Unit tests for quiz events.
 *
 * @package    mod_quiz
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_structure_testcase extends advanced_testcase {

    public $sections = array();

    protected function prepare_quiz_data() {

        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Make a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance(array('course'=>$course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2));

        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));
        $numq = $questiongenerator->create_question('numerical', null, array('category' => $cat->id));

        // Add them to the quiz.
        quiz_add_quiz_question($saq->id, $quiz);
        quiz_add_quiz_question($numq->id, $quiz);

        return $quiz;
    }

    public function test_create() {
        $structure = \mod_quiz\structure::create();

        $this->assertInstanceOf('\mod_quiz\structure', $structure);
    }

    public function test_get_quiz_slots() {
        // Get empty quiz.
        $quiz = $this->get_dummy_quiz();
        $structure = new mod_quiz_testable_structure();

        // When no slots exist or slots propery is not set.
        $slots = $structure->get_quiz_slots();
        $this->assertInternalType('array', $slots);
        $this->assertCount(0, $slots);

        // Append slots to the quiz.
        $testslots = $this->get_dummy_quiz_slots($quiz);
        $structure->set_quiz_slots($testslots);

        // Are the correct slots returned?
        $slots = $structure->get_quiz_slots();
        $this->assertEquals($testslots, $slots);
    }

    public function test_get_quiz_sections() {

        // When no sections exist or sections propery is not set.
        $quiz = $this->get_dummy_quiz();
        $structure = new mod_quiz_testable_structure();

        $sections = $structure->get_quiz_sections();
        $this->assertInternalType('array', $sections);
        $this->assertCount(0, $sections);

        // Append sections to the quiz.
        $testsections = $this->get_dummy_quiz_sections($quiz);
        $structure->set_quiz_sections($testsections);

        // Are the correct sections returned?
        $sections = $structure->get_quiz_sections();
        $this->assertCount(count($testsections), $sections);
        $this->assertEquals($testsections, $sections);
    }

    /**
     * Test removing slots from a quiz.
     */
    public function test_quiz_remove_slot() {
        global $SITE, $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup a quiz with 1 standard and 1 random question.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(array('course' => $SITE->id, 'questionsperpage' => 3, 'grade' => 100.0));

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $standardq = $questiongenerator->create_question('shortanswer', null, array('category' => $cat->id));

        quiz_add_quiz_question($standardq->id, $quiz);
        quiz_add_random_questions($quiz, 0, $cat->id, 1, false);

        // Get the random question.
        $randomq = $DB->get_record('question', array('qtype' => 'random'));

        $slotssql = "SELECT qs.*, q.qtype AS qtype
                       FROM {quiz_slots} qs
                       JOIN {question} q ON qs.questionid = q.id
                      WHERE qs.quizid = ?
                   ORDER BY qs.slot";
        $slots = $DB->get_records_sql($slotssql, array($quiz->id));

        // Check that the setup looks right.
        $this->assertEquals(2, count($slots));
        $slot = array_shift($slots);
        $this->assertEquals($standardq->id, $slot->questionid);
        $slot = array_shift($slots);
        $this->assertEquals($randomq->id, $slot->questionid);
        $this->assertEquals(2, $slot->slot);

        // Remove the standard question.
        $structure = \mod_quiz\structure::create();
        $structure->remove_slot($quiz, 1);

        $slots = $DB->get_records_sql($slotssql, array($quiz->id));

        // Check the new ordering, and that the slot number was updated.
        $this->assertEquals(1, count($slots));
        $slot = array_shift($slots);
        $this->assertEquals($randomq->id, $slot->questionid);
        $this->assertEquals(1, $slot->slot);

        // Check the the standard question was not deleted.
        $count = $DB->count_records('question', array('id' => $standardq->id));
        $this->assertEquals(1, $count);

        // Remove the random question.
        $structure = \mod_quiz\structure::create();
        $structure->remove_slot($quiz, 1);

        $slots = $DB->get_records_sql($slotssql, array($quiz->id));

        // Check that new ordering.
        $this->assertEquals(0, count($slots));

        // Check that the random question was deleted.
        $count = $DB->count_records('question', array('id' => $randomq->id));
        $this->assertEquals(0, $count);
    }

    /**
     * Create a basic quiz object for testing
     * @return object
     */
    public function get_dummy_quiz() {
        $quiz = new stdClass();
        $quiz->id = 1;
        return $quiz;
    }

    /**
     * Populate quiz slots with dummy data while the database is waiting
     * @param object $quiz
     * @return array
     */
    public function get_dummy_quiz_slots($quiz) {
        // Define the data.
        $data = array();
        $uniqueid = 1;
        $pagenumber = 0;

        // Rows are in the format array(id, quizid, slot, page, questionid, maxmark)
        $data[] = array($uniqueid++, $quiz->id, 1, $pagenumber, 1, 100);
        $data[] = array($uniqueid++, $quiz->id, 2, ++$pagenumber, 2, 100);
        $data[] = array($uniqueid++, $quiz->id, 3, $pagenumber, 3, 100);
        $data[] = array($uniqueid++, $quiz->id, 4, $pagenumber, 4, 100);
        $data[] = array($uniqueid++, $quiz->id, 5, $pagenumber, 5, 100);
        $data[] = array($uniqueid++, $quiz->id, 6, $pagenumber, 6, 100);
        $data[] = array($uniqueid++, $quiz->id, 7, ++$pagenumber, 7, 100);
        $data[] = array($uniqueid++, $quiz->id, 8, ++$pagenumber, 8, 100);

        // Translate data into records.
        $records = array();
        foreach ($data as $row) {
            $record = new \stdClass();
            $record->id = $row[0];
            $record->quizid = $row[1];
            $record->slot = $row[2];
            $record->page = $row[3];
            $record->questionid = $row[4];
            $record->maxmark = $row[5];
            $records[$record->id] = $record;
        }

        return $records;
    }

    /**
     * Populate quiz sections with dummy data while the database is waiting
     * to be changed
     * @param object $quiz
     * @return array
     */
    public function get_dummy_quiz_sections($quiz) {
        // TODO MDL-43089: When DB structure in place, get these from DB.
        $data = array();
        // Rows are in the format array(id, quizid, firstslot, heading, shuffle).
        $data[] = array(1, $quiz->id, 1, 'Section 1', true);
        $records = array();

        // Temp: create number of sections.
        foreach ($data as $row) {
            $record = new \stdClass();
            $record->id = $row[0];
            $record->quizid = $row[1];
            $record->firstslot = $row[2];
            $record->heading = $row[3];
            $record->shuffle = $row[4];
            $records[$record->id] = $record;
        }

        return $records;
    }
}
