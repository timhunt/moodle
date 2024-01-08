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
 * Restore test for mod_qbank.
 *
 * @package    mod_qbank
 * @copyright  2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_qbank\backup;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

class restore_date_test extends \restore_date_testcase {

    public function test_restore_dates(): void {
        global $DB;

        [$course, $module] = $this->create_course_and_module('qbank', ['timemodified' => time()]);
        $newcourseid = $this->backup_and_restore($course);
        $newmodule = $DB->get_record('qbank', ['course' => $newcourseid]);
        $this->assertFieldsNotRolledForward($module, $newmodule, ['timemodified']);
    }
}
