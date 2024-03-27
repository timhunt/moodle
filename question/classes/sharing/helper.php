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
use core_question\local\bank\question_edit_contexts;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot . '/lib/questionlib.php';
require_once $CFG->dirroot . '/course/modlib.php';

class helper {

    private static array $openmods = [];

    private static array $closedmods = [];

    private static \moodle_recordset $openinstances;

    private static \moodle_recordset $closedinstances;

    /** @var string the type of qbank module that users create */
    public const STANDARD = 'standard';

    /**
     * The type of shared bank module that the system creates.
     * These are created in course restores when no target context can be found,
     * and also for when a question category cannot be deleted safely due to questions being in use.
     *
     * @var string
     */
    public const SYSTEM = 'system';

    /** @var string The type of shared bank module that the system creates for previews. Not used for any other purpose. */
    public const PREVIEW = 'preview';

    /** @var array Shared bank types */
    public const SHARED_TYPES = [self::STANDARD, self::SYSTEM, self::PREVIEW];

    /** @var string Shareable plugin type */
    public const OPEN = 'open';

    /** @var string Non-shareable plugin type */
    public const CLOSED = 'closed';

    /** Plugin types */
    public const PLUGIN_TYPES = [self::OPEN, self::CLOSED];

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
     * @param string $type either self::OPEN for plugin instances that implement FEATURE_PUBLISHES_QUESTIONS,
     * or self::CLOSED for those that don't.
     * @param array $incourseids array of course ids where you want instances included. Leave empty if you want them from all courses.
     * @param array $notincourseids array of course ids where you do not want instances included.
     * @param array $havingcap current user must have these capabilities on each bank context.
     * @param bool $getcategories optionally return the categories belonging to these banks.
     * @return iterable
     */
    public static function get_instances(
            string $type = self::OPEN,
            array $incourseids = [],
            array $notincourseids = [],
            array $havingcap = [],
            bool $getcategories = false
    ): iterable {

        if (!in_array($type, self::PLUGIN_TYPES)) {
            throw new \moodle_exception('Invalid type');
        }

        $validopenrs = isset(self::$openinstances) && self::$openinstances->valid();
        $validclosedrs = isset(self::$closedinstances) && self::$closedinstances->valid();

        if ((!$validopenrs && $type === self::OPEN) || (!$validclosedrs && $type === self::CLOSED)) {
            self::init_instance_records($type, $incourseids, $notincourseids, $getcategories);
        }

        $instances = $type === self::OPEN ? self::$openinstances : self::$closedinstances;

        foreach ($instances as $instance) {
            if (!empty($havingcap)) {
                $context = \context_module::instance($instance->id);
                if (!(new question_edit_contexts($context))->have_one_cap($havingcap)) {
                    continue;
                }
            }

            $cminfo = cm_info::create($instance);
            $toreturn = self::get_return_object($cminfo, $instance->cats ?? '');
            yield $toreturn;
        }

        if ($type === self::OPEN) {
            self::$openinstances->close();
        } else {
            self::$closedinstances->close();
        }
    }

    /**
     * @param string $type
     * @param array $incourseids
     * @param array $notincourseids
     * @param bool $getcategories
     */
    private static function init_instance_records(
            string $type,
            array $incourseids = [],
            array $notincourseids = [],
            bool $getcategories = false
    ): void {
        global $DB;

        $plugins = $type === self::OPEN ? self::get_open_modules() : self::get_closed_modules();
        $pluginssql = [];
        $params = [];

        foreach ($plugins as $key => $plugin) {
            $moduleid = $DB->get_field('modules', 'id', ['name' => $plugin]);
            $sql = "JOIN {{$plugin}} AS p{$key} ON p{$key}.id = cm.instance and cm.module = {$moduleid}";
            if ($plugin === 'qbank') {
                $sql .= " AND p{$key}.type <> '" . self::PREVIEW . "'";
            }
            $pluginssql[] = $sql;
        }
        $pluginssql = implode(' ', $pluginssql);

        if ($getcategories) {
            $select = 'SELECT cm.*,' . $DB->sql_group_concat($DB->sql_concat('qc.id', "'<->'", 'qc.name', "'<->'", 'qc.contextid'), ',') . 'AS cats';
            $catsql = ' JOIN {context} AS c ON c.instanceid = cm.id AND c.contextlevel = ' . CONTEXT_MODULE .
                      ' JOIN {question_categories} AS qc ON qc.contextid = c.id AND qc.parent <> 0';
        } else {
            $select = 'SELECT cm.*';
            $catsql = '';
        }

        if (!empty($notincourseids)) {
            [$notincoursesql, $notincourseparams] = $DB->get_in_or_equal($notincourseids, SQL_PARAMS_QM, 'param', false);
            $notincoursesql = "AND cm.course {$notincoursesql}";
            $params = array_merge($params, $notincourseparams);
        } else {
            $notincoursesql = '';
        }

        if (!empty($incourseids)) {
            [$incoursesql, $incourseparams] = $DB->get_in_or_equal($incourseids);
            $incoursesql = " AND cm.course {$incoursesql}";
            $params = array_merge($params, $incourseparams);
        } else {
            $incoursesql = '';
        }

        $sql = "{$select}
                FROM {course_modules} AS cm
                JOIN {modules} AS m ON m.id = cm.module
                {$pluginssql}
                {$catsql}
                WHERE 1=1 {$notincoursesql} {$incoursesql}
                GROUP BY cm.id";

        if ($type === self::OPEN) {
            self::$openinstances = $DB->get_recordset_sql($sql, $params);
        } else {
            self::$closedinstances = $DB->get_recordset_sql($sql, $params);
        }
    }

    /**
     * Get a list of recently viewed question banks that implement FEATURE_PUBLISHES_QUESTIONS.
     * If any of the stored contexts don't exist anymore then update the user preference record accordingly.
     *
     * @param int $userid
     * @param int $notincourseid if supplied don't return any in this course id
     * @return cm_info[]
     */
    public static function get_recently_used_open_banks(int $userid, int $notincourseid = 0): array {
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
            $cminfo = cm_info::create($cm);
            if (!empty($notincourseid) && $notincourseid == $cminfo->course) {
                continue;
            }
            $record = new stdClass();
            $record->bankmodid = $cminfo->id;
            $record->name = $cminfo->get_formatted_name();
            $toreturn[] = $record;
        }

        if (!empty($invalidcontexts)) {
            $tostore = array_diff($contextids, $invalidcontexts);
            $tostore = implode(',', $tostore);
            set_user_preference(self::RECENTLY_VIEWED, $tostore, $userid);
        }

        return $toreturn ?? [];
    }

    /**
     * @param cm_info $cminfo
     * @param string $categories
     * @return stdClass
     */
    private static function get_return_object(cm_info $cminfo, string $categories = ''): stdClass {

        $concatedcats = !empty($categories) ? explode(',', $categories) : [];
        $categories = array_map(static function($concatedcategory) {
            $values = explode('<->', $concatedcategory);
            $cat = new stdClass();
            $cat->id = $values[0];
            $cat->name = $values[1];
            $cat->contextid = $values[2];
            return $cat;
        }, $concatedcats);

        $bank = new stdClass();
        $bank->bankname = $cminfo->get_formatted_name();
        $bank->cminfo = $cminfo;
        $bank->questioncategories = $categories;

        return $bank;
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

        if (!in_array($type, self::SHARED_TYPES)) {
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
        $data->type = in_array($type, self::SHARED_TYPES) ? $type : self::STANDARD;
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
