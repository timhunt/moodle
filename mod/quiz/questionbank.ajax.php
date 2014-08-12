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
 * Ajax script to update the contents of the question bank dialogue.
 *
 * @package    mod_quiz
 * @copyright  2014 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// DONOTCOMMIT uncomment this.
// define('AJAX_SCRIPT', true);


require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');


/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 * Displays button in form with checkboxes for each question.
 */
function module_specific_buttons($cmid, $cmoptions) {
    global $OUTPUT;
    $params = array(
        'type' => 'submit',
        'name' => 'add',
        'value' => $OUTPUT->larrow() . ' ' . get_string('addtoquiz', 'quiz'),
    );
    if ($cmoptions->hasattempts) {
        $params['disabled'] = 'disabled';
    }
    return html_writer::empty_tag('input', $params);
}

/**
 * Callback function called from question_list() function
 * (which is called from showbank())
 */
function module_specific_controls($totalnumber, $recurse, $category, $cmid, $cmoptions) {
    global $OUTPUT;
    $out = '';
    $catcontext = context::instance_by_id($category->contextid);
    if (has_capability('moodle/question:useall', $catcontext)) {
        if ($cmoptions->hasattempts) {
            $disabled = ' disabled="disabled"';
        } else {
            $disabled = '';
        }
        $randomusablequestions =
                question_bank::get_qtype('random')->get_available_questions_from_category(
                        $category->id, $recurse);
        $maxrand = count($randomusablequestions);
        if ($maxrand > 0) {
            for ($i = 1; $i <= min(10, $maxrand); $i++) {
                $randomcount[$i] = $i;
            }
            for ($i = 20; $i <= min(100, $maxrand); $i += 10) {
                $randomcount[$i] = $i;
            }
        } else {
            $randomcount[0] = 0;
            $disabled = ' disabled="disabled"';
        }

        $out = '<strong><label for="menurandomcount">'.get_string('addrandomfromcategory', 'quiz').
                '</label></strong><br />';
        $attributes = array();
        $attributes['disabled'] = $disabled ? 'disabled' : null;
        $select = html_writer::select($randomcount, 'randomcount', '1', null, $attributes);
        $out .= get_string('addrandom', 'quiz', $select);
        $out .= '<input type="hidden" name="recurse" value="'.$recurse.'" />';
        $out .= '<input type="hidden" name="categoryid" value="' . $category->id . '" />';
        $out .= ' <input type="submit" name="addrandom" value="'.
                get_string('addtoquiz', 'quiz').'"' . $disabled . ' />';
        $out .= $OUTPUT->help_icon('addarandomquestion', 'quiz');
    }
    return $out;
}

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
        question_edit_setup('editq', '/mod/quiz/edit.php', true);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$quizhasattempts = quiz_has_attempts($quiz->id);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);

$questionbank = new quiz_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

$output = $PAGE->get_renderer('mod_quiz', 'edit');

// Course wrapper start.
echo $output->get_questionbank_form($thispageurl, $contexts, $pagevars, $course, $cm, $quiz);
