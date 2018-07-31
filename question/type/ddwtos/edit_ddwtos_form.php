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
 * Defines the editing form for the drag-and-drop words into sentences question type.
 *
 * @package   qtype_ddwtos
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/gapselect/edit_form_base.php');


/**
 * Drag-and-drop words into sentences editing form definition.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_edit_form extends qtype_gapselect_edit_form_base {
    /**
     * Maximum number of different groups of drag items there can be in a question.
     */
    const MAX_GROUPS = 8;

    public function qtype() {
        return 'ddwtos';
    }

    protected function data_preprocessing_choice($question, $answer, $key) {
        $question = parent::data_preprocessing_choice($question, $answer, $key);
        $options = unserialize($answer->feedback);
        $question->choices[$key]['choicegroup'] = $options->draggroup;
        $question->choices[$key]['infinite'] = $options->infinite;
        return $question;
    }

    /**
     * Creates an array with elements for a choice group.
     *
     * @param object $mform The Moodle form we are working with
     * @param int $maxgroup The number of max group generate element select.
     * @return array Array for form elements
     */
    protected function choice_group($mform, $maxgroup = self::MAX_GROUPS) {
        $grouparray = parent::choice_group($mform, $maxgroup);
        $grouparray[] = $mform->createElement('checkbox', 'infinite', ' ',
                get_string('infinite', 'qtype_ddwtos'), null,
                array('size' => 1, 'class' => 'tweakcss'));
        return $grouparray;
    }
}
