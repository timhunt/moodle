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
 * @package    core_question
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\output;

use cm_info;
use core_question\local\bank\question_bank_helper;
use renderer_base;

/**
 * Get the switch question bank rendered content. Displays lists of shared banks the viewing user has access to.
 */
class switch_question_bank implements \renderable, \templatable {

    /**
     * @param int $quizmodid
     * @param int $courseid
     * @param int $userid
     */
    public function __construct(
        private int $quizmodid,
        private int $courseid,
        private int $userid
    ) {
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        [, $cm] = get_module_from_cmid($this->quizmodid);
        $cminfo = cm_info::create($cm);

        $allopenbanks = iterator_to_array(
            question_bank_helper::get_instances(havingcap: ['moodle/question:managecategory'], notincourseids: [$this->courseid]),
            false
        );
        $courseopenbanks = iterator_to_array(
            question_bank_helper::get_instances(havingcap: ['moodle/question:managecategory'], incourseids: [$this->courseid]),
            false
        );
        $recentlyviewedbanks = question_bank_helper::get_recently_used_open_banks($this->userid);

        return [
            'quizname' => $cminfo->get_formatted_name(),
            'quizmodid' => $this->quizmodid,
            'hascourseopenbanks' => !empty($courseopenbanks),
            'courseopenbanks' => $courseopenbanks,
            'hasrecentlyviewedbanks' => !empty($recentlyviewedbanks),
            'recentlyviewedbanks' => $recentlyviewedbanks,
            'allopenbanks' => $allopenbanks,
        ];
    }
}
