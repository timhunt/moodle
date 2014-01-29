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
 * Library of functions for the quiz module.
 *
 * This contains functions that are called also from outside the quiz module
 * Functions that are only called by the quiz module itself are in {@link locallib.php}
 *
 * @package    mod_quiz
 * @copyright  2014 The open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


//defined('MOODLE_INTERNAL') || die();

require_once('../config.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$scrollpos = optional_param('scrollpos', 0, PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
    question_edit_setup('editq', '/mod/quiz/edit.php', true);

$quizhasattempts = quiz_has_attempts($quiz->id);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

// Set navigation bar and display header.
$PAGE->set_url($thispageurl);
$returnurl = new moodle_url('/mod/quiz/edit.php', array('cmid' => $quiz->cmid));
$questionbank = get_string('chooseqtypetoadd', 'question');
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('editinga', 'moodle', get_string('modulename', $cm->modname)),$returnurl);
$PAGE->navbar->add($questionbank);
$PAGE->set_title($questionbank);
echo $OUTPUT->header();

// Create quiz question bank view.
$questionbank = new quiz_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

// Log this visit.
add_to_log($cm->course, 'quiz', 'editquestions',
            "view.php?id=$cm->id", "$quiz->id", $cm->id);

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

// Process commands ============================================================

// Get the list of question ids had their check-boxes ticked.
$selectedquestionids = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedquestionids[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (($addquestion = optional_param('addquestion', 0, PARAM_INT)) && confirm_sesskey()) {
    // Add a single question to the current quiz.
    quiz_require_question_use($addquestion);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    quiz_add_quiz_question($addquestion, $quiz, $addonpage);
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    $thispageurl->param('lastchanged', $addquestion);
    redirect($afteractionurl);
}

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    // Add selected questions to the current quiz.
    $rawdata = (array) data_submitted();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $key = $matches[1];
            quiz_require_question_use($key);
            quiz_add_quiz_question($key, $quiz);
        }
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if ((optional_param('addrandom', false, PARAM_BOOL)) && confirm_sesskey()) {
    // Add random questions to the quiz.
    $recurse = optional_param('recurse', 0, PARAM_BOOL);
    $addonpage = optional_param('addonpage', 0, PARAM_INT);
    $categoryid = required_param('categoryid', PARAM_INT);
    $randomcount = required_param('randomcount', PARAM_INT);
    quiz_add_random_questions($quiz, $addonpage, $categoryid, $randomcount, $recurse);

    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if (optional_param('addnewpagesafterselected', null, PARAM_CLEAN) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        $quiz->questions = quiz_add_page_break_after($quiz->questions, $questionid);
    }
    $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

$addpage = optional_param('addpage', false, PARAM_INT);
if ($addpage !== false && confirm_sesskey()) {
    $quiz->questions = quiz_add_page_break_at($quiz->questions, $addpage);
    $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

$deleteemptypage = optional_param('deleteemptypage', false, PARAM_INT);
if (($deleteemptypage !== false) && confirm_sesskey()) {
    $quiz->questions = quiz_delete_empty_page($quiz->questions, $deleteemptypage);
    $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
}

$remove = optional_param('remove', false, PARAM_INT);
if ($remove && confirm_sesskey()) {
    // Remove a question from the quiz.
    // We require the user to have the 'use' capability on the question,
    // so that then can add it back if they remove the wrong one by mistake,
    // but, if the question is missing, it can always be removed.
    if ($DB->record_exists('question', array('id' => $remove))) {
        quiz_require_question_use($remove);
    }
    quiz_remove_question($quiz, $remove);
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    quiz_remove_question_from_quiz($quiz, $remove);
    redirect($afteractionurl);
}

if (optional_param('quizdeleteselected', false, PARAM_BOOL) &&
        !empty($selectedquestionids) && confirm_sesskey()) {
    foreach ($selectedquestionids as $questionid) {
        if (quiz_has_question_use($questionid)) {
            quiz_remove_question($quiz, $questionid);
        }
    }
    quiz_delete_previews($quiz);
    quiz_update_sumgrades($quiz);
    redirect($afteractionurl);
}

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $deletepreviews = false;
    $recomputesummarks = false;

    $oldquestions = explode(',', $quiz->questions); // The questions in the old order.
    $questions = array(); // For questions in the new order.
    $rawdata = (array) data_submitted();
    $moveonpagequestions = array();
    $moveselectedonpage = optional_param('moveselectedonpagetop', 0, PARAM_INT);
    if (!$moveselectedonpage) {
        $moveselectedonpage = optional_param('moveselectedonpagebottom', 0, PARAM_INT);
    }

    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            // Parse input for question -> grades.
            $questionid = $matches[1];
            $quiz->grades[$questionid] = unformat_float($value);
            quiz_update_question_instance($quiz->grades[$questionid], $questionid, $quiz);
            $deletepreviews = true;
            $recomputesummarks = true;

        } else if (preg_match('!^o(pg)?([0-9]+)$!', $key, $matches)) {
            // Parse input for ordering info.
            $questionid = $matches[2];
            // Make sure two questions don't overwrite each other. If we get a second
            // question with the same position, shift the second one along to the next gap.
            $value = clean_param($value, PARAM_INT);
            while (array_key_exists($value, $questions)) {
                $value++;
            }
            if ($matches[1]) {
                // This is a page-break entry.
                $questions[$value] = 0;
            } else {
                $questions[$value] = $questionid;
            }
            $deletepreviews = true;
        }
    }

    // If ordering info was given, reorder the questions.
    if ($questions) {
        ksort($questions);
        $questions[] = 0;
        $quiz->questions = implode(',', $questions);
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // Get a list of questions to move, later to be added in the appropriate
    // place in the string.
    if ($moveselectedonpage) {
        $questions = explode(',', $quiz->questions);
        $newquestions = array();
        // Remove the questions from their original positions first.
        foreach ($questions as $questionid) {
            if (!in_array($questionid, $selectedquestionids)) {
                $newquestions[] = $questionid;
            }
        }
        $questions = $newquestions;

        // Move to the end of the selected page.
        $pagebreakpositions = array_keys($questions, 0);
        $numpages = count($pagebreakpositions);

        // Ensure the target page number is in range.
        for ($i = $moveselectedonpage; $i > $numpages; $i--) {
            $questions[] = 0;
            $pagebreakpositions[] = count($questions) - 1;
        }
        $moveselectedpos = $pagebreakpositions[$moveselectedonpage - 1];

        // Do the move.
        array_splice($questions, $moveselectedpos, 0, $selectedquestionids);
        $quiz->questions = implode(',', $questions);

        // Update the database.
        $DB->set_field('quiz', 'questions', $quiz->questions, array('id' => $quiz->id));
        $deletepreviews = true;
    }

    // If rescaling is required save the new maximum.
    $maxgrade = unformat_float(optional_param('maxgrade', -1, PARAM_RAW));
    if ($maxgrade >= 0) {
        quiz_set_grade($maxgrade, $quiz);
    }

    if ($deletepreviews) {
        quiz_delete_previews($quiz);
    }
    if ($recomputesummarks) {
        quiz_update_sumgrades($quiz);
        quiz_update_all_attempt_sumgrades($quiz);
        quiz_update_all_final_grades($quiz);
        quiz_update_grades($quiz, 0, true);
    }
    redirect($afteractionurl);
}

$questionbank->process_actions($thispageurl, $cm);


$bankclass = '';
echo '<div class="questionbankwindow ' . $bankclass . 'block">';
echo '<div class="header"><div class="title"><h2>';
echo get_string('questionbankcontents', 'quiz');
echo '</h2></div></div><div class="content">';

if (function_exists('module_specific_buttons')) {
    echo module_specific_buttons($this->cm->id,$cmoptions);
}

echo '<span id="questionbank"></span>';
echo '<div class="container">';
echo '<div id="module" class="module">';
echo '<div class="bd">';
$questionbank->display('editq',
        $pagevars['qpage'],
        $pagevars['qperpage'],
        $pagevars['cat'], $pagevars['recurse'], $pagevars['showhidden'],
        $pagevars['qbshowtext']);
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div></div>';

