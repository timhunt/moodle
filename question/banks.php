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
 * moodlecore manage banks.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_question\local\bank\question_edit_contexts;
use core_question\sharing\helper;

require_once __DIR__ . '/../config.php';

global $CFG, $PAGE, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$createdefault = optional_param('createdefault', false, PARAM_BOOL);
$course = get_course($courseid);
$coursecontext = context_course::instance($course->id);

if ($course->id === get_site()->id) {
    throw new moodle_exception('invalidcourse');
}

require_login($course, false);
require_capability('moodle/course:manageactivities', \context_course::instance($course->id));

$allopenbanks = helper::get_course_open_instances($course->id);
$allclosedbanks = helper::get_course_closed_instances($course->id);
$openbanks = helper::filter_by_question_edit_access(array_keys(question_edit_contexts::$caps), $allopenbanks);
$closedbanks = helper::filter_by_question_edit_access(array_keys(question_edit_contexts::$caps), $allclosedbanks);

$pageurl = new moodle_url('/question/banks.php', ['courseid' => $course->id]);
$PAGE->set_url($pageurl);

if ($createdefault) {
    require_sesskey();
    helper::create_default_open_instance($course);
    \core\notification::add(get_string('defaultcreated', 'question'), \core\notification::SUCCESS);
    redirect($pageurl);
}

$output = $PAGE->get_renderer('core_question', 'bank');

$openbanksrenderable = new \core_question\sharing\output\question_bank_list($openbanks);
$closedbanksrenderable = new \core_question\sharing\output\question_bank_list($closedbanks);
$addbankrenderable = new \core_question\sharing\output\add_bank_list($course, helper::get_open_modules());
$createdefaultrenderable = new \single_button(
        new \moodle_url('/question/banks.php', ['createdefault' => true, 'courseid' => $course->id]),
        get_string('createdefault', 'question')
);

echo $output->header();
echo $output->heading(get_string('banksincourse', 'question'));
echo $output->render_from_template('core_question/view_banks',
        [
                'openbanks' => $openbanksrenderable->export_for_template($output),
                'closedbanks' => $closedbanksrenderable->export_for_template($output),
                'addbanks' => $addbankrenderable->export_for_template($output),
                'createdefault' => has_capability('moodle/course:manageactivities', context_course::instance($course->id)) ?
                        $createdefaultrenderable->export_for_template($output) : false,
        ]
);
echo $output->footer();
