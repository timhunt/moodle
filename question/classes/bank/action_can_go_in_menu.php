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
 * A base class for actions that are an icon that lets you manipulate the question in some way.
 *
 * @package   core_question
 * @copyright 2019 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\bank;
defined('MOODLE_INTERNAL') || die();


/**
 * An interface for question bank 'columns' which would be better in the edit menu.
 *
 * If a question bank column implements this interface, and if the {@link edit_menu_column}
 * is present in the question bank view, then the 'column' will be shown as an entry in the
 * edit menu instead of as a separate column.
 *
 * Probably most columns that want to implement this will be subclasses of
 * {@link action_column_base}, and most such columns should probably implement
 * this interface.
 *
 * @copyright 2019 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface action_can_go_in_menu {

    /**
     * Return the appropriate action menu link, or null if it does not apply to this question.
     *
     * @param \stdClass $question data about the question being displayed in this row.
     * @return \action_menu_link|null the action, if applicable here.
     */
    public function get_action_menu_link($question);
}
