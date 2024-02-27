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
 * Contain the logic for the add random question modal.
 *
 * @module     mod_quiz/add_question_modal
 * @copyright  2023 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import * as Fragment from 'core/fragment';
import {getString} from 'core/str';
import AutoComplete from 'core/form-autocomplete';

export default class AddQuestionModal extends Modal {
    configure(modalConfig) {
        // Add question modals are always large.
        modalConfig.large = true;

        // Always show on creation.
        modalConfig.show = true;
        modalConfig.removeOnClose = true;

        // Apply question modal configuration.
        this.setContextId(modalConfig.contextId);
        this.setAddOnPageId(modalConfig.addOnPage);

        // Store the quiz module id for when we need to POST to the quiz.
        // This is because the URL cmid param will change during filter operations as we will be in another bank context.
        this.quizModId = modalConfig.quizModId;
        this.bankModId = modalConfig.bankModId;

        // Apply standard configuration.
        super.configure(modalConfig);
    }

    constructor(root) {
        super(root);

        this.contextId = null;
        this.addOnPageId = null;
    }

    /**
     * Save the Moodle context id that the question bank is being
     * rendered in.
     *
     * @method setContextId
     * @param {Number} id
     */
    setContextId(id) {
        this.contextId = id;
    }

    /**
     * Retrieve the saved Moodle context id.
     *
     * @method getContextId
     * @return {Number}
     */
    getContextId() {
        return this.contextId;
    }

    /**
     * Set the id of the page that the question should be added to
     * when the user clicks the add to quiz link.
     *
     * @method setAddOnPageId
     * @param {Number} id
     */
    setAddOnPageId(id) {
        this.addOnPageId = id;
    }

    /**
     * Returns the saved page id for the question to be added to.
     *
     * @method getAddOnPageId
     * @return {Number}
     */
    getAddOnPageId() {
        return this.addOnPageId;
    }

    /**
     * @param {String} Selector for the original select element.
     * @return {Promise} Modal.
     */
    async handleSwitchBankContentReload(Selector) {
        this.setTitle(getString('selectquestionbank', 'mod_quiz'));
        this.setBody(
            Fragment.loadFragment(
                'mod_quiz',
                'switch_question_bank',
                this.getContextId(),
                {
                    'quizcmid': this.quizModId,
                    'bankmodid': this.bankModId,
                })
        );
        const placeholder = await getString('searchbyname', 'mod_quiz').then((str) => { return str;});
        await this.getBodyPromise();
        await AutoComplete.enhance(
            Selector,
            false,
            '',
            placeholder,
            false,
            true,
            '',
            true
        );

        return this;
    }
}
