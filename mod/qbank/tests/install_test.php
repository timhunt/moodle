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
 * mod_qbank install script testing.
 *
 * @package    mod_qbank
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_question\local\bank\question_version_status;

/**
 * Before testing, we firstly need to create some data to emulate what sites can have pre-upgrade.
 * Namely, we are adding question categories and questions to deprecated contexts i.e. anything not CONTEXT_MODULE,
 * and to quiz local banks too as we need to test these don't get touched.
 * It also adds questions to some categories that are not used by quizzes anywhere.
 *
 * The tests cover a few areas.
 * 1: We validate the data setup is correct before we run the install script testing.
 * 2: The install test validates that any question categories not in CONTEXT_MODULE get transferred to relevant mod_qbank
 * instances including their questions. It also validates that any stale questions that are not in use by quizzes are removed
 * along with empty categories.
 */
class install_test extends advanced_testcase {
    private \core\context\coursecat $coursecatcontext;
    private \core\context\course $coursecontext;
    private \core\context\course $stalecoursecontext;
    private \core\context\module $quizcontext;
    /** @var stdClass[] */
    private array $stalequestions;

    private function get_question_data(array $categoryids): array {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($categoryids);

        $sql = "SELECT q.id, qbe.questioncategoryid AS categoryid
                  FROM {question} AS q
                  JOIN {question_versions} AS qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} AS qbe ON qbe.id = qv.questionbankentryid
                 WHERE qbe.questioncategoryid {$insql}";

        return $DB->get_records_sql($sql, $inparams);
    }

    /**
     * This is hacky, but we can't use the API to create these as non module contexts are deprecated for holding question
     * categories.
     */
    private function create_question_category(string $name, int $contextid, int $parentid = 0): stdClass {

        global $DB;

        if (!$parentid) {
            if (!$parent = $DB->get_record('question_categories', ['contextid' => $contextid, 'parent' => 0, 'name' => 'top'])) {
                $parent = new stdClass();
                $parent->name = 'top';
                $parent->info = '';
                $parent->contextid = $contextid;
                $parent->parent = 0;
                $parent->sortorder = 0;
                $parent->stamp = make_unique_id_code();
                $parent->id = $DB->insert_record('question_categories', $parent);
            }
            $parentid = $parent->id;
        }

        $record = (object) [
            'name' => $name,
            'parent' => $parentid,
            'contextid' => $contextid,
            'info' => '',
            'infoformat' => FORMAT_HTML,
            'stamp' => make_unique_id_code(),
            'sortorder' => 999,
            'idnumber' => null,
        ];

        $record->id = $DB->insert_record('question_categories', $record);
        return $record;
    }

    protected function setup_pre_install_data(): void {
        global $DB;
        self::setAdminUser();
        $questiongenerator = self::getDataGenerator()->get_plugin_generator('core_question');
        $quizgenerator = self::getDataGenerator()->get_plugin_generator('mod_quiz');

        // Setup 2 categories at site level context, with a question in each.
        $sitecontext = context_system::instance();
        $site = get_site();

        $siteparentcat = $this->create_question_category('Site Parent Cat', $sitecontext->id);

        $sitechildcat = $this->create_question_category('Site Child Cat', $sitecontext->id, $siteparentcat->id);

        $question1 = $questiongenerator->create_question(
            'shortanswer',
            null,
            ['category' => $siteparentcat->id, 'status' => question_version_status::QUESTION_STATUS_READY]
        );
        $question2 = $questiongenerator->create_question(
            'shortanswer',
            null,
            ['category' => $sitechildcat->id, 'status' => question_version_status::QUESTION_STATUS_READY]
        );

        // Add a quiz to the site course and put those questions into it.
        $quiz = $quizgenerator->create_instance(['course' => $site->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        quiz_add_quiz_question($question1->id, $quiz, 1);
        quiz_add_quiz_question($question2->id, $quiz, 1);

        // Create a course category and then a question category attached to that context.
        $coursecategory = self::getDataGenerator()->create_category();
        $this->coursecatcontext = context_coursecat::instance($coursecategory->id);
        $coursecatcat = $this->create_question_category('Course Cat Parent Cat', $this->coursecatcontext->id);

        // Add a question to the category just made.
        $question3 = $questiongenerator->create_question('shortanswer', null, ['category' => $coursecatcat->id]);

        // Add a quiz to the course category and put those questions into it.
        $course = self::getDataGenerator()->create_course(['category' => $coursecategory->id]);
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        quiz_add_quiz_question($question3->id, $quiz, 1);

        // Create 2 nested categories with questions in them at course context level.
        $course = self::getDataGenerator()->create_course();
        $this->coursecontext = context_course::instance($course->id);
        $courseparentcat1 = $this->create_question_category('Course Parent Cat', $this->coursecontext->id);
        $coursechildcat1 = $this->create_question_category(
            'Course Child Cat',
            $this->coursecontext->id,
            $courseparentcat1->id
        );

        $question4 = $questiongenerator->create_question('shortanswer', null, ['category' => $courseparentcat1->id]);
        $question5 = $questiongenerator->create_question('shortanswer', null, ['category' => $coursechildcat1->id]);

        // Make the questions 'in use'.
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        quiz_add_quiz_question($question4->id, $quiz, 1);
        quiz_add_quiz_question($question5->id, $quiz, 1);

        // Create some nested categories with no questions in use.
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $courseparentcat1 = $this->create_question_category('Stale Course Parent Cat1', $context->id);
        $coursechildcat1 = $this->create_question_category('Stale Course Child Cat1', $context->id, $courseparentcat1->id);
        $courseparentcat2 = $this->create_question_category('Stale Course Parent Cat2', $context->id);
        $coursechildcat2 = $this->create_question_category('Stale Course Child Cat2', $context->id, $courseparentcat2->id);
        $coursegrandchildcat1 = $this->create_question_category('Stale Course Grandchild Cat1', $context->id, $coursechildcat2->id);
        $this->stalecoursecontext = context_course::instance($course->id);

        // Make all the questions hidden.
        $this->stalequestions[] = $questiongenerator->create_question('shortanswer',
            null,
            ['category' => $courseparentcat1->id, 'status' => question_version_status::QUESTION_STATUS_HIDDEN]
        );
        $this->stalequestions[] = $questiongenerator->create_question('shortanswer',
            null,
            ['category' => $coursechildcat1->id, 'status' => question_version_status::QUESTION_STATUS_HIDDEN]
        );
        $this->stalequestions[] = $questiongenerator->create_question('shortanswer',
            null,
            ['category' => $courseparentcat2->id, 'status' => question_version_status::QUESTION_STATUS_HIDDEN]
        );
        $this->stalequestions[] = $questiongenerator->create_question('shortanswer',
            null,
            ['category' => $coursechildcat2->id, 'status' => question_version_status::QUESTION_STATUS_HIDDEN]
        );
        $this->stalequestions[] = $questiongenerator->create_question('shortanswer',
            null,
            ['category' => $coursegrandchildcat1->id, 'status' => question_version_status::QUESTION_STATUS_HIDDEN]
        );

        foreach ($this->stalequestions as $question) {
            $DB->set_field('question_versions',
                'status',
                question_version_status::QUESTION_STATUS_HIDDEN,
                ['questionid' => $question->id]
            );
        }

        // Set up a quiz with some categories and questions attached to it.
        $course = self::getDataGenerator()->create_course();
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'grade' => 100.0, 'sumgrades' => 2, 'layout' => '1,0']);
        $this->quizcontext = context_module::instance($quiz->cmid);
        $quizparentcat1 = $this->create_question_category('Quiz Mod Parent Cat1', $this->quizcontext->id);
        $quizchildcat1 = $this->create_question_category('Quiz Mod Child Cat1', $this->quizcontext->id, $quizparentcat1->id);
        $question1 = $questiongenerator->create_question('shortanswer', null, ['category' => $quizparentcat1->id]);
        $question2 = $questiongenerator->create_question('shortanswer', null, ['category' => $quizchildcat1->id]);
        quiz_add_quiz_question($question1->id, $quiz, 1);
        quiz_add_quiz_question($question2->id, $quiz, 1);
    }

    public function test_setup_pre_install_data() {
        global $DB;
        $this->resetAfterTest();
        $this->setup_pre_install_data();

        $sitecontext = context_system::instance();
        $allsitecats = $DB->get_records('question_categories', ['contextid' => $sitecontext->id], 'id ASC');

        // Make sure we have 2 site level question categories below 'top' and that the child is below the parent.
        $this->assertCount(3, $allsitecats);
        $parentcat = next($allsitecats);
        $childcat = end($allsitecats);
        $this->assertEquals($parentcat->id, $childcat->parent);

        // Make sure we have 1 question per the above site level question categories.
        $questions = $this->get_question_data(array_map(static fn($cat) => $cat->id, $allsitecats));
        usort($questions, static fn($a, $b) => $a->categoryid <=> $b->categoryid);
        $this->assertCount(2, $questions);
        $parentcatq = reset($questions);
        $childcatq = end($questions);
        $this->assertEquals($parentcat->id, $parentcatq->categoryid);
        $this->assertEquals($childcat->id, $childcatq->categoryid);

        // Make sure that the course category has a question category below 'top'.
        $allcoursecatcats = $DB->get_records('question_categories', ['contextid' => $this->coursecatcontext->id], 'id ASC');
        $this->assertCount(2, $allcoursecatcats);
        $topcat = reset($allcoursecatcats);
        $parentcat = end($allcoursecatcats);
        $this->assertEquals($topcat->id, $parentcat->parent);

        // Make sure we have 1 question in the above course category level question category.
        $questions = $this->get_question_data(array_map(static fn($cat) => $cat->id, $allcoursecatcats));
        $this->assertCount(1, $questions);
        $question = reset($questions);
        $this->assertEquals($parentcat->id, $question->categoryid);

        // Make sure we have 3 question categories at course level (including 'top') with some questions in them.
        $allcoursecats = $DB->get_records('question_categories', ['contextid' => $this->coursecontext->id], 'id ASC');
        $this->assertCount(3, $allcoursecats);
        $parentcat = next($allcoursecats);
        $childcat = end($allcoursecats);
        $this->assertEquals($parentcat->id, $childcat->parent);
        $questions = $this->get_question_data(array_map(static fn($cat) => $cat->id, $allcoursecats));
        $this->assertCount(2, $questions);

        // Make sure we have 6 stale question categories at course level (including 'top') with some questions in them.
        $questioncats = $DB->get_records('question_categories', ['contextid' => $this->stalecoursecontext->id], 'id ASC');
        $this->assertCount(6, $questioncats);
        $topcat = reset($questioncats);
        $parentcat1 = next($questioncats);
        $childcat1 = next($questioncats);
        $parentcat2 = next($questioncats);
        $childcat2 = next($questioncats);
        $grandchildcat1 = next($questioncats);
        $this->assertEquals($topcat->id, $parentcat1->parent);
        $this->assertEquals($topcat->id, $parentcat2->parent);
        $this->assertEquals($parentcat1->id, $childcat1->parent);
        $this->assertEquals($parentcat2->id, $childcat2->parent);
        $this->assertEquals($childcat2->id, $grandchildcat1->parent);
        $questionids = $this->get_question_data(array_map(static fn($cat) => $cat->id, $questioncats));
        $this->assertCount(5, $questionids);
    }

    public function test_qbank_install() {
        global $DB;
        $this->resetAfterTest();
        $this->setup_pre_install_data();

        $task = new \mod_qbank\task\install();
        $task->execute();

        // Site context checks:

        $sitecontext = context_system::instance();
        $sitecontextcats = $DB->get_records('question_categories', ['contextid' => $sitecontext->id]);

        // Should be no site context question categories left, not even 'top'.
        $this->assertCount(0, $sitecontextcats);

        $sitemodinfo = get_fast_modinfo(get_site());
        $siteqbanks = $sitemodinfo->get_instances_of('qbank');

        // We should have 1 new module on the site course.
        $this->assertCount(1, $siteqbanks);
        $siteqbank = reset($siteqbanks);

        // Make doubly sure it got put into section 0 as these mod types are not rendered to the course page.
        $this->assertEquals(0, $siteqbank->sectionnum);

        // It should have our determined name.
        $this->assertEquals('System shared question bank', $siteqbank->name);
        $sitemodcontext = context_module::instance($siteqbank->get_course_module_record()->id);

        // The 3 question categories including 'top' should now be at the new module context with their order intact.
        $sitemodcats = $DB->get_records_select('question_categories',
            'parent <> 0 AND contextid = :contextid',
            ['contextid' => $sitemodcontext->id],
            'id ASC'
        );
        $this->assertCount(2, $sitemodcats);
        $topcat = question_get_top_category($sitemodcontext->id);
        $parentcat = reset($sitemodcats);
        $childcat = next($sitemodcats);
        $this->assertEquals($topcat->id, $parentcat->parent);
        $this->assertEquals($parentcat->id, $childcat->parent);

        // Course category context checks:

        // Make sure that the course category has no question categories, not even 'top'.
        $this->assertEquals(0, $DB->count_records('question_categories', ['contextid' => $this->coursecatcontext->id]));

        $courses = $DB->get_records('course', ['category' => $this->coursecatcontext->instanceid], 'id ASC');
        // We should have 2 courses in this category now, the original and the new one that holds our new mod instance.
        $this->assertCount(2, $courses);
        $newcourse = end($courses);
        $coursecat = $DB->get_record('course_categories', ['id' => $newcourse->category]);

        // Make sure the new course shortname is a unique name based on the category name and id.
        $this->assertEquals("{$coursecat->name}-{$coursecat->id}", $newcourse->shortname);

        // Make sure the new course fullname is based on the category name.
        $this->assertEquals("Shared teaching resources for category: {$coursecat->name}", $newcourse->fullname);

        $coursemodinfo = get_fast_modinfo($newcourse);
        $coursecatqbanks = $coursemodinfo->get_instances_of('qbank');

        // We should have 1 new module on this course.
        $this->assertCount(1, $coursecatqbanks);
        $coursecatqbank = reset($coursecatqbanks);

        // Make sure the new module name is what we expect.
        $this->assertEquals("{$coursecat->name} shared question bank", $coursecatqbank->name);

        $coursecatqcats = $DB->get_records('question_categories', ['contextid' => $coursecatqbank->context->id], 'parent ASC');

        // The 2 question categories should be moved to the module context now.
        $this->assertCount(2, $coursecatqcats);
        $topcat = reset($coursecatqcats);
        $parentcat = end($coursecatqcats);

        // Make sure the parent orders are correct.
        $this->assertEquals($topcat->id, $parentcat->parent);

        // Course context checks:

        // Make sure that the course has no more question categories, not even 'top'.
        $this->assertEquals(0, $DB->count_records('question_categories', ['contextid' => $this->coursecontext->id]));

        $coursemodinfo = get_fast_modinfo($this->coursecontext->instanceid);
        $course = $coursemodinfo->get_course();
        $courseqbanks = $coursemodinfo->get_instances_of('qbank');

        // We should have only 1 new mod instance in this course.
        $this->assertCount(1, $coursecatqbanks);

        // The module name should be what we expect.
        $courseqbank = reset($courseqbanks);
        $this->assertEquals("{$course->shortname} shared question bank", $courseqbank->name);

        // Make sure the question categories still exist and that we have a new top one at the new module context.
        $topcat = question_get_top_category($courseqbank->context->id);
        $courseqcats = $DB->get_records_select('question_categories',
            'parent <> 0 AND contextid = :contextid',
            ['contextid' => $courseqbank->context->id],
            'id ASC'
        );
        $parentcat = reset($courseqcats);
        $childcat = next($courseqcats);
        $this->assertEquals($topcat->id, $parentcat->parent);
        $this->assertEquals($parentcat->id, $childcat->parent);

        // Stale course context checks:

        // Make sure the stale course has no categories attached to it anymore and the questions were removed.
        $this->assertFalse($DB->record_exists('question_categories', ['contextid' => $this->stalecoursecontext->id]));
        foreach ($this->stalequestions as $stalequestion) {
            $this->assertFalse($DB->record_exists('question', ['id' => $stalequestion->id]));
        }

        // Quiz module checks:

        // Make sure the 3 categories at quiz context, including 'top' have not been touched.
        $quizcategories = $DB->get_records('question_categories', ['contextid' => $this->quizcontext->id]);
        $this->assertCount(3, $quizcategories);
        $questions = $this->get_question_data(array_map(static fn($cat) => $cat->id, $quizcategories));
        $this->assertCount(2, $questions);
    }
}
