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
 * The repaginate class will rearrange questions in pages.
 * The quiz setting allows users to write quizzes with one auestion pe rpage
 * n questions per page, or all questions on one page.
 * @package   mod_quiz
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class quiz_repaginate {

    const LINK = 1; // This is used to join pages.
    const UNLINK = 2; // This is used to separate pages.

    private $quizid;
    private $slots;

    /**
     * Set current slots object
     * @param int $quizid
     * @param object $slots
     */
    public function __construct($quizid = 0, $slots = null) {
        $this->quizid = $quizid;
        if (!$this->quizid) {
            $this->slots = array();
        }
        if (!$slots) {
            $this->slots = $slots;
        }
    }

    /**
     * Rearanging the key for easy use and return the slots with the key
     * as page-number, slot-number, slotid
     * @param object $slots
     */
    protected function get_pages_slotnumber_slotids($slots) {
        if (!$slots) {
            return;
        }
        $pages = array();
        foreach ($slots as $slotid => $slot) {
            $pages[$slot->page . ',' . $slot->slot . ',' . $slot->id] = $slot;
        }
        return $pages;
    }

    /**
     * Repaginate a given slot with the given pagenumber
     * @param object $slot
     * @param int $newpagenumber
     */
    protected function repaginate_this_slot($slot, $newpagenumber) {
        $newslot = clone($slot);
        $newslot->page = $newpagenumber;
        return $newslot;
    }

    /**
     * Return number of pages
     * @param object $slots
     */
    protected function get_number_of_pages($slots) {
        $numberofpages = 0;
        $rememberpagenumber = 0;
        foreach ($slots as $key => $slot) {
            if ($rememberpagenumber == $slot->page) {
                continue;
            }
            $rememberpagenumber = $slot->page;
            $numberofpages++;
        }
        return $numberofpages;
    }

    /**
     * Return current slot object
     * @param object$slots
     * @param int $slotnumber
     */
    protected function get_this_slot($slots, $slotnumber) {
        foreach ($slots as $key => $slot) {
            if ($slot->slot == $slotnumber) {
                return $slot;
            }
        }
        return null;
    }

    /**
     * Return the last slot object
     * @param object$slots
     */
    protected function get_last_slot($slots) {
        return end($slots);
    }

    /**
     * Return array of slots with slot number as key
     * @param object$slots
     */
    protected function get_slots_by_slot_number($slots) {
        if (!$slots) {
            return array();
        }
        $newslots = array();
        foreach ($slots as $slot) {
            $newslots[$slot->slot] = $slot;
        }
        return $newslots;
    }

    /**
     * Return array of slots with slot id as key
     * @param object$slots
     */
    protected function get_slots_by_slotid($slots) {
        if (!$slots) {
            return array();
        }
        $newslots = array();
        foreach ($slots as $slot) {
            $newslots[$slot->id] = $slot;
        }
        return $newslots;
    }
    /**
     * Repaginate, update DB and slots object
     * @param int $nextslotnumber
     * @param int $type
     */
    public function repaginate($nextslotnumber, $type) {
        global $DB;
        $this->slots = $DB->get_records('quiz_slots', array('quizid' => $this->quizid), 'slot');
        $nextslot = null;
        $newslots = array();
        foreach ($this->slots as $slot) {
            if ($slot->slot < $nextslotnumber) {
                 $newslots[$slot->id] = $slot;
            } else if ($slot->slot == $nextslotnumber) {
                $nextslot = $this->repaginate_next_slot($nextslotnumber, $type);

                // Update DB.
                $this->update_this_slot($nextslot);

                // Update returning object.
                 $newslots[$slot->id] = $nextslot;
            }
        }
        if ($nextslot) {
            $newslots = array_merge($newslots, $this->repaginate_the_rest($this->slots, $nextslotnumber, $type));
            $this->slots = $this->get_slots_by_slotid($newslots);
        }
    }

    /**
     * Repaginate next slot and return the modified slot object
     * @param int $nextslotnumber
     * @param int $type
     */
    public function repaginate_next_slot($nextslotnumber, $type) {
        global $DB;
        global $Out;
        $currentslotnumber = $nextslotnumber - 1;
        if (!($currentslotnumber && $nextslotnumber)) {
            return null;
        }
        $currentslot = $DB->get_record('quiz_slots', array('quizid' => $this->quizid, 'slot' => $currentslotnumber));
        $nextslot = $DB->get_record('quiz_slots', array('quizid' => $this->quizid, 'slot' => $nextslotnumber));
//         $Out->append('type '. $type);
        if ($type === self::LINK) {
//             $Out->append('type === self::LINK');
            return $this->repaginate_this_slot($nextslot, $currentslot->page);
        } else if ($type === self::UNLINK) {
//             $Out->append('type === self::UNLINK');
            return $this->repaginate_this_slot($nextslot, $nextslot->page + 1);
        }
//         $Out->append('type null');
        return null;
    }

    /**
     * Update quiz_slots table for this slot
     * @param object $slot
     */
    public function update_this_slot($slot) {
        global $DB;
        // Update quiz_slots table.
        $transaction = $DB->start_delegated_transaction(); // TODO: Do i really need this?
        $DB->update_record('quiz_slots', $slot, true);
        $transaction->allow_commit();
    }

    /**
     * Update quiz_slots table
     *
     */
    public function update_quiz_slots_table($slots) {
        global $DB;
        // Update quiz_slots table.
        $transaction = $DB->start_delegated_transaction();
        foreach ($slots as $slot) {
            $DB->update_record('quiz_slots', $slot, true);
        }
        $transaction->allow_commit();
    }

    /**
     * Return the slots with the new pagination, regardless of current pagination.
     * @param object $slots
     * @param int $number, number of question per page
     */
    public function repaginate_n_question_per_page($slots, $number) {
        $slots = $this->get_slots_by_slot_number($slots);
        $newslots = array();
        $count = 0;
        $page = 1;
        foreach ($slots as $key => $slot) {
            for ($page + $count; $page < ($number + $count + 1); $page++) {
                if ($slot->slot >= $page) {
                    $slot->page = $page;
                    $count++;
                }
            }
            $newslots[$slot->id] = $slot;
        }
        return $newslots;
    }

    /**
     * Repaginate the rest
     * @param object $quizslots
     * @param int $slotfrom
     * @param int $type
     */
    public function repaginate_the_rest($quizslots, $slotfrom, $type) {
        global $DB;
        if (!$quizslots) {
            return null;
        }
        $newslots = array();
        foreach ($quizslots as $slot) {
            if ($type == self::LINK) {
                if ($slot->slot <= $slotfrom) {
                    continue;
                }
                $slot->page = $slot->page - 1;
            } else if ($type == self::UNLINK) {
                if ($slot->slot <= $slotfrom - 1) {
                    continue;
                }
                $slot->page = $slot->page + 1;
            }
            // Update DB.
            $DB->update_record('quiz_slots', $slot);
            $newslots[$slot->id] = $slot;
        }
        return $newslots;
    }

    public function get_slots() {
        return $this->slots;
    }

    /**
     *
     * @param object $quiz
     * @param object $thispageurl
     * @param string $repaginatingdisabledhtml
     */
    public function get_popup_menu($quiz, $thispageurl, $repaginatingdisabledhtml) {
        $perpage = array();
        $perpage[0] = get_string('allinone', 'quiz');
        for ($i = 1; $i <= 50; ++$i) {
            $perpage[$i] = $i;
        }
        $gostring = get_string('go');
        $output = '<div id="repaginatedialog"><div class="hd">';
        $output .= get_string('repaginatecommand', 'quiz');
        $output .= '</div><div class="bd">';
        $output .= '<form action="edit.php" method="post">';
        $output .= '<fieldset class="invisiblefieldset">';
        $output .= html_writer::input_hidden_params($thispageurl);
        $output .= '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
        // YUI does not submit the value of the submit button so we need to add the value.
        $output .= '<input type="hidden" name="repaginate" value="'.$gostring.'" />';
        $attributes = array();
        $attributes['disabled'] = $repaginatingdisabledhtml ? 'disabled' : null;
        $select = html_writer::select(
                $perpage, 'questionsperpage', $quiz->questionsperpage, null, $attributes);
        $output .= get_string('repaginate', 'quiz', $select);
        $output .= '<div class="quizquestionlistcontrols">';
        $output .= ' <input type="submit" name="repaginate" value="'. $gostring . '" ' .
                $repaginatingdisabledhtml.' />';
        $output .= '</div></fieldset></form></div></div>';
        return $output;
    }
}
