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
 * The structure of the quiz. That is, which questions it is built up
 * from. This is used on the Edit quiz page (edit.php) and also when
 * starting an attempt at the quiz (startattempt.php). Once an attempt
 * has been started, then the attempt holds the specific set of questions
 * that that student should answer, and we no longer use this class.
 *
 * @package   mod_quiz
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quiz;

class structure {
    public static function create(){
        $structure = new \stdClass();
        return $structure;
    }

    public static function get_quiz_slots($quiz) {
        if(!property_exists($quiz, 'slots')){
            return array();
        }
        return $quiz->slots;
    }

    public static function set_quiz_slots($quiz, $slots) {
        if(!gettype($slots) == 'array'){
            return;
        }
        $quiz->slots = $slots;
    }

    public static function get_quiz_sections($quiz) {
        if(!property_exists($quiz, 'sections')){
            return array();
        }
        return $quiz->sections;
    }

    public static function set_quiz_sections($quiz, $sections) {
        if(!gettype($sections) == 'array'){
            return;
        }
        $quiz->sections = $sections;
    }

    public static function get_quiz_section_heading($section) {
        if(!property_exists($section, 'heading')){
            return '';
        }
        return $section->heading;
    }

    public static function populate_structure($quiz) {
        self::populate_quiz_slots($quiz);
        self::populate_quiz_questionids($quiz);
//         self::update_quiz_structure_from_questions($quiz);
        self::populate_quiz_sections($quiz);
        self::populate_slot_to_sectionids($quiz);
        self::populate_slots_with_sectionids($quiz);
    }

    /**
     * Populate quiz sections with dummy data while the database is waiting
     * to be changed
     * @param object $quiz
     * @return array
     */
    public static function populate_quiz_sections($quiz) {
        // TODO: When DB structure in place, get these from DB.
        // For now present dummy data.
        $data = array();
        $uniqueid = 1;
        // Rows are in the format array(id, quizid, firstslot, heading, shuffle)
        $data[] = array($uniqueid++, $quiz->id, 1, 'Section 1', true);
//         $data[] = array($uniqueid++, $quiz->id, 3, 'Section 2', false);
//         $data[] = array($uniqueid++, $quiz->id, 5, 'Section 3', true);
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
        $quiz->sections = $records;

        return $records;
    }

    /**
     * Update the quiz slots field using the questions field
     * @param object $quiz
     * @return array
     */
    public static function update_quiz_structure_from_questions($quiz) {
        $order = $quiz->questions;
        $quiz->slots = self::determine_slots_from_questions($quiz);

        return $order;
    }

    /**
     * Populate quiz slots for the given quiz from the DB
     * to be changed
     * @param object $quiz
     * @return array
     */
    public static function populate_quiz_slots($quiz, $sort='slot'){
        global $DB;
        $records = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), $sort);
        $quiz->slots = $records;
        return $records;
    }

    /**
     * Save quiz slots for the given quiz to the DB
     * to be changed
     * @param object $quiz
     * @return array
     */
    public static function save_quiz_slot_order($quiz) {
        global $DB;

        $records = self::get_quiz_slots($quiz);

        // Start a transaction because we are performing multiple related updates
        $transaction = $DB->start_delegated_transaction();

        $count = count($records);
        // iterate through the slots.
        foreach($records as $record){
            // To avoid unique constraint on quizid and slot set matching slot to a number
            // greater than existing slots for the quiz
            $sql = 'UPDATE {quiz_slots} SET slot = '.($record->slot+$count).' WHERE quizid='.$quiz->id.' AND slot='.$record->slot;
            $DB->execute($sql);

            $DB->update_record('quiz_slots', $record, true);

        }

        // End transaction
        $transaction->allow_commit();
    }

    /**
     * Populate quiz questionids from slots array
     * to be changed
     * @param object $quiz
     * @return void
     */
    public static function populate_quiz_questionids($quiz){
        $slots = self::get_quiz_slots($quiz);
        $questionids = array();
        foreach ($slots as $slot) {
            $questionids[] = $slot->questionid;
        }

        $quiz->questionids = $questionids;
    }

    /**
     * Populate quiz slots using the $quiz->questions string
     * to be changed
     * @param object $quiz
     * @return array
     */
    public static function determine_slots_from_questions($quiz){
        //Determine slots from questions.
        // Rows are in the format array(id, quizid, slot, page, questionid, maxmark)
        // Reflecting a $quiz->question string of '1,0,2,3,4,5,6,0,7,0,8,0,0'
        $questions = explode(',', $quiz->questions);


        $records = array();
        $currentpagenumber = 0;
        $currentslotid = 0;
        $currentslotnumber = 0;

        foreach($questions as $questionid) {

            // New pages have id=0
            if(!$questionid){
                $currentpagenumber++;
                continue;
            }

            $currentslotid++;
            $currentslotnumber++;

            $record = new \stdClass();
            $record->id = $currentslotid;
            $record->quizid = $quiz->id;
            $record->slot = $currentslotnumber;
            $record->page = $currentpagenumber;
            $record->questionid = $questionid;
            $record->maxmark = 100;
            $records[$record->id] = $record;
        }

        return $records;
    }

    /**
     * Move a slot from its current location to a new location.
     * Reorder the slot table accordingly.
     * @param object $quiz
     * @param int $id id of slot to be moved
     * @param int $idbefore id of slot to come after slot being moved
     * @return array
     */
    public static function move_slot($quiz, $id, $idbefore){

        // Get the current slots for the current quiz ordered by slot
        self::populate_quiz_slots($quiz, 'slot');
        $records = self::get_quiz_slots($quiz);

        $keyvalues = self::get_original_pagination(self::get_quiz_slots($quiz));

        // iterate through the slots.
        $slot = 1;
        $slottomove = $records[$id];
        foreach($records as $record){

            if($record->id == $id){
                continue;
            }

            if($record->id == $idbefore){
                $slottomove->slot = $slot;
                $slottomove->page = $keyvalues[$slottomove->slot];
                $slot++;
            }
            $record->slot = $slot;

            $record->page = $keyvalues[$record->slot];
            $slot++;
        }

        if($idbefore == 0){
            $slottomove->slot = $slot;
            $slottomove->page = $keyvalues[$slottomove->slot];
        }

        // Update the db
        self::save_quiz_slot_order($quiz);
    }

    public static function convert_slots_to_new_slot_objects($attemptobj, $oldslots) {
        global $DB;
        $slots = array();
        $sequesnce = 1;
        Foreach ($oldslots as $key => $oldslot) {
            $slot = new \stdClass();
            $slot->id = $sequesnce;
            $slot->quizid = $attemptobj->get_quizid();
            $slot->sectionid = 1;
            $slot->slot = $oldslot;
            $slot->page = $key;
            $slot->questionid = $attemptobj->get_questionid($oldslot);
            $slot->questioncategoryid = $attemptobj->get_question_categoryid($slot->questionid);
            $slot->includesubcategories = true;
            $slot->maxmark = $attemptobj->get_question_mark($oldslot);
            $slot->requireprevious = false;
            if ($slot->slot == 5) { // TODO: This would not be hardcoded
                $slot->requireprevious = true;
            }
            $slots[] = $slot;
            $sequesnce++;
        }
        return $slots;
    }

    public static function get_slot_object($attemptobj, $oldslotid) {
        $slots = self::convert_slots_to_new_slot_objects($attemptobj, $attemptobj->get_slots());
        if (!$slots) {
            return null;
        }
        foreach ($slots as $slot) {
            if ($slot->slot == $oldslotid) {
                return $slot;
            }
        }
        return null;
    }

    public static function populate_slot_to_sectionids($quiz) {
        $sections = self::get_quiz_sections($quiz);
        $slottosectionids = array();

        foreach ($sections as $section) {
            $slottosectionids[$section->firstslot] = $section->id;
        }

        $quiz->slottosectionids = $slottosectionids;
    }

    public static function populate_slots_with_sectionids($quiz) {
        $slots = self::get_quiz_slots($quiz);
        $sectionid = 0;
        $sectiontoslotids = array();
        $currentslottosectionid = 1;
        foreach ($slots as $slot) {
            if(array_key_exists($slot->slot, $quiz->slottosectionids)) {
                $sectionid = $quiz->slottosectionids[$slot->slot];
            }

            $slot->sectionid = $sectionid;
            if(!array_key_exists($slot->sectionid, $sectiontoslotids)) {
                $sectiontoslotids[$slot->sectionid] = array();
            }

            $sectiontoslotids[$slot->sectionid][] = $slot->id;
        }

        $quiz->sectiontoslotids = $sectiontoslotids;
    }

    /**
     * Return an array which refers to the original pagination
     * where the slot number is the key and the page number is the value
     * @param object $slots
     */
    public static function get_original_pagination($slots) {
        if (!$slots) {
            return;
        }
        $keyvalues = array();
        foreach ($slots as $slotid => $slot) {
            $keyvalues[$slot->slot] = $slot->page;
        }
        return $keyvalues;
    }

}
