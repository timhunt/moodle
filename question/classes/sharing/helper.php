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
 * Helper class for qbank sharing.
 *
 * @package    moodlecore
 * @subpackage questionbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_question\sharing;

use cm_info;
use context_course;
use core_course_category;
use core_question\local\bank\question_edit_contexts;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/lib/questionlib.php';
require_once $CFG->dirroot . '/course/modlib.php';

class helper {

    private static array $openmods = [];

    private static array $closedmods = [];

    /** @var string the type of qbank module that users create */
    public const STANDARD = 'standard';

    /**
     * The type of module that the system creates.
     * These are created in course restores when no target context can be found,
     * and also for when a question category cannot be deleted safely due to questions being in use.
     *
     * @var string
     */
    public const SYSTEM = 'system';

    public const TYPES = [self::STANDARD, self::SYSTEM];

    /**
     * Modules that share questions via FEATURE_PUBLISHES_QUESTIONS.
     *
     * @return array
     */
    public static function get_open_modules(): array {
        if (!empty(self::$openmods)) {
            return self::$openmods;
        }

        $plugins = \core_component::get_plugin_list('mod');
        self::$openmods = array_filter(
                array_keys($plugins),
                static fn($plugin) => plugin_supports('mod', $plugin, FEATURE_PUBLISHES_QUESTIONS) &&
                        question_module_uses_questions($plugin)
        );

        return self::$openmods;
    }

    /**
     * Modules that are closed to sharing questions have FEATURE_USES_QUESTIONS flag only.
     *
     * @return array
     */
    public static function get_closed_modules(): array {
        if (!empty(self::$closedmods)) {
            return self::$closedmods;
        }

        $plugins = \core_component::get_plugin_list('mod');
        self::$closedmods = array_filter(
                array_keys($plugins),
                static fn($plugin) => !plugin_supports('mod', $plugin, FEATURE_PUBLISHES_QUESTIONS) &&
                        question_module_uses_questions($plugin)
        );

        return self::$closedmods;
    }

    /**
     * @param int $courseid
     * @return cm_info[][]
     */
    public static function get_course_open_instances(int $courseid): array {
        foreach (self::get_open_modules() as $plugin) {
            $coursemodinfo = \course_modinfo::instance($courseid);
            if ($plugininstances = $coursemodinfo->get_instances_of($plugin)) {
                usort($plugininstances, static fn($a, $b) => $a->get_formatted_name() <=> $b->get_formatted_name());
                $instances[$plugin] = $plugininstances;
            }
        }
        return $instances ?? [];
    }

    /**
     * @param int $courseid
     * @return cm_info[][]
     */
    public static function get_course_closed_instances(int $courseid): array {
        foreach (self::get_closed_modules() as $plugin) {
            $coursemodinfo = \course_modinfo::instance($courseid);
            if ($plugininstances = $coursemodinfo->get_instances_of($plugin)) {
                $plugininstances = array_filter($plugininstances, static function($instance) {
                    global $DB;
                    if ($instance->deletioninprogress) {
                        return false;
                    }
                    $categories = $DB->get_records('question_categories', ['contextid' => $instance->context->id]);
                    foreach ($categories as $category) {
                        if (question_category_in_use($category->id)) {
                            return true;
                        }
                    }
                    return false;
                });
                usort($plugininstances, static fn($a, $b) => $a->get_formatted_name() <=> $b->get_formatted_name());
                $instances[$plugin] = $plugininstances;
            }
        }
        return $instances ?? [];
    }

    /**
     * Get instances that exist across ALL courses that implement FEATURE_PUBLISHES_QUESTIONS.
     *
     * @return cm_info[][]
     */
    public static function get_all_open_instances(): array {
        $plugins = self::get_open_modules();
        foreach (core_course_category::get_all() as $category) {
            foreach ($category->get_courses(['recursive', 'idonly']) as $course) {
                foreach ($plugins as $plugin) {
                    $coursemodinfo = \course_modinfo::instance($course->id);
                    if ($modinstances = $coursemodinfo->get_instances_of($plugin)) {
                        usort($modinstances, static fn($a, $b) => $a->get_formatted_name() <=> $b->get_formatted_name());
                        $instances[$plugin . '_' . $course->id] = $modinstances;
                    }
                }
            }
        }
        return $instances ?? [];
    }

    /**
     * @param array $tabs
     * @param cm_info[][] $allinstances
     * @return cm_info[][]
     */
    public static function filter_by_question_edit_access(array $tabs, array $allinstances): array {
        $filtered = [];
        foreach ($allinstances as $plugin => $plugininstances) {
            $instances = array_filter($plugininstances, static function($cminfo) use ($tabs) {
                return (new question_edit_contexts($cminfo->context))->have_one_edit_tab_cap($tabs);
            });
            if (!empty($instances)) {
                $filtered[$plugin] = $instances;
            }
        }
        return $filtered;
    }

    /**
     * Get the system type mod_qbank instance for this course, optionally create it if it does not yet exist.
     * @see self::SYSTEM
     *
     * @param stdClass $course
     * @param bool $createifnotexists
     * @return cm_info|false
     */
    public static function get_default_open_instance_system_type(stdClass $course, bool $createifnotexists = false): cm_info|false {

        $modinfo = get_fast_modinfo($course);
        $qbanks = $modinfo->get_instances_of('qbank');

        $qbanks = array_filter($qbanks, static function($qbank) {
            global $DB;
            return $DB->record_exists('qbank', ['id' => $qbank->instance, 'type' => self::SYSTEM]);
        });

        // Should only be one of these so return the first anyway.
        $qbank = reset($qbanks);

        if (!$qbank && $createifnotexists) {
            $qbank = self::create_default_open_instance($course, "{$course->fullname} system bank", self::SYSTEM);
        }

        return $qbank;
    }

    /**
     * @param stdClass $course the course that the new module is being created in
     * @param string $bankname name of the new module
     * @param string $type @see self::TYPES
     * @return cm_info
     */
    public static function create_default_open_instance(stdClass $course, string $bankname, string $type = self::STANDARD): cm_info {
        global $DB;

        if (!in_array($type, self::TYPES)) {
            throw new \RuntimeException('invalid type');
        }

        // We can only have one of these types per course.
        if ($type === self::SYSTEM && $qbank = self::get_default_open_instance_system_type($course)) {
            return $qbank;
        }

        $module = $DB->get_record('modules', ['name' => 'qbank'], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        // Types other than system need capability checks.
        if ($type !== self::SYSTEM) {
            require_capability('moodle/course:manageactivities', $context);
            if (!course_allowed_module($course, $module->name)) {
                throw new \moodle_exception('moduledisable');
            }
        }

        $data = new stdClass();
        $data->section = 0;
        $data->visible = 0;
        $data->course = $course->id;
        $data->module = $module->id;
        $data->modulename = $module->name;
        $data->groupmode = $course->groupmode;
        $data->groupingid = $course->defaultgroupingid;
        $data->id = '';
        $data->instance = '';
        $data->coursemodule = '';
        $data->downloadcontent = DOWNLOAD_COURSE_CONTENT_ENABLED;
        $data->visibleoncoursepage = 0;
        $data->name = $bankname;
        $data->type = in_array($type, self::TYPES) ? $type : self::STANDARD;
        $data->showdescription = $type === self::STANDARD ? 0 : 1;

        $mod = add_moduleinfo($data, $course);

        // Have to set this manually as the system because this bank type is not intended to be created directly by a user.
        if ($type === self::SYSTEM) {
            $DB->set_field('qbank', 'intro', get_string('systembankdescription', 'mod_qbank'), ['id' => $mod->instance]);
            $DB->set_field('qbank', 'introformat', FORMAT_HTML, ['id' => $mod->instance]);
        }

        return get_fast_modinfo($course)->get_cm($mod->coursemodule);
    }
}
