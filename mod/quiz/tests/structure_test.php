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

        // Create a course
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

        $this->sections = $this->get_dummy_quiz_sections($quiz);

        return array($quiz);
    }

    public function test_create() {
        $structure = \mod_quiz\structure::create();

        $this->assertInstanceOf('\stdClass', $structure);
    }

    public function test_get_quiz_slots(){
        // Get empty quiz.
        $quiz = $this->get_dummy_quiz();

        // When no slots exist or slots propery is not set.
        $slots = \mod_quiz\structure::get_quiz_slots($quiz);
        $this->assertInternalType('array', $slots);
        $this->assertCount(0, $slots);

        // Append slots to the quiz.
        $this->slots = $this->get_dummy_quiz_slots($quiz);
        $quiz->slots = $this->slots;

        // Are the correct slots returned?
        $slots = \mod_quiz\structure::get_quiz_slots($quiz);
        $this->assertCount(count($this->slots), $slots);
        $this->assertEquals($this->slots, $slots);
    }

    public function test_set_quiz_slots(){
        // Get empty quiz and test data.
        $quiz = $this->get_dummy_quiz();
        $this->slots = $this->get_dummy_quiz_slots($quiz);

        // Set sections
        \mod_quiz\structure::set_quiz_slots($quiz, $this->slots);

        // Are the correct slots returned?
        $slots = \mod_quiz\structure::get_quiz_slots($quiz);
        $this->assertCount(count($this->slots), $slots);
        $this->assertEquals($this->slots, $slots);
    }

    public function test_get_quiz_sections(){
        // Get empty quiz.
        $quiz = $this->get_dummy_quiz();

        // When no sections exist or sections propery is not set.
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertInternalType('array', $sections);
        $this->assertCount(0, $sections);

        // Append sections to the quiz.
        $this->sections = $this->get_dummy_quiz_sections($quiz);
        $quiz->sections = $this->sections;

        // Are the correct sections returned?
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertCount(count($this->sections), $sections);
        $this->assertEquals($this->sections, $sections);
    }

    public function test_set_quiz_sections(){
        // Get empty quiz.
        $quiz = $this->get_dummy_quiz();
        $this->sections = $this->get_dummy_quiz_sections($quiz);

        // Set sections
        \mod_quiz\structure::set_quiz_sections($quiz, $this->sections);

        // Are the correct sections returned?
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertCount(count($this->sections), $sections);
        $this->assertEquals($this->sections, $sections);
    }

    public function test_populate_quiz_sections(){
        /**
         * The database structure doesn't yet exist.
         */
        // test empty quiz
        $quiz = $this->get_dummy_quiz();
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertInternalType('array', $sections);
        $this->assertCount(0, $sections);

        list($quiz) = $this->prepare_quiz_data();

        \mod_quiz\structure::populate_quiz_sections($quiz);
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertCount(count($this->sections), $sections);

    }

    public function test_update_quiz_structure_from_questions() {
        // test empty quiz
        $quiz = $this->get_dummy_quiz();
        \mod_quiz\structure::update_quiz_structure_from_questions($quiz);

        // Are slots created correctly from $quiz->questions?
        $this->slots = $this->get_dummy_quiz_slots($quiz);
        $this->assertCount(count($this->slots), \mod_quiz\structure::get_quiz_slots($quiz));

        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        $this->assertInternalType('array', $sections);
        $this->assertCount(0, $sections);
    }

    /**
     * Setup functions
     */

    /**
     * Create a basic quiz object for testing
     * @return object
     */
    public function get_dummy_quiz() {
        $quiz = new stdClass();
        $quiz->id = 1;
        $quiz->questions = $this->get_dummy_questions_string();

        return $quiz;
    }

    /**
     * Populate quiz slots with dummy data while the database is waiting
     * to be changed
     * @param object $quiz
     * @return array
     */
    public function get_dummy_quiz_slots($quiz) {
        // TODO: When DB structure in place, get these from DB.

        // Define the data.
        $data = array();
        // Rows are in the format array(id, quizid, slot, page, questionid, maxmark)
        // Reflecting a $quiz->question string of '1,0,2,3,4,5,6,0,7,0,8,0,0'
        $uniqueid = 1;
        $pagenumber = 0;

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
        foreach($data as $row) {
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
        // TODO: When DB structure in place, get these from DB.
        $data = array();
        // Rows are in the format array(id, quizid, firstslot, heading, shuffle)
        $data[] = array(1, $quiz->id, 1, 'Section 1', true);
        $data[] = array(2, $quiz->id, 3, 'Section 2', false);
        $data[] = array(3, $quiz->id, 5, 'Section 3', true);
        $records = array();

        // Temp: create number of sections.
        foreach($data as $row) {
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

    /**
     * Mimic the original questions property of the quiz object
     * @return string
     */
    public function get_dummy_questions_string(){
        return '1,0,2,3,4,5,6,0,7,0,8,0,0';
    }

}
