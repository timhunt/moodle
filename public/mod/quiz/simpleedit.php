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

use mod_quiz\output\edit_nav_actions;
use mod_quiz\quiz_settings;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');


list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) =
    question_edit_setup('editq', '/mod/quiz/simpleedit.php');

$PAGE->set_url($thispageurl);
$PAGE->set_secondary_active_tab("mod_quiz_edit");

// You need mod/quiz:manage in addition to question capabilities to access this page.
require_capability('mod/quiz:manage', $contexts->lowest());

// Get the course object and related bits.
$course = get_course($quiz->course);
$quizobj = new quiz_settings($quiz, $cm, $course);
$structure = $quizobj->get_structure();
$gradecalculator = $quizobj->get_grade_calculator();

$defaultcategoryobj = question_get_default_category($contexts->lowest()->id, true);
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$quizhasattempts = quiz_has_attempts($quiz->id);


$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('mod-quiz-edit');

$output = $PAGE->get_renderer('mod_quiz', 'simple_edit');

$PAGE->set_title(get_string('editingquizx', 'quiz', format_string($quiz->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();
$node = $PAGE->settingsnav->find('mod_quiz_edit', navigation_node::TYPE_SETTING);
if ($node) {
    $node->make_active();
}

// Add random question - result message.
if ($message = optional_param('message', '', PARAM_TEXT)) {
    core\notification::add($message, core\notification::SUCCESS);
}

$tertiarynav = new edit_nav_actions($cmid, edit_nav_actions::SUMMARY);

// Do output.
echo $output->header();
echo $output->render($tertiarynav);

echo $output->footer();
