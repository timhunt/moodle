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

namespace mod_quiz\output;

use mod_quiz\quiz_settings;
use mod_quiz\structure;
use renderable;
use renderer_base;
use templatable;

/**
 * Represents the list of links to other attempts
 *
 * @package   mod_quiz
 * @category  output
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_grading_page implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param structure $structure
     */
    public function __construct(

        /** @var structure the make-up the quiz we are manipulating. */
        public readonly structure $structure,

    ) {
    }

    public function export_for_template(renderer_base $output) {
        return [];
    }
}
