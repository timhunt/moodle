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
 * qbank_bulkmove external functions.
 *
 * @package    qbank_bulkmove
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_bulkmove\external;

use core\context;
use core\notification;
use core_external\external_function_parameters;
use core_external\external_value;
use moodle_url;

class move_questions extends \core_external\external_api {

    public static function execute_parameters() {
        return new external_function_parameters(
                [
                        'contextid' => new external_value(PARAM_INT, 'Contextid of the target question bank'),
                        'categoryid' => new external_value(PARAM_INT, 'Id of the target question category'),
                        'movequestionsselected' => new external_value(PARAM_SEQUENCE, 'Comma separated list of question ids to move'),
                        'returnurl' => new external_value(PARAM_URL, 'The return URL to be modified'),
                ]
        );
    }

    public static function execute_returns() {
        return new external_value(PARAM_URL, 'Modified return URL');
    }

    public static function execute($contextid, $categoryid, $movequestions, $returnurl) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
                'contextid' => $contextid,
                'categoryid' => $categoryid,
                'movequestionsselected' => $movequestions,
                'returnurl' => $returnurl,
        ]);

        $targetcontextid = $params['contextid'];
        $targetcontext = context::instance_by_id($targetcontextid);
        self::validate_context($targetcontext);

        $targetcategoryid = $params['categoryid'];
        $movequestionselected = $params['movequestionsselected'];
        $returnurlstring = $params['returnurl'];
        require_sesskey();

        \core_question\local\bank\helper::require_plugin_enabled('qbank_bulkmove');

        $contexts = new \core_question\local\bank\question_edit_contexts($targetcontext);
        $contexts->require_cap('moodle/question:add');
        $returnurl = new moodle_url($returnurlstring);

        if (!$targetcategory = $DB->get_record('question_categories', ['id' => $targetcategoryid, 'contextid' => $targetcontextid])) {
            throw new \moodle_exception('cannotfindcate', 'question');
        }

        \qbank_bulkmove\helper::bulk_move_questions($movequestionselected, $targetcategory);

        $returnfilters = \core_question\local\bank\filter_condition_manager::update_filter_param_to_category(
                $returnurl->param('filter'),
                $targetcategoryid,
        );

        $returnurl->param('filter', $returnfilters);

        notification::success(get_string('questionsmoved', 'qbank_bulkmove'));
        return $returnurl->out();
    }
}
