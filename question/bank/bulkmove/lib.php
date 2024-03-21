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
 * qbank_bulkmove lib functions.
 *
 * @package    qbank_bulkmove
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_question\sharing\helper;

function qbank_bulkmove_output_fragment_bulk_move(array $args) {
    global $PAGE, $DB, $OUTPUT;

    [, $cmrec] = get_module_from_cmid($args['context']->instanceid);
    $currentbank = cm_info::create($cmrec);

    // Get all shared banks and categories and make the current bank/category pre-selected.
    [$allopeninstances, $categories] = helper::get_all_open_instances([], ['moodle/question:add'], true);
    $allopeninstances = array_merge(...array_values($allopeninstances));

    // The current bank is not a shared bank, so grab the category records so that we can at least allow them
    // to be moved to another local category.
    if (!plugin_supports('mod', $currentbank->modname, FEATURE_PUBLISHES_QUESTIONS, false)) {
        $currentbankcats = $DB->get_records_sql(
                'SELECT * FROM {question_categories} WHERE parent <> 0 AND contextid = :contextid',
                ['contextid' => $currentbank->context->id]
        );
        $categories = array_merge($currentbankcats, $categories);
        array_unshift($allopeninstances, $currentbank);
    }

    $allopenbanks = \qbank_bulkmove\helper::format_for_display($allopeninstances, $args['context']->id, $currentbank->context->id);
    $categories = \qbank_bulkmove\helper::format_for_display($categories, $args['categoryid'], $currentbank->context->id);

    $savebutton = new single_button(
            new moodle_url('#'),
            get_string('movequestions', 'qbank_bulkmove'),
            'post',
            single_button::BUTTON_PRIMARY,
            [
                    'data-action' => 'bulkmovesave',
                    'disabled' => 'disabled'
            ]
    );

    return $PAGE->get_renderer('qbank_bulkmove')->render_bulk_move_form(
            [
                    'allopenbanks' => $allopenbanks,
                    'allcategories' => $categories,
                    'save' => $savebutton->export_for_template($OUTPUT),
            ]
    );
}
