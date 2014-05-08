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
 * Page to edit quizzes
 *
 * This page generally has two columns:
 * The right column lists all available questions in a chosen category and
 * allows them to be edited or more to be added. This column is only there if
 * the quiz does not already have student attempts
 * The left column lists all questions that have been added to the current quiz.
 * The lecturer can add questions from the right hand list to the quiz or remove them
 *
 * The script also processes a number of actions:
 * Actions affecting a quiz:
 * up and down  Changes the order of questions and page breaks
 * addquestion  Adds a single question to the quiz
 * add          Adds several selected questions to the quiz
 * addrandom    Adds a certain number of random questions to the quiz
 * repaginate   Re-paginates the quiz
 * delete       Removes a question from the quiz
 * savechanges  Saves the order and grades for questions in the quiz
 *
 * @package    mod_quiz
 * @copyright  1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../config.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/addrandomform.php');
require_once($CFG->dirroot . '/question/category_class.php');

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

// These params are only passed from page request to request while we stay on
// this page otherwise they would go in question_edit_setup.
$scrollpos = optional_param('scrollpos', '', PARAM_INT);

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
        question_edit_setup('editq', '/mod/quiz/edit.php', true);
$structure = \mod_quiz\structure::create_for($quiz);

$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$canaddrandom = $contexts->have_cap('moodle/question:useall');
$canaddquestion = (bool) $contexts->having_add_and_use();

$quizhasattempts = quiz_has_attempts($quiz->id);

$PAGE->set_url($thispageurl);

// Get the course object and related bits.
$course = $DB->get_record('course', array('id' => $quiz->course));
if (!$course) {
    print_error('invalidcourseid', 'error');
}

// TODO: Following code related to questionbank needs to be removed from this file
// since it has been moved to /question/questionbank.php and js related functionality
// will be done in /question/yui/src/questionbank/js/questionbank.js.
$questionbank = new quiz_question_bank_view($contexts, $thispageurl, $course, $cm, $quiz);
$questionbank->set_quiz_has_attempts($quizhasattempts);

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

// Log this visit.
$params = array(
    'courseid' => $course->id,
    'context' => $contexts->lowest(),
    'other' => array(
        'quizid' => $quiz->id
    )
);
$event = \mod_quiz\event\edit_page_viewed::create($params);
$event->trigger();

// Process commands ============================================================.

// Get the list of question ids had their check-boxes ticked.
$selectedslots = array();
$params = (array) data_submitted();
foreach ($params as $key => $value) {
    if (preg_match('!^s([0-9]+)$!', $key, $matches)) {
        $selectedslots[] = $matches[1];
    }
}

$afteractionurl = new moodle_url($thispageurl);
if ($scrollpos) {
    $afteractionurl->param('scrollpos', $scrollpos);
}

if (optional_param('repaginate', false, PARAM_BOOL) && confirm_sesskey()) {
    // Re-paginate the quiz.
    $questionsperpage = optional_param('questionsperpage', $quiz->questionsperpage, PARAM_INT);
    quiz_repaginate_questions($quiz->id, $questionsperpage );
    quiz_delete_previews($quiz);
    redirect($afteractionurl);
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

if (optional_param('savechanges', false, PARAM_BOOL) && confirm_sesskey()) {
    $deletepreviews = false;
    $recomputesummarks = false;

    $rawdata = (array) data_submitted();
    $moveonpagequestions = array();
    $moveselectedonpage = optional_param('moveselectedonpagetop', 0, PARAM_INT);
    if (!$moveselectedonpage) {
        $moveselectedonpage = optional_param('moveselectedonpagebottom', 0, PARAM_INT);
    }

    $newslotorder = array();
    foreach ($rawdata as $key => $value) {
        if (preg_match('!^g([0-9]+)$!', $key, $matches)) {
            // Parse input for question -> grades.
            $slotnumber = $matches[1];
            $newgrade = unformat_float($value);
            quiz_update_slot_maxmark($DB->get_record('quiz_slots',
                    array('quizid' => $quiz->id, 'slot' => $slotnumber), '*', MUST_EXIST), $newgrade);
            $deletepreviews = true;
            $recomputesummarks = true;

        } else if (preg_match('!^o(pg)?([0-9]+)$!', $key, $matches)) {
            // Parse input for ordering info.
            $slotnumber = $matches[2];
            // Make sure two questions don't overwrite each other. If we get a second
            // question with the same position, shift the second one along to the next gap.
            $value = clean_param($value, PARAM_INT);
            while (array_key_exists($value, $newslotorder)) {
                $value++;
            }
            if ($matches[1]) {
                // This is a page-break entry.
                $newslotorder[$value] = 0;
            } else {
                $newslotorder[$value] = $slotnumber;
            }
            $deletepreviews = true;
        }
    }

    if ($moveselectedonpage) {

        // Make up a $newslotorder, then let the next if statement do the work.
        $oldslots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), 'slot');

        $beforepage = array();
        $onpage = array();
        $afterpage = array();
        foreach ($oldslots as $oldslot) {
            if (in_array($oldslot->slot, $selectedslots)) {
                $onpage[] = $oldslot;
            } else if ($oldslot->page <= $moveselectedonpage) {
                $beforepage[] = $oldslot;
            } else {
                $afterpage[] = $oldslot;
            }
        }

        $newslotorder = array();
        $currentpage = 1;
        $index = 10;
        foreach ($beforepage as $slot) {
            while ($currentpage < $slot->page) {
                $newslotorder[$index] = 0;
                $index += 10;
                $currentpage += 1;
            }
            $newslotorder[$index] = $slot->slot;
            $index += 10;
        }

        while ($currentpage < $moveselectedonpage) {
            $newslotorder[$index] = 0;
            $index += 10;
            $currentpage += 1;
        }
        foreach ($onpage as $slot) {
            $newslotorder[$index] = $slot->slot;
            $index += 10;
        }

        foreach ($afterpage as $slot) {
            while ($currentpage < $slot->page) {
                $newslotorder[$index] = 0;
                $index += 10;
                $currentpage += 1;
            }
            $newslotorder[$index] = $slot->slot;
            $index += 10;
        }
    }

    // If ordering info was given, reorder the questions.
    if ($newslotorder) {
        ksort($newslotorder);
        $currentpage = 1;
        $currentslot = 1;
        $slotreorder = array();
        $slotpages = array();
        foreach ($newslotorder as $slotnumber) {
            if ($slotnumber == 0) {
                $currentpage += 1;
                continue;
            }
            $slotreorder[$slotnumber] = $currentslot;
            $slotpages[$currentslot] = $currentpage;
            $currentslot += 1;
        }
        $trans = $DB->start_delegated_transaction();
        update_field_with_unique_index('quiz_slots',
                'slot', $slotreorder, array('quizid' => $quiz->id));
        foreach ($slotpages as $slotnumber => $page) {
            $DB->set_field('quiz_slots', 'page', $page, array('quizid' => $quiz->id, 'slot' => $slotnumber));
        }
        $trans->allow_commit();
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

// End of process commands =====================================================.

$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-quiz-edit');

$output = $PAGE->get_renderer('mod_quiz', 'edit');

$PAGE->requires->skip_link_to('questionbank',
        get_string('skipto', 'access', get_string('questionbank', 'question')));
$PAGE->requires->skip_link_to('quizcontentsblock',
        get_string('skipto', 'access', get_string('questionsinthisquiz', 'quiz')));
$PAGE->set_title(get_string('editingquizx', 'quiz', format_string($quiz->name)));
$PAGE->set_heading($course->fullname);
$node = $PAGE->settingsnav->find('mod_quiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}
echo $OUTPUT->header();

// Initialise the JavaScript.
$quizeditconfig = new stdClass();
$quizeditconfig->url = $thispageurl->out(true, array('qbanktool' => '0'));
$quizeditconfig->dialoglisteners = array();
$numberoflisteners = $DB->get_field_sql("
    SELECT COALESCE(MAX(page), 1)
      FROM {quiz_slots}
     WHERE quizid = ?", array($quiz->id));

for ($pageiter = 1; $pageiter <= $numberoflisteners; $pageiter++) {
    $quizeditconfig->dialoglisteners[] = 'addrandomdialoglaunch_' . $pageiter;
}

$PAGE->requires->data_for_js('quiz_edit_config', $quizeditconfig);
$PAGE->requires->js('/question/qengine.js');
$module = array(
    'name'      => 'mod_quiz_edit',
    'fullpath'  => '/mod/quiz/edit.js',
    'requires'  => array('yui2-dom', 'yui2-event', 'yui2-container'),
    'strings'   => array(),
    'async'     => false,
);

global $USER;
$USER->editing = 1;
// Prevent caching of this page to stop confusion when changing page after making AJAX changes.
    $PAGE->set_cacheable(false);

$ajaxenabled = true; // TODO MDL-40987.

$completion = new completion_info($course);
if ($completion->is_enabled() && $ajaxenabled) {
    $PAGE->requires->string_for_js('completion-title-manual-y', 'completion');
    $PAGE->requires->string_for_js('completion-title-manual-n', 'completion');
    $PAGE->requires->string_for_js('completion-alt-manual-y', 'completion');
    $PAGE->requires->string_for_js('completion-alt-manual-n', 'completion');

    $PAGE->requires->js_init_call('M.core_completion.init');
}

if ($completion->is_enabled() && $ajaxenabled) {
    // This value tracks whether there has been a dynamic change to the page.
    // It is used so that if a user does this - (a) set some tickmarks, (b)
    // go to another page, (c) clicks Back button - the page will
    // automatically reload. Otherwise it would start with the wrong tick
    // values.
    echo html_writer::start_tag('form', array('action' => '.', 'method' => 'get'));
    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'id' => 'completion_dynamic_change',
            'name' => 'completion_dynamic_change', 'value' => '0'));
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

// Course wrapper start.
echo html_writer::start_tag('div', array('class' => 'course-content'));

$output->edit_page($course, $quiz, $structure, $cm, $contexts, $thispageurl);

// Content wrapper end.
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
