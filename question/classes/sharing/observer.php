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
 * Observer class for core_question.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\sharing;

use core\event\question_category_viewed;

class observer {

    public static function handle_question_category_viewed(question_category_viewed $event) {
        $context = $event->get_context();

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        [, $cm] = get_module_from_cmid($context->instanceid);

        if (!plugin_supports('mod', $cm->modname, FEATURE_PUBLISHES_QUESTIONS)) {
            return;
        }

        $userprefs = get_user_preferences(helper::RECENTLY_VIEWED);
        $recentlyviewed = !empty($userprefs) ? explode(',', $userprefs) : [];
        $recentlyviewed = array_combine($recentlyviewed, $recentlyviewed);
        $tostore = [];
        $tostore[] = $context->id;
        if (!empty($recentlyviewed[$context->id])) {
            unset($recentlyviewed[$context->id]);
        }
        $tostore = array_merge($tostore, array_values($recentlyviewed));
        $tostore = array_slice($tostore, 0, 5);
        set_user_preference(helper::RECENTLY_VIEWED, implode(',', $tostore));
    }

    public static function handle_question_bank_deletion() {
        
    }
}
