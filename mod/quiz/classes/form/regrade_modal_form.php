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

namespace mod_quiz\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Regrade modal form.
 *
 * @package mod_quiz
 * @copyright 2023 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regrade_modal_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $this->_form->setAttributes([
            'action' => new \moodle_url('/mod/quiz/report.php'),
            'class' => 'regrade-modal-form',
            'method' => 'post',
            'id' => 'mod-quiz-regrade-modal-form',
        ]);
        $questionids = $this->_customdata['questionids'];
        $questionslots = $this->_customdata['questionslots'];
        $reportid = $this->_customdata['reportid'];
        $mode = $this->_customdata['mode'];

        // And hidden fields to get create a help button.
        $mform->addElement('text', 'regradelabel', get_string('reportregrade', 'mod_quiz'),  ['class' => 'hidden']);
        $mform->addHelpButton('regradelabel', 'regrade', 'quiz_overview');

        // Add radio buttons to the form.
        $radio = [];
        $radio[] = $mform->createElement('radio', 'attempts', null,
            get_string('regrade_allattempts', 'quiz_overview'), 0);
        $radio[] = $mform->createElement('radio', 'attempts', null,
            get_string('regrade_selectedattempts', 'quiz_overview'), 1);
        $mform->addGroup($radio, 'attempts', ' ', ' ', false, ['class' => 'attempt-radio-buttons']);
        $mform->setDefault('attempts', 0);
        $radio = [];
        $radio[] = $mform->createElement('radio', 'questions', null,
            get_string('regrade_allquestions', 'quiz_overview'), 0);
        $radio[] = $mform->createElement('radio', 'questions', null,
            get_string('regrade_selectedquestions', 'quiz_overview'), 1);
        $mform->addGroup($radio, 'questions', ' ', ' ', false, ['class' => 'question-radio-buttons']);
        $mform->setDefault('questions', 0);

        // Explode the data from the Fragment and put it into an array.
        $questionidsarray = explode(',', $questionids);
        $questionslotsarray = explode(',', $questionslots);

        // Add checkbox element to the form.
        $mform->addElement('html', \html_writer::start_tag('div', ['class' => 'questionlist']));
        foreach ($questionidsarray as $index => $questionid) {
            $checkboxname = "questionids_$questionid";
            $checkboxlabel = get_string('question', 'core') . ' ' . ($index + 1);
            $questionslot = $questionslotsarray[$index];

            // Add checkbox element to the form.
            $mform->addElement('advcheckbox', $checkboxname, $checkboxlabel, null, [
                'data-questionid' => $questionid,
                'data-questionslot' => $questionslot,
            ]);
        }
        $mform->addElement('html', \html_writer::end_tag('div'));

        // Add submit button to the form.
        $submitbutton = [];
        $submitbutton[] = $mform->createElement('submit', 'regradenow',
            get_string('regrade_regradenow', 'quiz_overview'));
        $submitbutton[] = $mform->createElement('submit', 'dryrun',
            get_string('regrade_dryrun', 'quiz_overview'));

        $mform->addGroup($submitbutton, 'button', ' ', ' ', false, ['class' => 'regrade-modal-submit-button']);

        $mform->addElement('hidden', 'id', $reportid);
        $mform->addElement('hidden', 'mode', $mode);
        $mform->addElement('hidden', 'questionslots', '');
        $mform->addElement('hidden', 'regradeall', '');
        $mform->addElement('hidden', 'regrade', '');
        $mform->addElement('hidden', 'regradealllwithquestionselected', '');
        $mform->addElement('hidden', 'regradewithquestionselected', '');
        $mform->addElement('hidden', 'dryrun', '');
        $mform->disable_form_change_checker();
    }
}
