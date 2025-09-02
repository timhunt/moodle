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
 * Test page to show an editable view of a multiple choice question.
 *
 * @package    qtype_multichoice
 * @copyright  2025 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use qtype_multichoice\output\inline_edit_view;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

// Get and validate question id.
$id = required_param('id', PARAM_INT);
$PAGE->set_url('/question/type/multichoice/testedit.php', ['id' => $id]);

$questiondata = question_bank::load_question_data($id);
if ($questiondata->qtype !== 'multichoice') {
    throw new \core\exception\coding_exception(
        'Currenltly this code only works with multiple choice question types.',
    );
}

// Check permissions.
$context = context::instance_by_id($questiondata->contextid);
[$course, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'quiz');
require_login($course, false, $cm);
question_require_capability_on($questiondata, 'edit');

// Start output.
$editrenderer = $PAGE->get_renderer('qtype_multichoice', 'edit');
$title = 'Edit question test page';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $editrenderer->render(new inline_edit_view($questiondata));
echo $OUTPUT->footer();
