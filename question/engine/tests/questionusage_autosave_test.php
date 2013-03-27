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
 * This file contains tests for the autosave code in the question_usage class.
 *
 * @package    moodlecore
 * @subpackage questionengine
 * @copyright  2013 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(dirname(__FILE__) . '/../lib.php');
require_once(dirname(__FILE__) . '/helpers.php');


/**
 * Unit tests for the autosave parts of the {@link question_usage} class.
 *
 * @copyright 2013 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_usage_autosave_test extends qbehaviour_walkthrough_test_base {

    public function test_autosave_then_display() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        $this->delete_quba();
    }

    public function test_autosave_then_autosave_different_data() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        // Process a second autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'third response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'third response');

        $this->delete_quba();
    }

    public function test_autosave_then_autosave_same_data() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        $stepid = $this->quba->get_question_attempt($this->slot)->get_last_step()->get_id();

        // Process a second autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Try to check it is really the same step
        $newstepid = $this->quba->get_question_attempt($this->slot)->get_last_step()->get_id();
        $this->assertEquals($stepid, $newstepid);

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        $this->delete_quba();
    }

    public function test_autosave_then_autosave_original_data() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        // Process a second autosave saving the original response.
        // This should remove the autosave step.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'first response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        $this->delete_quba();
    }

    public function test_autosave_then_real_save() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        // Now save for real a third response.
        $this->process_submission(array('answer' => 'third response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'third response');
    }

    public function test_autosave_then_real_save_same() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        // Now save for real of the same response.
        $this->process_submission(array('answer' => 'second response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');
    }

    public function test_autosave_then_submit() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $generator->create_question_category();
        $question = $generator->create_question('shortanswer', null,
                array('category' => $cat->id));

        // Start attempt at a shortanswer question.
        $q = question_bank::load_question($question->id);
        $this->start_attempt_at_question($q, 'deferredfeedback', 1);

        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_step_count(1);

        // Process a response and check the expected result.
        $this->process_submission(array('answer' => 'first response'));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(2);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'first response');

        // Process an autosave.
        $this->load_quba();
        $this->process_autosave(array('answer' => 'second response'));
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_step_count(3);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->load_quba();
        $this->render();
        $this->check_output_contains_text_input('answer', 'second response');

        // Now submit a third response.
        $this->process_submission(array('answer' => 'third response'));
        $this->quba->finish_all_questions();

        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_step_count(4);
        $this->save_quba();

        // Now check how that is re-displayed.
        $this->render();
        $this->check_output_contains_text_input('answer', 'third response', false);
    }

    public function test_autosave_and_save_concurrently() {
        // TODO
    }

}
