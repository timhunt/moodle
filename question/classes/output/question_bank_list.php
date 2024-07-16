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
 * Create a list of question bank type links to manage their respective instances.
 */
class question_bank_list implements \renderable, \templatable {

    /** @var iterable */
    private iterable $bankinstances;

    public function __construct(iterable $bankinstances) {
        $this->bankinstances = $bankinstances;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {

        $banks = [];
        foreach ($this->bankinstances as $instance) {
            if (plugin_supports('mod', $instance->cminfo->modname, FEATURE_PUBLISHES_QUESTIONS)) {
                $actions = course_get_cm_edit_actions($instance->cminfo);
                $actionmenu = new \action_menu();
                $actionmenu->set_kebab_trigger(get_string('edit'));
                $actionmenu->add_secondary_action($actions['update']);
                $actionmenu->add_secondary_action($actions['delete']);
                $actionmenu->add_secondary_action($actions['assign']);
                $managebankexport = $actionmenu->export_for_template($output);
            } else {
                $managebankexport = null;
            }

            $managequestions = new action_link(
                new \moodle_url("/mod/{$instance->cminfo->modname}/view.php", [
                    'id' => $instance->cminfo->id,
                ]),
                $instance->name,
            );

            $banks[] = [
                'purpose' => plugin_supports('mod', $instance->cminfo->modname, FEATURE_MOD_PURPOSE),
                'iconurl' => $instance->cminfo->get_icon_url(),
                'modname' => $instance->name,
                'description' => $instance->cminfo->get_formatted_content(),
                'managequestions' => $managequestions->export_for_template($output),
                'managebank' => $managebankexport,
            ];
        }

        usort($banks, static fn($a, $b) => $a['modname'] <=> $b['modname']);

        return $banks;
    }
}
