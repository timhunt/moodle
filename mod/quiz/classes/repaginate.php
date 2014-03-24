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

    public function __construct($slots = null, $nextslotnumber = 0, $type = null) {
        if ($slots && $nextslotnumber && $type) {
            global $DB;
            $newslots = $this->repaginate($slots, $nextslotnumber, $type);
            $this->update_quiz_slots_table($newslots);
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
     * Return currect slot object
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
     * Repaginate the slots. Depending on the function call type
     * 'join', join two pages together and repaginate
     * 'separate' separate two pages and repaginate
     * @param int $nextslot, next slot number
     * @param string $type, whether 'join' or 'separate'
     * @param object $slots, all quiz slots (optional)
     */
    public function repaginate($slots, $nextslot, $type) {
        global $DB;
        if (!$slots) {
            return array();
        }

        $slots = $this->get_pages_slotnumber_slotids($slots);
        $numberofpages = $this->get_number_of_pages($slots);
        if ($numberofpages < 2) {
            return $slots;
        }

        $numberofslots = count($slots);
        if ($numberofslots < 2) {
            return $slots;
        }
        $nextslotobj = $this->get_this_slot($slots, $nextslot);
        $lastslot = $this->get_last_slot($slots);
        $newslots = array();
        $paging = 1;
        foreach ($slots as $key => $slot) {
            // We do use the complex key for processing.
            // we do not need this any more, The return object will only have the id as key.
            list($currentpagenumber, $currentslotnumber, $currentslotid) = explode(',', $key);
            if ($nextslot > $lastslot->slot) {
                // That should not happen.
                return;
            }
            if ((int)$currentslotnumber === (int)$nextslot - 1) {
                $newslots[$slot->id] = $this->repaginate_this_slot($slot, $paging);
                if ($type === 'join') {
                    $nextslotobj->page = $currentpagenumber;
                    $newslots[$nextslotobj->id] = $nextslotobj;
                    $paging++;
                } else {
                    $nextslotobj->page = $currentpagenumber + 1;
                    $newslots[$nextslotobj->id] = $nextslotobj;
                    $paging = $paging + 2;
                }
            } else {
                $ignorekey = $currentpagenumber . ',' . $nextslot . ',' . $nextslotobj->id;
                if ($key != $ignorekey) {
                    $newslots[$slot->id] = $this->repaginate_this_slot($slot, $paging);
                    $paging++;
                }
            }
        }
        return $newslots;
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
        $output =  '<div id="repaginatedialog"><div class="hd">';
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
