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
 *
 * @package   mod_quiz
 * @copyright 2014 The Open Univsersity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group quiz
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/classes/repaginate.php');

class quiz_repaginate_testable extends quiz_repaginate {

    public function set_pages($slots) {
        return parent::set_pages($slots);
    }
    public function get_this_slot($slots, $slotnumber) {
        return parent::get_this_slot($slots, $slotnumber);
    }
    public function get_last_slot($slots) {
        return parent::get_last_slot($slots);
    }
    public function get_pages_slotnumber_slotids($slots) {
        return parent::get_pages_slotnumber_slotids($slots);
    }
    public function get_number_of_pages($slots) {
        return parent::get_number_of_pages($slots);
    }
    public function get_slots_by_slot_number($slots) {
        return parent::get_slots_by_slot_number($slots);
    }
}

/** Test for some parts of the of the quiz_reviewquestions report.
 * @copyright 2013 The Open Univsersity
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group quiz_reviewquestions
 */
class quiz_repaginate_test extends advanced_testcase {

    private $repaginate = null;

    public function setUp() {
        $this->repaginate = new mod_quiz_repaginate_testable($this->get_quiz_slots());
    }

    public function tearDown() {
        $this->repaginate = null;
    }

    /**
     * Return an slot object.
     * @param int $slotid
     * @param int $questionid
     * @param int $slotnumber
     * @param int $page
     * @return stdClass
     */
    private function get_test_slot($page, $slotnumber, $slotid, $questionid) {
        $slot = new stdClass();
        $slot->id = $slotid;
        $slot->quizid = 5;
        $slot->questionid = $questionid;
        $slot->maxmark = 1.000;
        $slot->slot = $slotnumber;
        $slot->page = $page;
        $slot->sectionid = 1;
        return $slot;
    }

    /**
     * Return new string as an array key
     * @param object $slot
     * @return string
     */
    private function get_key($slot) {
        return $slot->page . ',' . $slot->slot . ',' . $slot->id;
    }

    /**
     * Return an array of slots
     */
    private function get_quiz_slots() {
        $page = 1;
        $slotnumber = 1;
        $id = 101;
        $questionid = 11;
        $slot1 = $this->get_test_slot($page, $slotnumber, $id, $questionid);

        $page = 2;
        $slotnumber = 2;
        $id = 102;
        $questionid = 12;
        $slot2 = $this->get_test_slot($page, $slotnumber, $id, $questionid);

        $page = 3;
        $slotnumber = 3;
        $id = 103;
        $questionid = 13;
        $slot3 = $this->get_test_slot($page, $slotnumber, $id, $questionid);

        $page = 4;
        $slotnumber = 4;
        $id = 104;
        $questionid = 14;
        $slot4 = $this->get_test_slot($page, $slotnumber, $id, $questionid);

        $page = 5;
        $slotnumber = 5;
        $id = 105;
        $questionid = 15;
        $slot5 = $this->get_test_slot($page, $slotnumber, $id, $questionid);

        return array(
                $slot1->id => $slot1,
                $slot2->id => $slot2,
                $slot3->id => $slot3,
                $slot4->id => $slot4,
                $slot5->id => $slot5);
    }

    /**
     * Test the get_pages_slotnumber_slotids() method
     * by checking the return value with the expected one
     */
    public function test_get_pages_slotnumber_slotids() {
        $quizslots = $this->get_quiz_slots();

        $returnedobject = $this->repaginate->get_pages_slotnumber_slotids($quizslots);
        $expectedobject = array();
        foreach ($quizslots as $slot) {
            $expectedobject[$this->get_key($slot)] = $slot;
        }
        $this->assertEquals($expectedobject, $returnedobject);
    }

    /**
     * Test the get_this_slot() method
     */
    public function test_get_this_slot() {
        $slots = $this->get_quiz_slots();
        $slots = $this->repaginate->get_slots_by_slot_number($slots);
        $slotnumber = 5;
        $thisslot = $this->repaginate->get_this_slot($slots, $slotnumber);
        $this->assertEquals($slots[$slotnumber], $thisslot);
    }

    /**
     * Test get_number of pages() method
     */
    public function test_get_number_of_pages() {
        $slots = $this->get_quiz_slots();
        $numberofpages = $this->repaginate->get_number_of_pages($slots);
        $this->assertEquals(count($slots), $numberofpages);
    }

    public function test_get_slots_by_slotnumber() {
        $slots = $this->get_quiz_slots();

        $expected = array();
        foreach ($slots as $slot) {
            $expected[$slot->slot] = $slot;
        }
        $actual = $this->repaginate->get_slots_by_slot_number($slots);
        $this->assertEquals($expected, $actual);
    }

    public function test_repaginate_n_questions_per_page() {
        $slots = $this->get_quiz_slots();

        // Expec 2 questions per page.
        $expected = array();
        foreach ($slots as $slot) {
            // Page 1 contains Slots 1 and 2.
            if ($slot->slot >= 1 && $slot->slot <= 2) {
                $slot->page = 1;
            }
            // Page 2 contains slots 3 and 4.
            if ($slot->slot >= 3 && $slot->slot <= 4) {
                $slot->page = 2;
            }
            // Page 3 contains slots 5.
            if ($slot->slot >= 5 && $slot->slot <= 6) {
                $slot->page = 3;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($slots, 2);
        $this->assertEquals($expected, $actual);

        // Expec 3 questions per page.
        $expected = array();
        foreach ($slots as $slot) {
            // Page 1 contains Slots 1, 2 and 3.
            if ($slot->slot >= 1 && $slot->slot <= 3) {
                $slot->page = 1;
            }
            // Page 2 contains slots 4 and 5.
            if ($slot->slot >= 4 && $slot->slot <= 6) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($slots, 3);
        $this->assertEquals($expected, $actual);

        // Expec 5 questions per page.
        $expected = array();
        foreach ($slots as $slot) {
            // Page 1 contains Slots 1, 2, 3, 4 and 5.
            if ($slot->slot > 0 && $slot->slot < 6) {
                $slot->page = 1;
            }
            // Page 2 contains slots 6, 7, 8, 9 and 10.
            if ($slot->slot > 5 && $slot->slot < 11) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($slots, 5);
        $this->assertEquals($expected, $actual);

        // Expec 10 questions per page.
        $expected = array();
        foreach ($slots as $slot) {
            // Page 1 contains Slots 1 to 10.
            if ($slot->slot >= 1 && $slot->slot <= 10) {
                $slot->page = 1;
            }
            // Page 2 contains slots 11 to 20.
            if ($slot->slot >= 11 && $slot->slot <= 20) {
                $slot->page = 2;
            }
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($slots, 10);
        $this->assertEquals($expected, $actual);

        // Expec 1 questions per page.
        $expected = array();
        $page = 1;
        foreach ($slots as $slot) {
            $slot->page = $page++;
            $expected[$slot->id] = $slot;
        }
        $actual = $this->repaginate->repaginate_n_question_per_page($slots, 1);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test repaginate() method when using 'join'
     */
    public function test_repaginate_join() {

        // Expected slot 3 on page 2 and repagination after that.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(2, 3, 103, 13),
            '104' => $this->get_test_slot(3, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));

        $currentslotnumber = 2;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($this->get_quiz_slots(), $nextslotnumber, 'join');
        $this->assertEquals($expected, $actual);

        // Expected slot 2 on page 1 and repagination after that.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(1, 2, 102, 12),
            '103' => $this->get_test_slot(2, 3, 103, 13),
            '104' => $this->get_test_slot(3, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));
        $currentslotnumber = 1;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($this->get_quiz_slots(), $nextslotnumber, 'join');
        $this->assertEquals($expected, $actual);

         // Expected slot 5 on page 4 and repagination after that.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(4, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));
        $currentslotnumber = 4;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($this->get_quiz_slots(), $nextslotnumber, 'join');
        $this->assertEquals($expected, $actual);

        // Expected slot 4 on page 3 and repagination after that.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(3, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));
        $currentslotnumber = 3;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($this->get_quiz_slots(), $nextslotnumber, 'join');
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test repaginate() method when using 'separate'
     */
    public function test_repaginate_separate() {

        $slots = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(2, 3, 103, 13),
            '104' => $this->get_test_slot(3, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));

        // Expected slot 3 on page 3 and repagination the rest.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(4, 4, 104, 14),
            '105' => $this->get_test_slot(5, 5, 105, 15));

        $currentslotnumber = 2;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($slots, $nextslotnumber, 'separate');
        $this->assertEquals($expected, $actual);

        $slots2 = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(1, 2, 102, 12),
            '103' => $this->get_test_slot(2, 3, 103, 13),
            '104' => $this->get_test_slot(3, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));

        // Expected slot 2 on page 2 and repagination the rest.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(4, 4, 104, 14),
            '105' => $this->get_test_slot(5, 5, 105, 15));

        $currentslotnumber = 1;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($slots2, $nextslotnumber, 'separate');
        $this->assertEquals($expected, $actual);

        $slots3 = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(4, 4, 104, 14),
            '105' => $this->get_test_slot(4, 5, 105, 15));

        // Expected slot 5 on page 5 and repagination the rest.
        $expected = array(
            '101' => $this->get_test_slot(1, 1, 101, 11),
            '102' => $this->get_test_slot(2, 2, 102, 12),
            '103' => $this->get_test_slot(3, 3, 103, 13),
            '104' => $this->get_test_slot(4, 4, 104, 14),
            '105' => $this->get_test_slot(5, 5, 105, 15));

        $currentslotnumber = 1;
        $nextslotnumber = $currentslotnumber + 1;
        $actual = $this->repaginate->repaginate($slots3, $nextslotnumber, 'separate');
        $this->assertEquals($expected, $actual);
    }
}
