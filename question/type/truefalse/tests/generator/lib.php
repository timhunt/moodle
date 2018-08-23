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

defined('MOODLE_INTERNAL') || die();

/**
 * Test data generator class for truefalse question type.
 *
 * @package    qtype
 * @subpackage truefalse
 * @copyright 2018 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_truefalse_generator extends component_generator_base {
    public function get_simulated_post_data(question_attempt $qa, $response) {
        $tosubmit = [];

        $tosubmit[$qa->get_control_field_name('sequencecheck')] = $qa->get_sequence_check_count();
        $tosubmit[$qa->get_flag_field_name()] = (int)$qa->is_flagged();
        $tosubmit[$qa->get_control_field_name('answer')] = $response;

        return $tosubmit;
    }
}
