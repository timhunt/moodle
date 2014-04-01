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
$courseid   = required_param('courseid', PARAM_INT);
$quizid     = required_param('quizid', PARAM_INT);
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
$pageaction = optional_param('action', '', PARAM_ALPHA); // Used to simulate a DELETE command.
$maxmark    = optional_param('maxmark', '', PARAM_FLOAT);
$page       = optional_param('page', '', PARAM_INT);
$PAGE->set_url('/mod/quiz/rest.php',
        array('courseid' => $courseid, 'quizid' => $quizid, 'class' => $class));

require_sesskey();
$quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course);
require_login($courseid, false, $cm);
$structure = \mod_quiz\structure::create_for($quiz);
$modcontext = context_module::instance($cm->id);

echo $OUTPUT->header(); // Send headers.

// OK, now let's process the parameters and do stuff
// MDL-10221 the DELETE method is not allowed on some web servers,
// so we simulate it with the action URL param.
$requestmethod = $_SERVER['REQUEST_METHOD'];
if ($pageaction == 'DELETE') {
    $requestmethod = 'DELETE';
}

switch($requestmethod) {
    case 'POST':
     case 'GET': // For debugging.

        switch ($class) {
            case 'section':
                break;

            case 'resource':
                switch ($field) {
                    case 'move':
                        require_capability('mod/quiz:manage', $modcontext);
                        $structure->move_slot($quiz, $id, $beforeid, $page);
                        quiz_delete_previews($quiz);
                        echo json_encode(array('visible' => true));
                        break;

                    case 'getmaxmark':
                        require_capability('mod/quiz:manage', $modcontext);
                        $slot = $DB->get_record('quiz_slots', array('id' => $id), '*', MUST_EXIST);
                        echo json_encode(array('instancemaxmark' => 0 + $slot->maxmark));
                        break;

                    case 'updatemaxmark':
                        require_capability('mod/quiz:manage', $modcontext);
                        $slot = $structure->get_slot_by_id($id);
                        if ($structure->update_slot_maxmark($slot, $maxmark)) {
                            // Grade has really changed.
                            quiz_delete_previews($quiz);
                            quiz_update_sumgrades($quiz);
                            quiz_update_all_attempt_sumgrades($quiz);
                            quiz_update_all_final_grades($quiz);
                            quiz_update_grades($quiz, 0, true);
                        }
                        echo json_encode(array('instancemaxmark' => $maxmark));
                        break;
                    case 'linkslottopage':
                        require_capability('mod/quiz:manage', $modcontext);
                        $slots = $structure->link_slot_to_page($quiz, $id, $value);
                        $slots_json = array();
//                         $slots_json = $slots;
                        foreach ($slots as $slot) {
                            $slots_json[$slot->slot] = array('id'=>$slot->id, 'slot'=>$slot->slot,
                                                            'page'=>$slot->page);
                        }
                        echo json_encode(array('slots' => $slots_json));
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
                require_capability('mod/quiz:manage', $modcontext);
                if (!$slot = $DB->get_record('quiz_slots', array('quizid'=>$quiz->id, 'id'=>$id))) {
                    throw new moodle_exception('AJAX commands.php: Bad slot ID '.$id);
                }
                $structure->remove_slot($quiz, $slot->slot);
                quiz_delete_previews($quiz);
                quiz_update_sumgrades($quiz);
                break;
        }
        break;
}
