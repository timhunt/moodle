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
 * mod_qbank install operations to transfer question categories to new contexts.
 *
 * @package    mod_qbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_qbank\task;

use context_module;
use context_system;
use core\context;
use core\task\adhoc_task;
use core_course_category;
use core_question\sharing\helper;
use Throwable;

class install extends adhoc_task {

    /**
     * This script transfers question categories at CONTEXT_SITE, CONTEXT_COURSE, & CONTEXT_COURSECAT to a new qbank instance
     * context.
     *
     * Firstly, it finds any question categories where questions are not being used and deletes them, including questions.
     *
     * Then for any remaining, if it is at course level context, it creates a mod_qbank instance taking the course name
     * and moves the category there including subcategories, files and tags.
     *
     * If the original question category context was a course category context, then it creates a course in that category,
     * taking the category name. Then it creates a mod_qbank instance in that course and moves the category & sub categories
     * there, along with files and tags belonging to those categories.
     *
     * @inheritDoc
     */
    public function execute() {

        global $DB, $CFG;

        require_once $CFG->dirroot . '/course/modlib.php';

        foreach ($DB->get_records('question_categories', ['parent' => 0]) as $oldtopcategory) {

            if (!$oldcontext = context::instance_by_id($oldtopcategory->contextid, IGNORE_MISSING)) {
                // That context does not exist anymore, we will treat these as if they were at site context level.
                $oldcontext = context_system::instance();
            }

            $trans = $DB->start_delegated_transaction();

            try {
                // Remove any questions and categories below the current 'top' if they are unused.
                $subcategories = $DB->get_records_select('question_categories',
                        'parent <> 0 AND contextid = :contextid',
                        ['contextid' => $oldtopcategory->contextid]
                );
                // This gives us categories in parent -> child order so array_reverse it,
                // because we should process stale categories from the bottom up.
                $subcategories = array_reverse(\sort_categories_by_tree($subcategories, $oldtopcategory->id));
                foreach ($subcategories as $subcategory) {
                    \qbank_managecategories\helper::question_remove_stale_questions_from_category($subcategory->id);
                    if (!question_category_in_use($subcategory->id)) {
                        question_category_delete_safe($subcategory);
                    }
                }

                // We don't want to transfer any categories at valid contexts i.e. quiz modules.
                if ($oldcontext->contextlevel === CONTEXT_MODULE) {
                    $trans->allow_commit();
                    continue;
                }

                // Category is in use so let's process it. Firstly, a course and mod instance is needed.
                switch ($oldcontext->contextlevel) {
                    case CONTEXT_SYSTEM:
                        $course = get_site();
                        $bankname = 'System shared question bank';
                        break;
                    case CONTEXT_COURSECAT:
                        $coursecategory = core_course_category::get($oldcontext->instanceid);
                        $courseshortname = "{$coursecategory->name}-{$coursecategory->id}";
                        $course = $this->create_course($coursecategory, $courseshortname);
                        $bankname = "{$coursecategory->name} shared question bank";
                        break;
                    case CONTEXT_COURSE:
                        $course = get_course($oldcontext->instanceid);
                        $bankname = "{$course->shortname} shared question bank";
                        break;
                    default:
                        // This shouldn't be possible, so we can't really transfer it.
                        // We should commit any pre-transfer category cleanup though.
                        $trans->allow_commit();
                        continue 2;
                }

                if (!$newmod = helper::get_default_open_instance_system_type($course)) {
                    $newmod = helper::create_default_open_instance($course, $bankname, helper::SYSTEM);
                }

                // We have our new mod instance, now move all the subcategories of the old 'top' category to this new context.
                $this->move_category($oldtopcategory, $newmod->context);

            } catch (Throwable $t) {
                debugging("Problem encountered processing categoryid: {$oldtopcategory->id}");
                $trans->rollback($t);
            }

            // Job done, lets delete the old 'top' category.
            $DB->delete_records('question_categories', ['id' => $oldtopcategory->id]);
            $trans->allow_commit();
        }
    }

    private function create_course(object $coursecategory, string $shortname): object {
        $data = (object) [
                'enablecompletion' => 0,
                'fullname' => "Shared teaching resources for Category: {$coursecategory->name}",
                'shortname' => $shortname,
                'category' => $coursecategory->id,
        ];
        return \create_course($data);
    }

    private function move_category(object $oldtopcategory, \context $newcontext): void {
        global $DB;

        $newtopcategory = question_get_top_category($newcontext->id, true);

        // This function moves subcategories, so we have to start at the top.
        question_move_category_to_context($oldtopcategory->id, $oldtopcategory->contextid, $newcontext->id);

        // Move the parent from the old top category to the new one.
        $DB->set_field('question_categories', 'parent', $newtopcategory->id, ['parent' => $oldtopcategory->id]);
    }
}
