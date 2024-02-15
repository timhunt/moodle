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

namespace core_question;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Class core_question_backup_testcase
 *
 * @package    core_question
 * @category   test
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait backup_restore_trait {

    /**
     * Makes a backup of the course.
     *
     * @param int $courseormodid The course|course_module id.
     * @return string Unique identifier for this backup.
     */
    protected function backup_course($courseormodid, $backuptype = \backup::TYPE_1COURSE) {
        global $CFG, $USER;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new \backup_controller($backuptype, $courseormodid,
                \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO, \backup::MODE_IMPORT,
                $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup that has been made earlier.
     *
     * @param string $backupid The unique identifier of the backup.
     * @param string $courseid Course id of where the restore is happening.
     * @param string[] $expectedprecheckwarning
     * @return int The new course id.
     */
    protected function restore_to_course($backupid, $courseid, $expectedprecheckwarning = []): void {
        global $CFG, $USER;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $rc = new \restore_controller($backupid, $courseid,
                \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
                \backup::TARGET_NEW_COURSE);

        $precheck = $rc->execute_precheck();
        if (!$expectedprecheckwarning) {
            $this->assertTrue($precheck);
        } else {
            $precheckresults = $rc->get_precheck_results();
            $this->assertEqualsCanonicalizing($expectedprecheckwarning, $precheckresults['warnings']);
            $this->assertCount(1, $precheckresults);
        }
        $rc->execute_plan();
        $rc->destroy();
    }
}
