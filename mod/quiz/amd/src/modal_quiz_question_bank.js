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
 * Contain the logic for the question bank modal.
 *
 * @module     mod_quiz/modal_quiz_question_bank
 * @copyright  2018 Ryan Wyllie <ryan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Modal from './add_question_modal';
import * as Fragment from 'core/fragment';
import * as FormChangeChecker from 'core_form/changechecker';
import * as ModalEvents from 'core/modal_events';

const SELECTORS = {
    ADD_TO_QUIZ_CONTAINER: 'td.addtoquizaction',
    ANCHOR: 'a[href]',
    PREVIEW_CONTAINER: 'td.previewquestionaction',
    ADD_QUESTIONS_FORM: 'form#questionsubmit',
    SORTERS: '.sorters',
    SWITCH_TO_OTHER_BANK: 'button[data-action="switch-question-bank"]',
    NEW_BANKMOD_ID: 'data-newmodid',
    BANK_SEARCH: '#searchbanks',
};

export default class ModalQuizQuestionBank extends Modal {
    static TYPE = 'mod_quiz-quiz-question-bank';

    /**
     * Create the question bank modal.
     *
     * @param {Number} contextId Current module context id.
     * @param {Number} bankModId Current question bank course module id.
     * @param {Number} quizModId Current quiz course module id.
     * @param {Array} courseOpenBanks list of question banks from the current course that can be shared.
     * @param {Array} allOpenBanks list of question banks from other courses that can be shared.
     * @param {Array} recentlyViewedBanks list of recently viewed question banks that can be shared.
     */
    static init(contextId, bankModId, quizModId, courseOpenBanks, allOpenBanks, recentlyViewedBanks) {
        const selector = '.menu [data-action="questionbank"]';
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest(selector);
            if (!trigger) {
                return;
            }
            e.preventDefault();

            ModalQuizQuestionBank.create({
                contextId,
                quizModId,
                bankModId,
                courseOpenBanks,
                allOpenBanks,
                recentlyViewedBanks,
                title: trigger.dataset.header,
                addOnPage: trigger.dataset.addonpage,
                templateContext: {
                    hidden: true,
                },
                large: true,
            });
        });
    }

    /**
     * Override the parent show function.
     *
     * Reload the body contents when the modal is shown. The current
     * window URL is used to inform the new content that should be
     * displayed.
     *
     * @method show
     * @return {void}
     */
    show() {
        this.reloadBodyContent(window.location.search);
        return super.show(this);
    }

    /**
     * Replaces the current body contents with a new version of the question
     * bank.
     *
     * The contents of the question bank are generated using the provided
     * query string.
     *
     * @method reloadBodyContent
     * @param {string} querystring URL encoded string.
     */
    reloadBodyContent(querystring) {
        // Load the question bank fragment to be displayed in the modal.
        this.setBody(Fragment.loadFragment(
            'mod_quiz',
            'quiz_question_bank',
            this.getContextId(),
            {
                querystring,
                quizmodid: this.getQuizModId(),
                bankmodid: this.getBankModId(),
            }
        ));
    }

    /**
     * Update the URL of the anchor element that the user clicked on to make
     * sure that the question is added to the correct page.
     *
     * @method handleAddToQuizEvent
     * @param {event} e A JavaScript event
     * @param {object} anchorElement The anchor element that was triggered
     */
    handleAddToQuizEvent(e, anchorElement) {
        // If the user clicks the plus icon to add the question to the page
        // directly then we need to intercept the click in order to adjust the
        // href and include the correct add on page id and cmid before the page is
        // redirected.
        const href = new URL(anchorElement.attr('href'));
        href.searchParams.set('addonpage', this.getAddOnPageId());
        href.searchParams.set('cmid', this.getQuizModId());
        anchorElement.attr('href', href);
    }

    /**
     * Set up all of the event handling for the modal.
     *
     * @method registerEventListeners
     */
    registerEventListeners() {
        // Apply parent event listeners.
        super.registerEventListeners(this);

        this.getModal().on('submit', SELECTORS.ADD_QUESTIONS_FORM, (e) => {
            // If the user clicks on the "Add selected questions to the quiz" button to add some questions to the page
            // then we need to intercept the submit in order to include the correct "add on page id"
            // and the quizmod id before the form is submitted.
            const formElement = $(e.currentTarget);

            $('<input />').attr('type', 'hidden')
                .attr('name', "addonpage")
                .attr('value', this.getAddOnPageId())
                .appendTo(formElement);

            $('<input />').attr('type', 'hidden')
                .attr('name', "cmid")
                .attr('value', this.getQuizModId())
                .appendTo(formElement);

            // We also need to set the form action to be our quizmod id.
            const actionUrl = new URL(formElement.attr('action'));
            actionUrl.searchParams.set('cmid', this.getQuizModId());
            formElement.attr('action', actionUrl.toString());
        });

        this.getModal().on('click', SELECTORS.SWITCH_TO_OTHER_BANK, () => {
            this.handleSwitchBankContentReload(SELECTORS.BANK_SEARCH).then(function (ModalQuizQuestionBank) {
                $(SELECTORS.BANK_SEARCH).on('change',(e) => {
                        const bankModId = $(e.currentTarget).val();
                        if (bankModId > 0) {
                            ModalQuizQuestionBank.setBankModId(bankModId);
                            ModalQuizQuestionBank.reloadBodyContent(window.location.search);
                        }
                    });
                }
            );
        });

        this.getModal().on('click', SELECTORS.ANCHOR, (e) => {
            const anchorElement = $(e.currentTarget);

            // If the anchor element was the add to quiz link.
            if (anchorElement.closest(SELECTORS.ADD_TO_QUIZ_CONTAINER).length) {
                this.handleAddToQuizEvent(e, anchorElement);
                return;
            }

            // If the anchor element was a preview question link.
            if (anchorElement.closest(SELECTORS.PREVIEW_CONTAINER).length) {
                return;
            }

            // Sorting links have their own handler.
            if (anchorElement.closest(SELECTORS.SORTERS).length) {
                return;
            }

            if (anchorElement.closest('a[' + SELECTORS.NEW_BANKMOD_ID + ']').length) {
                this.setBankModId(anchorElement.attr(SELECTORS.NEW_BANKMOD_ID));
            }

            // Anything else means reload the pop-up contents.
            e.preventDefault();
            this.reloadBodyContent(anchorElement.prop('search'));
        });

        // Disable the form change checker when the body is rendered.
        this.getRoot().on(ModalEvents.bodyRendered, () => {
            // Make sure the form change checker is disabled otherwise it'll stop the user from navigating away from the
            // page once the modal is hidden.
            FormChangeChecker.disableAllChecks();
        });
    }
}

ModalQuizQuestionBank.registerModalType();
