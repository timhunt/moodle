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

use context_course;
use core_question\local\bank\question_bank_helper;
use renderer_base;
use single_button;
use stdClass;

/**
 * Create the management view of shared and non-shared banks.
 */
class view_banks implements \templatable, \renderable {

    /**
     * @param iterable $openbanksgenerator @see question_bank_helper::get_instances()
     * @param iterable $closedbanksgenerator @see question_bank_helper::get_instances()
     * @param stdClass $course
     */
    public function __construct(
        private readonly iterable $openbanksgenerator,
        private readonly iterable $closedbanksgenerator,
        private readonly stdClass $course,
    ) {
    }

    public function export_for_template(renderer_base $output) {
        $openbanksrenderable = new question_bank_list($this->openbanksgenerator);
        $openbanks = $openbanksrenderable->export_for_template($output);
        $closedbanksrenderable = new question_bank_list($this->closedbanksgenerator);
        $closedbanks = $closedbanksrenderable->export_for_template($output);
        $addbankrenderable = new add_bank_list($this->course, question_bank_helper::get_open_modules());
        $createdefaultrenderable = new single_button(
            question_bank_helper::get_url_for_qbank_list($this->course->id, true),
            get_string('createdefault', 'question')
        );

        return [
            'hasopenbanks' => !empty($openbanks),
            'openbanks' => $openbanks,
            'hasclosedbanks' => !empty($closedbanks),
            'closedbanks' => $closedbanks,
            'addbanks' => $addbankrenderable->export_for_template($output),
            'createdefault' => has_capability('moodle/course:manageactivities', context_course::instance($this->course->id)) ?
                $createdefaultrenderable->export_for_template($output) : false,
        ];
    }
}
