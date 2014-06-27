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
    /** @var stdClass[] the quiz_slots rows for this quiz. */
    protected $slots = array();

    /**
     * @var stdClass[] will be the quiz_sections rows, once that table exists.
     * For now contains one dummy section.
     */
    protected $sections = array();

    /**
     * @var int[] slot number => section id, for the first slot in each section.
     */
    protected $slottosectionids = array();

    /**
     * @var int[][] section number => slot ids, the slots in each section.
     */
    protected $sectiontoslotids = array();


    /**
     * Create an instance of this class representing an empty quiz.
     * @return structure
     */
    public static function create() {
        return new self();
    }

    /**
     * Create an instance of this class representing the structure of a given quiz.
     * @return structure
     */
    public static function create_for($quiz) {
        $structure = self::create();
        $structure->populate_structure($quiz);
        return $structure;
    }

    /**
     * @return stdClass[] the slots in this quiz.
     */
    public function get_quiz_slots() {
        return $this->slots;
    }

    /**
     * Get a slot by it's id. Throws an exception if it is missing.
     * @return stdClass the requested slot.
     */
    public function get_slot_by_id($slotid) {
        if (!array_key_exists($slotid, $this->slots)) {
            throw new dml_missing_record_exception('quiz_slots');
        }
        return $this->slots[$slotid];
    }

    /**
     * @return stdClass[] the sections in this quiz.
     */
    public function get_quiz_sections() {
        return $this->sections;
    }

    /**
     * @return int[][] the slots in each section.
     */
    public function get_sections_and_slots() {
        return $this->sectiontoslotids;
    }

    public function get_quiz_section_heading($section) {
        if (!property_exists($section, 'heading')) {
            return '';
        }
        return $section->heading;
    }

    /**
     * Populate this class with the structure for a given quiz.
     * @param unknown_type $quiz
     */
    public function populate_structure($quiz) {
        $this->populate_quiz_slots($quiz);
        $this->populate_quiz_sections($quiz);
        $this->populate_slot_to_sectionids($quiz);
        $this->populate_slots_with_sectionids($quiz);
    }

    /**
     * Populate quiz slots for the given quiz from the DB.
     * @param stdClass $quiz
     */
    public function populate_quiz_slots($quiz) {
        global $DB;
        $this->slots = $DB->get_records('quiz_slots',
                array('quizid' => $quiz->id), 'slot');
    }

    /**
     * Populate quiz sections with dummy data while the database is waiting.
     * @param stdClass $quiz
     */
    public function populate_quiz_sections($quiz) {
        $this->sections = array(
            1 => (object) array('id' => 1, 'quizid' => $quiz->id, 1,
                    'heading' => 'Section 1', 'firstslot' => 1, 'shuffle' => false)
        );
    }

    public function populate_slot_to_sectionids($quiz) {
        foreach ($this->sections as $section) {
            $this->slottosectionids[$section->firstslot] = $section->id;
        }
    }

    public function populate_slots_with_sectionids($quiz) {
        $slots = $this->get_quiz_slots($quiz);
        $sectionid = 0;
        $sectiontoslotids = array();
        $currentslottosectionid = 1;
        foreach ($slots as $slot) {
            if (array_key_exists($slot->slot, $this->slottosectionids)) {
                $sectionid = $this->slottosectionids[$slot->slot];
            }

            $slot->sectionid = $sectionid;
            if (!array_key_exists($slot->sectionid, $sectiontoslotids)) {
                $sectiontoslotids[$slot->sectionid] = array();
            }

            $sectiontoslotids[$slot->sectionid][] = $slot->id;
        }

        $this->sectiontoslotids = $sectiontoslotids;
    }

    public function create_slot_to_slotids($slots) {
        $slottoslotids = array();
        foreach ($slots as $slot) {
            $slottoslotids[$slot->slot] = $slot->id;
        }
        return $slottoslotids;
    }

    /**
     * Move a slot from its current location to a new location.
     * Reorder the slot table accordingly.
     * @param stdClass $quiz
     * @param int $id id of slot to be moved
     * @param int $idbefore id of slot to come before slot being moved
     * @return array
     */
    public function move_slot($quiz, $idmove, $idbefore, $page) {
        global $DB, $CFG;

        $slottoslotids = $this->create_slot_to_slotids($this->slots);
        $movingslot = $this->slots[$idmove];

        // Empty target slot means move slot to first.
        if (empty($idbefore)) {
            $targetslot = $this->slots[$slottoslotids[1]];
        }
        else {
            $targetslot = $this->slots[$idbefore];
        }
        $hasslotmoved = false;
        $pagehaschanged = false;

        if (empty($movingslot)) {
            throw new moodle_exception('Bad slot ID ' . $idmove);
        }

        // Unit tests convert slot values to strings. Need as int.
        $movingslotnumber = intval($movingslot->slot);
        $targetslotnumber = intval($targetslot->slot);

        $trans = $DB->start_delegated_transaction();
        // Move slots if slots haven't already been moved exit.
        if ($targetslotnumber - $movingslotnumber !== -1  ) {

            $slotreorder = array($movingslotnumber => $targetslotnumber);
            if ($movingslotnumber < $targetslotnumber) {
                $hasslotmoved = true;
                for ($i = $movingslotnumber; $i < $targetslotnumber; $i += 1) {
                    $slotreorder[$i + 1] = $i;
                }
            } else if ($movingslotnumber > $targetslotnumber) {
                $hasslotmoved = true;
                for ($i = $targetslotnumber; $i < $movingslotnumber; $i += 1) {
                    $slotreorder[$i] = $i + 1;
                }
            }

            // Slot has moved record new order.
            if ($hasslotmoved) {
                update_field_with_unique_index('quiz_slots',
                        'slot', $slotreorder, array('quizid' => $quiz->id));
            }
        }

        // Page has changed. Record it.
        if (!$page) {
            $page = 1;
        }

        if ($movingslot->page !== $page) {
            $DB->set_field('quiz_slots', 'page', $page,
                    array('id' => $movingslot->id));
            $pagehaschanged = true;
        }


        // Slot dropped back where it came from.
        if (!$hasslotmoved && !$pagehaschanged){
            $trans->allow_commit();
            return;
        }

        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/classes/repaginate.php');

        /*
         * Update page numbering.
         */

        // Get slots ordered by page then slot.
        $slots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), 'slot, page');

        // Loop slots. Start Page number at 1 and increment as required.
        $pagenumbers = array('new' => 0, 'old' => 0);
        foreach ($slots as $slot) {
            if ($slot->page !== $pagenumbers['old']) {
                $pagenumbers['old'] = $slot->page;
                ++$pagenumbers['new'];
            }

            if ($pagenumbers['new'] == $slot->page) {
                continue;
            }
            $slot->page = $pagenumbers['new'];
        }

        // Record new page order.
        foreach ($slots as $slot) {
            $DB->set_field('quiz_slots', 'page', $slot->page,
                    array('id' => $slot->id));
        }
        $trans->allow_commit();

        $this->slots = $slots;
    }

    /**
     * Remove a question from a quiz
     * @param object $quiz the quiz object.
     * @param int $questionid The id of the question to be deleted.
     */
    public function remove_slot($quiz, $slotnumber) {
        global $DB;

        $slot = $DB->get_record('quiz_slots', array('quizid' => $quiz->id, 'slot' => $slotnumber));
        $maxslot = $DB->get_field_sql('SELECT MAX(slot) FROM {quiz_slots} WHERE quizid = ?', array($quiz->id));
        if (!$slot) {
            return;
        }

        $trans = $DB->start_delegated_transaction();
        $DB->delete_records('quiz_slots', array('id' => $slot->id));
        for ($i = $slot->slot + 1; $i <= $maxslot; $i++) {
            $DB->set_field('quiz_slots', 'slot', $i - 1,
                    array('quizid' => $quiz->id, 'slot' => $i));
        }

        $qtype = $DB->get_field('question', 'qtype', array('id' => $slot->questionid));
        if ($qtype === 'random') {
            // This function automatically checks if the question is in use, and won't delete if it is.
            question_delete_question($slot->questionid);
        }

        $trans->allow_commit();
    }

    /**
     * Change the max mark for a slot.
     *
     * Saves changes to the question grades in the quiz_slots table and any
     * corresponding question_attempts.
     * It does not update 'sumgrades' in the quiz table.
     *
     * @param stdClass $slot    row from the quiz_slots table.
     * @param float    $maxmark the new maxmark.
     * @return bool true if the new grade is different from the old one.
     */
    public function update_slot_maxmark($slot, $maxmark) {
        global $DB;

        if (abs($maxmark - $slot->maxmark) < 1e-7) {
            // Grade has not changed. Nothing to do.
            return false;
        }

        $trans = $DB->start_delegated_transaction();
        $slot->maxmark = $maxmark;
        $DB->update_record('quiz_slots', $slot);
        \question_engine::set_max_mark_in_attempts(new \qubaids_for_quiz($slot->quizid),
                $slot->slot, $maxmark);
        $trans->allow_commit();

        return true;
    }

    /**
     * link/unlink a slot to a page.
     *
     * Saves changes to the slot page relationship in the quiz_slots table and reorders the paging
     * for subsequent slots.
     *
     * @param stdClass $slot    row from the quiz_slots table.
     * @param float    $maxmark the new maxmark.
     * @return bool true if the new grade is different from the old one.
     */
    public function link_slot_to_page($quiz, $slot, $type) {
        global $DB;
        require_once("locallib.php");
        require_once('classes/repaginate.php');
        $quizid = $quiz->id;
        $slotnumber = $slot + 1;
        $repagtype = $type;
        $quizslots = $DB->get_records('quiz_slots', array('quizid' => $quizid), 'slot');

        $repaginate = new \mod_quiz_repaginate($quizid, $quizslots);
        $repaginate->repaginate($slotnumber, $repagtype);
        $updatedquizslots = $repaginate->get_slots();

        return $updatedquizslots;
    }

    public function get_last_slot() {
        $slots = $this->get_quiz_slots();
        $keys = array_keys($slots);
        $id = array_pop($keys);
        return $slots[$id];
    }

    public function set_quiz_slots(array $slots) {
        $this->slots = $slots;
    }

    public function set_quiz_sections(array $sections) {
        $this->sections = $sections;
    }

    public function save_quiz_slots_to_db(array $slots = array()) {
        global $DB;
        $table = 'quiz_slots';
        $quizid = null;

        if(!count($slots)){
            $slots = $this->slots;
        }

        $existing_slots = $DB->get_records($table);

        $slotreorder = array();
        foreach ($existing_slots as $existing_slot) {
            if (!$quizid) {
                $quizid = $existing_slot->quizid;
            }
            $slotreorder[$existing_slot->slot] = $slots[$existing_slot->id]->slot;
        }
        update_field_with_unique_index('quiz_slots',
                        'slot', $slotreorder, array('quizid' => $quizid));
        foreach ($slots as $slot) {
            if($DB->get_field($table, 'id', array('id' => $slot->id))){
                $DB->update_record($table, $slot);
            } else {
                $DB->insert_record($table, $slot);
            }
        }
    }
}
