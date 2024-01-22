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
 * mod_qbank installer functions.
 *
 * @package    mod_qbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_qbank;

function create_course(object $coursecategory, string $shortname): object {
    $data = (object) [
            'enablecompletion' => 0,
            'fullname' => "Shared teaching resources for Category: {$coursecategory->name}",
            'shortname' => $shortname,
            'category' => $coursecategory->id,
    ];
    return \create_course($data);
}

function create_module(object $course, string $bankname): object {
    [$module, $context, $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'qbank', 0);
    unset($data->completion);
    $data->visibleoncoursepage = 0;
    $data->name = $bankname;
    return add_moduleinfo($data, $course);
}

function move_category(object $oldtopcategory, \context $newcontext): void {
    global $DB;

    $newtopcategory = question_get_top_category($newcontext->id, true);

    // This function moves subcategories, so we have to start at the top.
    question_move_category_to_context($oldtopcategory->id, $oldtopcategory->contextid, $newcontext->id);

    // Move the parent from the old top category to the new one.
    $DB->set_field('question_categories', 'parent', $newtopcategory->id, ['parent' => $oldtopcategory->id]);
}

function remove_stale_questions_from_subcategories($topcategory) {

    \qbank_managecategories\helper::question_remove_stale_questions_from_category($oldtopcategory->id);
}
