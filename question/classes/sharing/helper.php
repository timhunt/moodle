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

    /** @var string The type of module that the system creates for previews. Not used for any other purpose. */
    public const PREVIEW = 'preview';

    public const TYPES = [self::STANDARD, self::SYSTEM, self::PREVIEW];

    /**
     * User preferences record key to store recently viewed question banks.
     */
    public const RECENTLY_VIEWED = 'recently_viewed_open_banks';

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
     * Get instances in $courseid that implement FEATURE_PUBLISHES_QUESTIONS.
     *
     * @param int $courseid
     * @param array $havingcap capabilities to check current user access for.
     * @param bool $getcategories optionally return categories
     * @return array
     */
    public static function get_course_open_instances(
            int $courseid,
            array $havingcap = [],
            bool $getcategories = false
    ): array {
        global $DB;

        $categories = [];
        $instances = [];

        foreach (self::get_open_modules() as $plugin) {
            $sql = "SELECT cm.*
                    FROM {course_modules} AS cm
                    JOIN {modules} AS m ON m.id = cm.module
                    JOIN {{$plugin}} AS p ON p.id = cm.instance
                    WHERE m.name = :modname AND cm.course = :courseid";
            $params = ['modname' => $plugin, 'courseid' => $courseid];

            if ($plugin === 'qbank') {
                $sql .= ' AND p.type <> :type';
                $params['type'] = self::PREVIEW;
            }

            if ($plugininstances = $DB->get_records_sql($sql, $params)) {
                $cminfos = array_map(static fn($cmrecord) => cm_info::create($cmrecord), $plugininstances);

                if (!empty($havingcap)) {
                    $cminfos = array_filter($cminfos, static function($cminfo) use ($havingcap) {
                        return (new question_edit_contexts($cminfo->context))->have_one_cap($havingcap);
                    });
                }

                if (empty($cminfos)) {
                    continue;
                }

                usort($cminfos, static fn($a, $b) => $a->get_formatted_name() <=> $b->get_formatted_name());

                if ($getcategories) {
                    $contextids = array_map(static fn($cminfo) => $cminfo->context->id, $cminfos);
                    $categories[] = self::get_categories($contextids);
                }
                $instances[$plugin . '_' . $courseid] = $cminfos;
            }
        }
        return [$instances, $categories];
    }

    /**
     * @param $contextids
     * @return array
     */
    private static function get_categories($contextids): array {
        global $DB;
        if (empty($contextids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids);
        $sql = "SELECT * FROM {question_categories} WHERE contextid {$insql} AND parent <> 0";
        return $DB->get_records_sql($sql, $inparams);
    }

    /**
     * @param int $courseid
     * @param array $havingcap capabilities to check against each module context for the current user.
     * @return cm_info[][]
     */
    public static function get_course_closed_instances(int $courseid, array $havingcap = []): array {
        foreach (self::get_closed_modules() as $plugin) {
            $coursemodinfo = \course_modinfo::instance($courseid);
            if ($plugininstances = $coursemodinfo->get_instances_of($plugin)) {
                $plugininstances = array_filter($plugininstances, static function($instance) use ($havingcap) {
                    global $DB;
                    if ($instance->deletioninprogress) {
                        return false;
                    }
                    if (!empty($havingcap)) {
                        $hasacapability = (new question_edit_contexts($instance->context))->have_one_cap($havingcap);
                        if (!$hasacapability) {
                            return false;
                        }
                    }

                    $categories = $DB->get_records('question_categories', ['contextid' => $instance->context->id]);
                    foreach ($categories as $category) {
                        if (question_category_in_use($category->id)) {
                            return true;
                        }
                    }
                    return false;
                });
                if (empty($plugininstances)) {
                    continue;
                }
                usort($plugininstances, static fn($a, $b) => $a->get_formatted_name() <=> $b->get_formatted_name());
                $instances[$plugin . '_' . $courseid] = $plugininstances;
            }
        }
        return $instances ?? [];
    }

    /**
     * Get instances that exist across ALL courses that implement FEATURE_PUBLISHES_QUESTIONS.
     *
     * @param array $notincourseids array of course ids where you do not want instances included.
     * @param array $havingcap current user must have these capabilities on each bank context.
     * @param bool $getcategories optionally return the categories belonging to these banks.
     * @return array
     */
    public static function get_all_open_instances(
            array $notincourseids = [],
            array $havingcap = [],
            bool $getcategories = false
    ): array {
        $instances = [];
        $allcategories = [];
        foreach (core_course_category::get_all() as $category) {
            foreach ($category->get_courses(['recursive', 'idonly']) as $course) {
                if (in_array($course->id, $notincourseids)) {
                    continue;
                }
                [$courseinstances, $categories] = self::get_course_open_instances($course->id, $havingcap, $getcategories);
                if (empty($courseinstances)) {
                    continue;
                }
                $allcategories[] = array_merge([], ...$categories);

                $instances[] = $courseinstances;
            }
        }
        $instances = array_merge([], ...$instances);
        $allcategories = array_merge([],... $allcategories);
        return [$instances, $allcategories];
    }

    /**
     * Get a list of recently viewed question banks that implement FEATURE_PUBLISHES_QUESTIONS.
     * If any of the stored contexts don't exist anymore then update the user preference record accordingly.
     *
     * @param $userid
     * @return cm_info[]
     */
    public static function get_recently_used_open_banks($userid): array {
        $prefs = get_user_preferences(self::RECENTLY_VIEWED, null, $userid);
        $contextids = !empty($prefs) ? explode(',', $prefs) : [];
        if (empty($contextids)) {
            return $contextids;
        }
        $invalidcontexts = [];

        foreach ($contextids as $contextid) {
            if (!$context = \context::instance_by_id($contextid, IGNORE_MISSING)) {
                $invalidcontexts[] = $context;
                continue;
            }
            if ($context->contextlevel !== CONTEXT_MODULE) {
                throw new \moodle_exception('Invalid question bank contextlevel: ' . $context->contextlevel);
            }
            [, $cm] = get_module_from_cmid($context->instanceid);
            $toreturn[] = cm_info::create($cm);
        }

        if (!empty($invalidcontexts)) {
            $tostore = array_diff($contextids, $invalidcontexts);
            $tostore = implode(',', $tostore);
            set_user_preference(self::RECENTLY_VIEWED, $tostore, $userid);
        }

        return $toreturn ?? [];
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

    public static function get_preview_open_instance_type($createifnotexists = false) {
        $modinfo = get_fast_modinfo(get_site());
        $qbanks = $modinfo->get_instances_of('qbank');
        $qbanks = array_filter($qbanks, static function($qbank) {
            global $DB;
            return $DB->record_exists('qbank', ['id' => $qbank->instance, 'type' => self::PREVIEW]);
        });

        // Should only be one of these so return the first anyway.
        $qbank = reset($qbanks);

        if (!$qbank && $createifnotexists) {
            $qbank = self::create_default_open_instance(get_site(), "Preview system bank", self::PREVIEW);
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

        // Preview bank must be created at site course.
        if ($type === self::PREVIEW) {
            if ($qbank = self::get_preview_open_instance_type()) {
                return $qbank;
            }
            $course = get_site();
        }

        // We can only have one of these types per course.
        if ($type === self::SYSTEM && $qbank = self::get_default_open_instance_system_type($course)) {
            return $qbank;
        }

        $module = $DB->get_record('modules', ['name' => 'qbank'], '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        // STANDARD type needs capability checks.
        if ($type === self::STANDARD) {
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
