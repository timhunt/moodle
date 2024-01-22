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
use core_component;
use core_course\local\entity\content_item;
use core_course_category;
use core_question\local\bank\question_edit_contexts;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/lib/questionlib.php';
require_once $CFG->dirroot . '/course/modlib.php';

class helper {

    private static array $openmods = [];

    private static array $closedmods = [];

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
        //TODO: only get mods which have questions locally.
        foreach (self::get_closed_modules() as $plugin) {
            $coursemodinfo = \course_modinfo::instance($courseid);
            if ($plugininstances = $coursemodinfo->get_instances_of($plugin)) {
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
                    if ($modinfo = $coursemodinfo->get_instances_of($plugin)) {
                        $instances[$plugin . '_' . $course->id] = $modinfo;
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
     * @param \stdClass $course
     * @param string $bankname
     * @return object|\stdClass
     */
    public static function create_default_open_instance(\stdClass $course, string $bankname) {
        [$module, $context, $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'qbank', 0);
        unset($data->completion);
        $data->visibleoncoursepage = 0;
        $data->name = "{$bankname} course question bank";
        return add_moduleinfo($data, $course);
    }
}
