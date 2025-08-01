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

namespace mod_feedback\output;

use plugin_renderer_base;

/**
 * Class renderer
 *
 * @package   mod_feedback
 * @copyright 2021 Peter Dias
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * Generate the tertiary nav
     *
     * @param base_action_bar $actionmenu
     * @return bool|string
     */
    public function main_action_bar(base_action_bar $actionmenu) {
        $context = $actionmenu->export_for_template($this);

        return $this->render_from_template('mod_feedback/main_action_menu', $context);
    }

    /**
     * Render the create template form
     *
     * @param int $id
     * @return bool|string
     * @deprecated since 4.5
     * @todo MDL-82164 This will be deleted in Moodle 6.0.
     */
    #[\core\attribute\deprecated(replacement: null, since: '4.5')]
    public function create_template_form(int $id) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);

        return $this->render_from_template('mod_feedback/create_template', ['id' => $id]);
    }

    /**
     * Builds the feedback page title.
     *
     * @param array $titleparts the different parts to add to the title.
     * @param string $pagetitle the page title to add to the rest of the parts. Empty by default.
     */
    public function set_title(array $titleparts, string $pagetitle = '') {
        if (!empty($pagetitle)) {
            array_unshift($titleparts, $pagetitle);
        }
        $title = implode(\moodle_page::TITLE_SEPARATOR, $titleparts);
        $this->page->set_title($title);
    }
}
