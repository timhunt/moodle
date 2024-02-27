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
 * Steps definitions related with the question bank management.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

class behat_mod_qbank extends behat_base {

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | Question bank     | Qbank name  | The question bank page for a qbank module instance     |
     *
     * @param string $type identifies which type of page this is, e.g. 'Question bank'.
     * @param string $identifier identifies the particular page, e.g. 'Qbank 1 > mod_qbank > question bank'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'question bank':
                return new moodle_url('/mod/qbank/view.php',
                        ['id' => $this->get_cm_by_qbank_name($identifier)->id]
                );
            default:
                throw new Exception('Unrecognised mod_qbank page type "' . $type . '."');
        }
    }

    /**
     * Get a qbank cmid from the qbank name.
     *
     * @param string $name qbank name.
     * @return object cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_qbank_name(string $name): object {
        $qbank = $this->get_qbank_by_name($name);
        return get_coursemodule_from_instance('qbank', $qbank->id, $qbank->course);
    }

    /**
     * @param string $name
     * @return object
     */
    protected function get_qbank_by_name(string $name): object {
        global $DB;
        return $DB->get_record('qbank', ['name' => $name], '*', MUST_EXIST);
    }
}
