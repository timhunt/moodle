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
 * Provide interface for topics AJAX course formats
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package course
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/editlib.php');

// Initialise ALL the incoming parameters here, up front.
$courseid   = required_param('courseId', PARAM_INT);
$quizid     = required_param('quizId', PARAM_INT);
$class      = required_param('class', PARAM_ALPHA);
$field      = optional_param('field', '', PARAM_ALPHA);
$instanceid = optional_param('instanceId', 0, PARAM_INT);
$sectionid  = optional_param('sectionId', 0, PARAM_INT);
$beforeid   = optional_param('beforeId', 0, PARAM_INT);
$value      = optional_param('value', 0, PARAM_INT);
$column     = optional_param('column', 0, PARAM_ALPHA);
$id         = optional_param('id', 0, PARAM_INT);
$summary    = optional_param('summary', '', PARAM_RAW);
$sequence   = optional_param('sequence', '', PARAM_SEQUENCE);
$visible    = optional_param('visible', 0, PARAM_INT);
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command
$title      = optional_param('title', '', PARAM_FLOAT);

global $Out;
$PAGE->set_url('/mod/quiz/rest.php', array('courseId'=>$courseid,'quizId'=>$quizid,'class'=>$class));

//NOTE: when making any changes here please make sure it is using the same access control as mod/quiz/edit.php !!

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);

// Check user is logged in and set contexts if we are dealing with resource
if (in_array($class, array('resource'))) {
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);
} else {
    require_login($course);
}
$coursecontext = context_course::instance($course->id);
require_sesskey();

echo $OUTPUT->header(); // send headers

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers, so we simulate it with the action URL param
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

switch($requestmethod) {
    case 'POST':
//     case 'GET': // While debugging

        switch ($class) {
            case 'section':
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/quiz:manage', $PAGE->cm->context);
                        if (!$slot = $DB->get_record('quiz_slots', array('quizid'=>$quiz->id, 'id'=>$id))) {
                            throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                        }
                        \mod_quiz\structure::move_slot($quiz, $id, $beforeid);
                        $isvisible = true;

                        // Just something to tell the browser everything is ok.
                        echo json_encode(array('visible' => (bool) $isvisible));
                        break;
                    case 'getmaxmark':
                        require_capability('mod/quiz:manage', $PAGE->cm->context);
                        $slot = $DB->get_record('quiz_slots', array('id' => $id), '*', MUST_EXIST);

                        // Don't pass edit strings through multilang filters - we need the entire string
                        echo json_encode(array('instancemaxmark' => (0+$slot->maxmark)));
                        break;
                    case 'updatemaxmark':
                        require_capability('mod/quiz:manage', $PAGE->cm->context);
                        $slot = $DB->get_record('quiz_slots', array('id' => $id), '*', MUST_EXIST);

                        // Escape strings as they would be by mform
                        $slot->maxmark = clean_param($title, PARAM_FLOAT);

//                         if (!empty($slot->maxmark)) {
                            $DB->update_record('quiz_slots', $slot);
//                         }

                        echo json_encode(array('instancemaxmark' => $slot->maxmark));
                        break;
                }
                break;

            case 'course':
                break;
        }
        break;

    case 'DELETE':
        switch ($class) {
            case 'resource':
                require_capability('mod/quiz:manage', $PAGE->cm->context);
                quiz_remove_question_from_quiz($quiz, $id);
                break;
        }
        break;
}
