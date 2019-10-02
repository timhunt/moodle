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
 * Helper class for actions that want to be action_column_base and action_can_go_in_menu.
 *
 * @package   core_question
 * @copyright 2019 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\bank;
defined('MOODLE_INTERNAL') || die();


/**
 * Helper class for actions that want to be action_column_base and action_can_go_in_menu.
 *
 * @copyright 2019 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class action_column_menuable extends action_column_base implements action_can_go_in_menu {

    /**
     * Work out the info required to display this action.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @return array [$url, $label, $icon].
     */
    abstract protected function determine_url_label_and_icon($question);

    protected function display_content($question, $rowclasses) {
        [$url, $icon, $label] = $this->determine_url_label_and_icon($question);
        if ($url) {
            $this->print_icon($icon, $label, $url);
        }
    }

    public function get_action_menu_link($question) {
        [$url, $icon, $label] = $this->determine_url_label_and_icon($question);
        if (!$url) {
            return null;
        }
        return new \action_menu_link_secondary($url, new \pix_icon($icon, ''), $label);
    }
}
