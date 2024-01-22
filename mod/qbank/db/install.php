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

use core\context;

require_once __DIR__ . '/installlib.php';

/**
 * This script transfers question categories at CONTEXT_SITE, CONTEXT_COURSE, & CONTEXT_COURSECAT to a new qbank instance context.
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
 * @return void
 */
function xmldb_qbank_install(): void {
    global $DB;

    foreach ($DB->get_records('question_categories', ['parent' => 0]) as $oldtopcategory) {

        $oldcontext = context::instance_by_id($oldtopcategory->contextid);

        $trans = $DB->start_delegated_transaction();

        try {
            // Remove any questions and categories below the current 'top' if they are unused.
            $subcategories = $DB->get_records_select('question_categories',
                    'parent <> 0 AND contextid = :contextid',
                    ['contextid' => $oldcontext->id]
            );
            // This gives us categories in parent -> child order so array_reverse it,
            // because we should process stale categories from the bottom up.
            $subcategories = array_reverse(sort_categories_by_tree($subcategories, $oldtopcategory->id));
            foreach ($subcategories as $subcategory) {
                \qbank_managecategories\helper::question_remove_stale_questions_from_category($subcategory->id);
                if (!question_category_in_use($subcategory->id)) {
                    question_category_delete_safe($subcategory);
                    // Log the deletion of this category.
                    $event = \core\event\question_category_deleted::create_from_question_category_instance($subcategory);
                    $event->add_record_snapshot('question_categories', $subcategory);
                    $event->trigger();
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
                    $course = \mod_qbank\create_course($coursecategory, $courseshortname);
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

            $newmod = \mod_qbank\create_module($course, $bankname);
            $newcontext = context_module::instance($newmod->coursemodule);

            // We have our new mod instance, now move all the subcategories of the old 'top' category to this new context.
            \mod_qbank\move_category($oldtopcategory, $newcontext);

        } catch (Throwable $t) {
            debugging("Problem encountered processing categoryid: {$oldtopcategory->id} due to: {$t->getTraceAsString()}");
            $trans->rollback($t);
            continue;
        }

        // Job done, lets delete the old 'top' category.
        $DB->delete_records('question_categories', ['id' => $oldtopcategory->id]);
        $event = \core\event\question_category_deleted::create_from_question_category_instance($oldtopcategory);
        $event->add_record_snapshot('question_categories', $oldtopcategory);
        $event->trigger();
        $trans->allow_commit();
    }
}
