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

use action_link;
use renderer_base;

/**
 * Create a list of 'Add another question bank' links for plugins that support FEATURE_PUBLISHES_QUESTIONS.
 */
class add_bank_list implements \renderable, \templatable {

    /** @var \stdClass */
    private \stdClass $course;

    /** @var array */
    private array $bankplugins;

    public function __construct(\stdClass $course, array $bankplugins) {
        $this->course = $course;
        $this->bankplugins = $bankplugins;
    }

    public function export_for_template(renderer_base $output): array {

        foreach ($this->bankplugins as $plugin) {

            if (!plugin_supports('mod', $plugin, FEATURE_PUBLISHES_QUESTIONS)) {
                continue;
            }

            $link = new action_link(
                new \moodle_url('/course/modedit.php', [
                    'add' => $plugin,
                    'course' => $this->course->id,
                    'section' => 0,
                    'return' => 0,
                    'sr' => 0,
                    'beforemod' => 0,
                ]),
                get_string('addanotherbank', $plugin),
                null,
                null,
                new \pix_icon('t/add', get_string('addanotherbank', $plugin))
            );
            $addbanks[] = $link->export_for_template($output);
        }

        return $addbanks ?? [];
    }
}
