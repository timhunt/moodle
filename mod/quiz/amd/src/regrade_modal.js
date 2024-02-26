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
 * Regrade modal form is used to regrade or dryrun the attempts and questions.
 *
 * @module mod_quiz/regrade_modal
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Fragment from 'core/fragment';
import {getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Initialize and add the event for the regrade form.
 *
 * @param {Object} modal The modal object.
 */
const initRegradeForm = (modal) => {
    // Add the help text to the title of the modal.
    const helpText = document.querySelector('#id_regradelabel_label + div > a');
    modal.getTitle().append(helpText);
    const modalBody = modal.getBody()[0];
    // Initialize the modal body elements.
    const allAttemptsButton = modalBody.querySelector('#id_attempts_0');
    const selectedAttemptsButton = modalBody.querySelector('#id_attempts_1');
    const allQuestionsButton = modalBody.querySelector('#id_questions_0');
    const selectedQuestionsButton = modalBody.querySelector('#id_questions_1');
    const questionIdCheckboxes = modalBody.querySelectorAll('[id^="id_questionids_"]');
    // Check if user tick on select all in the table.
    const selectAll = document.getElementById('mod-quiz-report-overview-report-selectall-attempts');
    // Check if we have any checkbox checked in the table.
    const isSelectedAttemptsInTableChecked = document.querySelector('[id^="attemptid_"]:checked') !== null;

    // Set the behavior checked and disabled for the radio buttons in the modal.
    if (isSelectedAttemptsInTableChecked || selectAll.checked) {
        selectedAttemptsButton.checked = true;
    }
    if (allAttemptsButton.checked) {
        selectedAttemptsButton.disabled = true;
    }
    if (allQuestionsButton.checked) {
        questionIdCheckboxes.forEach((questionIdCheckbox) => {
            questionIdCheckbox.disabled = true;
        });
    }

    const regradeButton = modalBody.querySelector('#id_regradenow');
    const dryRun = modalBody.querySelector('#id_dryrun');
    // Add the change event listener to the modal form if any field changes.
    modalBody.querySelector('form').addEventListener('change', () => {
        transformForm(allQuestionsButton, selectedQuestionsButton, questionIdCheckboxes, regradeButton,
            dryRun, modalBody);
    });
    // Handle the click event for regradeButton and dryRun elements.
    regradeButton.addEventListener('click', (e) => handleClick(e, allAttemptsButton,
        selectedAttemptsButton, allQuestionsButton, selectedQuestionsButton, questionIdCheckboxes, modalBody));
    dryRun.addEventListener('click', (e) => handleClick(e, allAttemptsButton,
        selectedAttemptsButton, allQuestionsButton, selectedQuestionsButton, questionIdCheckboxes, modalBody));
};

/**
 * Controls all modifications to perform when any field changes.
 *
 * @param {Object} allQuestionsButton The all questions button.
 * @param {Object} selectedQuestionsButton The selected questions button.
 * @param {Object} questionIdCheckboxes The question id checkboxes.
 * @param {Object} regradeButton The regrade button.
 * @param {Object} dryRun The dry run button.
 * @param {Object} modalBody The modal body.
 */
const transformForm = (allQuestionsButton, selectedQuestionsButton, questionIdCheckboxes, regradeButton,
        dryRun, modalBody) => {
    // Check if any question checkbox is checked.
    let isChecked = modalBody.querySelector('[id^="id_questionids_"]:checked') !== null;

    // Set default values for regradeButton and dryRun.
    regradeButton.disabled = dryRun.disabled = false;

    // Disable the checkboxes if the all questions button is checked.
    if (allQuestionsButton.checked) {
        questionIdCheckboxes.forEach((questionIdCheckbox) => {
            questionIdCheckbox.disabled = true;
        });
    }
    // Enable and filter the checkboxes if the selected questions button is checked to get the list of questions.
    if (selectedQuestionsButton.checked) {
        questionIdCheckboxes.forEach((questionIdCheckbox) => {
            questionIdCheckbox.disabled = false;
        });
        if (!isChecked) {
            regradeButton.disabled = dryRun.disabled = true;
        }
    }
};

/**
 * Handles the click event for the regradeButton and dryRun elements.
 *
 * @param {Event} e The event object.
 * @param {Object} allAttemptsrButton The all attempts radio button.
 * @param {Object} selectedAttemptsrButton The selected attempts radio button.
 * @param {Object} allQuestionsrButton The all questions radio button.
 * @param {Object} selectedQuestionsrButton The selected radio questions button.
 * @param {Object} questionIdCheckboxes The question id checkboxes.
 * @param {Object} modalBody The modal body.
 */
const handleClick = (e, allAttemptsrButton, selectedAttemptsrButton, allQuestionsrButton,
        selectedQuestionsrButton, questionIdCheckboxes, modalBody) => {
    e.preventDefault();
    // Handle if all attempts and all questions are checked.
    if (allAttemptsrButton.checked && allQuestionsrButton.checked) {
        modalBody.querySelector('input[name="regradeall"]').value = true;
    }
    // Handle if selected attempts and all questions are checked.
    if (selectedAttemptsrButton.checked && allQuestionsrButton.checked) {
        modalBody.querySelector('input[name="regrade"]').value = true;
        appendAttemptCheckboxesId(modalBody);
    }
    // Handle if all attempts and selected questions are checked.
    if (allAttemptsrButton.checked && selectedQuestionsrButton.checked) {
        modalBody.querySelector('input[name="regradealllwithquestionselected"]').value = true;
        modalBody.querySelector('input[name="questionslots"]').value = getCheckedQuestionSlots(questionIdCheckboxes);
    }
    // Handle if selected attempts and selected questions are checked.
    if (selectedAttemptsrButton.checked && selectedQuestionsrButton.checked) {
        modalBody.querySelector('input[name="regradewithquestionselected"]').value = true;
        modalBody.querySelector('input[name="questionslots"]').value = getCheckedQuestionSlots(questionIdCheckboxes);
        appendAttemptCheckboxesId(modalBody);
    }
    // Handle if dryrun is checked.
    if (e.target.name === 'dryrun') {
        modalBody.querySelector('form input[name="dryrun"]').value = true;
    }
    modalBody.querySelector('form').submit();
};

/**
 * Append the attempt checkboxes id to the modal form.
 *
 * @param {Object} modalBody The modal body.
 */
const appendAttemptCheckboxesId = (modalBody) => {
    const selectedAttemptsInTable = document.querySelectorAll('[id^="attemptid_"]:checked');
    selectedAttemptsInTable.forEach((checkbox) => {
        checkbox.type = 'hidden';
        modalBody.querySelector('form').append(checkbox);
    });
};

/**
 * Get the list of question slots.
 *
 * @param {Object} questionIdCheckboxes The question id checkboxes.
 * @return {String} The list of question slots.
 */
const getCheckedQuestionSlots = (questionIdCheckboxes) => {
    let questionSlots = '';
    questionIdCheckboxes.forEach((questionIdCheckbox) => {
        if (questionIdCheckbox.checked) {
            const questionSlot = questionIdCheckbox.dataset.questionslot;
            questionSlots += (questionSlots.length > 0 ? ',' : '') + questionSlot;
        }
    });

    return questionSlots;
};

/**
 * Initialize the modal.
 *
 * @param {int} context The context id.
 */
export const init = (context) => {
    const regradeAttempts = document.getElementById('regradeattempts');
    if (regradeAttempts) {
        regradeAttempts.addEventListener('click', async(e) => {
            e.preventDefault();
            const questionIdsInTable = document.querySelector('#attemptsform input[name="questionids"]').value;
            const questionSlotsInTable = document.querySelector('#attemptsform input[name="questionslots"]').value;
            const reportId = document.querySelector('#attemptsform input[name="id"]').value;
            const questionMode = document.querySelector('#attemptsform input[name="mode"]').value;
            const formFragment = Fragment.loadFragment('mod_quiz', 'regrade_modal', context, {
                questionIdsInTable,
                questionSlotsInTable,
                reportId,
                questionMode
            });
            const modal = await Modal.create({
                title: getString('regrade', 'quiz_overview'),
                body: formFragment,
                isVerticallyCentered: true,
                removeOnClose: true,
                show: true,
            });
            modal.bodyPromise.then(function() {
                initRegradeForm(modal);
                return;
            }).catch(Notification.exception);
        });
    }
};
