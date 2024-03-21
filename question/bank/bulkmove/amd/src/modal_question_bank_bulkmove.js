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
 * Contain the logic for the bulkmove questions modal.
 *
 * @module     qbank_bulkmove/modal_question_bank_bulkmove
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import * as Fragment from 'core/fragment';
import {getString} from 'core/str';
import AutoComplete from 'core/form-autocomplete';
import {submitMoveQuestions} from 'core_question/repository';
import Templates from 'core/templates';
import Notification from 'core/notification';


export default class ModalQuestionBankBulkmove extends Modal {
    static TYPE = 'qbank_bulkmove/bulkmove';

    static SELECTORS = {
        SAVE_BUTTON: '[data-action="bulkmovesave"]',
        SELECTED_QUESTIONS: 'table#categoryquestions input[id^="checkq"]',
        SEARCH_BANK: '#searchbanks',
        SEARCH_CATEGORY: '#searchcategories',
        CATEGORY_OPTIONS: '#searchcategories option',
        BANK_OPTIONS: '#searchbanks option',
        CATEGORY_ENHANCED_INPUT: '.search-categories input',
        ORIGINAL_SELECTS: 'select.bulk-move',
        CATEGORY_WARNING: '#searchcatwarning',
        CATEGORY_SUGGESTION: '.search-categories span.form-autocomplete-downarrow',
        CONFIRM_BUTTON: '.bulk-move-footer button[data-action="save"]',
        CANCEL_BUTTON: '.bulk-move-footer button[data-action="cancel"]'
    };

    static init(contextId, categoryId) {
        document.addEventListener('click', (e) => {
            const trigger = e.target;
            if (trigger.className === 'dropdown-item' && trigger.getAttribute('name') === 'move') {
                e.preventDefault();
                ModalQuestionBankBulkmove.create({
                    contextId,
                    title: getString('bulkmoveheader', 'qbank_bulkmove'),
                    show: true,
                    categoryId: categoryId,
                });
            }
        });
    }

    configure(modalConfig) {
        this.setContextId(modalConfig.contextId);
        this.setTargetBankContextId(modalConfig.contextId);
        this.initSelectedCategoryId(modalConfig.categoryId);
        modalConfig.removeOnClose = true;
        super.configure(modalConfig);
    }

    /**
     * @param {integer} contextId
     */
    setContextId(contextId) {
        this.contextId = contextId;
    }

    /**
     * @return {integer} contextId
     */
    getContextId() {
        return this.contextId;
    }

    /**
     * @param {integer} categoryId
     */
    setTargetCategoryId(categoryId) {
        this.targetCategoryId = categoryId;
    }

    /**
     * @return {integer} categoryId
     */
    getTargetCategoryId() {
        return this.targetCategoryId;
    }

    /**
     * Initialise the category select based on the data passed to the JS or if a filter is applied in the url.
     * @param {integer} categoryId
     */
    initSelectedCategoryId(categoryId) {
        const filter = new URLSearchParams(window.location.href).get('filter');
        if (filter) {
            const filteredCategoryId = JSON.parse(filter)?.category.values[0];
            this.currentCategoryId = filteredCategoryId > 0 ? filteredCategoryId : null;
            this.targetCategoryId = filteredCategoryId;
            return;
        }
        this.currentCategoryId = categoryId;
        this.targetCategoryId = categoryId;
    }

    /**
     * @return {integer} currentCategoryId
     */
    getCurrentCategoryId() {
        return this.currentCategoryId;
    }

    /**
     * @param {integer} targetBankContextId
     */
    setTargetBankContextId(targetBankContextId) {
        this.targetBankContextId = targetBankContextId ? targetBankContextId : null;
    }

    /**
     * @return {integer} targetBankContextId
     */
    getTargetBankContextId() {
        return this.targetBankContextId;
    }

    /**
     * @param {array} data with key as categoryid and value as questionbank contextid
     */
    setMappedData(data) {
        this.mappedData = data;
    }

    /**
     * @return {Array} mappedData
     */
    getMappedData() {
        return this.mappedData;
    }

    show() {
        void this.display(this.getContextId(), this.getCurrentCategoryId());
        return super.show();
    }

    /**
     * @param {integer} currentBankContextId
     * @param {integer} currentCategoryId
     */
    async display(currentBankContextId, currentCategoryId) {
        this.bodyPromise = await Fragment.loadFragment(
            'qbank_bulkmove',
            'bulk_move',
            currentBankContextId,
            {
                'categoryid': currentCategoryId,
            }
        );

        await this.setBody(this.bodyPromise);
        await this.enhanceSelects(document.querySelectorAll(ModalQuestionBankBulkmove.SELECTORS.ORIGINAL_SELECTS));
        this.registerEnhancedEventListeners();
        this.mapData();
        this.updateSaveButtonState();
    }

    /**
     * Register event listeners on the enhanced selects. Must be done after they have been enhanced.
     */
    registerEnhancedEventListeners() {
        document.querySelector(ModalQuestionBankBulkmove.SELECTORS.SEARCH_CATEGORY).addEventListener("change", (e) => {
            const targetCategoryId = e.currentTarget.value;
            this.setTargetCategoryId(targetCategoryId);
            this.rebuildOptions(this.getTargetBankContextId(), targetCategoryId);
            this.updateSaveButtonState();
        });

        document.querySelector(ModalQuestionBankBulkmove.SELECTORS.SEARCH_BANK).addEventListener("change", (e) => {
            const selectedBankContextId = e.currentTarget.value;
            this.setTargetBankContextId(selectedBankContextId);
            this.rebuildOptions(selectedBankContextId, this.getTargetCategoryId());
        });

        this.getModal().on("click", ModalQuestionBankBulkmove.SELECTORS.SAVE_BUTTON, (e) => {
            e.preventDefault();
            this.displayConfirm();
        });
    }

    /**
     * Set a map, so we can determine which bank belongs to which category.
     */
    mapData() {
        const customSelectCategoryOptions = document.querySelectorAll(ModalQuestionBankBulkmove.SELECTORS.CATEGORY_OPTIONS);

        if (customSelectCategoryOptions.length === 0) {
            return;
        }

        const mappedData = [];

        customSelectCategoryOptions.forEach((option) => {
            mappedData[option.value] = option.dataset.bankContextid;
        });

        this.setMappedData(mappedData);
    }

    displayConfirm() {
        this.setTitle(getString('confirm', 'core'));
        this.setBody(getString('confirmmove', 'qbank_bulkmove'));
        if (!this.hasFooterContent()) {
            void this.configureFooter();
        } else {
            this.showFooter();
        }
    }

    /**
     * @return {Promise<void>}
     */
    async configureFooter() {
        this.setFooter(Templates.render('qbank_bulkmove/bulk_move_footer', {}));
        await this.getFooterPromise();

        document.querySelector(ModalQuestionBankBulkmove.SELECTORS.CONFIRM_BUTTON).addEventListener("click", (e) => {
            e.preventDefault();
            this.confirmed(this.getTargetBankContextId(), this.getTargetCategoryId());
        });

        document.querySelector(ModalQuestionBankBulkmove.SELECTORS.CANCEL_BUTTON).addEventListener("click", (e) => {
            e.preventDefault();
            this.setTitle(getString('bulkmoveheader', 'qbank_bulkmove'));
            this.setBodyContent(Templates.renderForPromise('core/loading',{}));
            this.hideFooter();
            this.display(this.getTargetBankContextId(), this.getTargetCategoryId());
        });
    }

    /**
     * Dynamically update all enhanced selects options based on what is selected.
     *
     * @param {integer} selectedBankContextId
     * @param {integer} selectedCategoryId
     */
    rebuildOptions(selectedBankContextId, selectedCategoryId) {
        const mappedData = this.getMappedData();
        const customSelectCategoryOptions = document.querySelectorAll(ModalQuestionBankBulkmove.SELECTORS.CATEGORY_OPTIONS);

        // Disable the category selector if no bank selected.
        if (!selectedBankContextId) {
            this.updateCategorySelector(false);
        } else {
            // Mark to be disabled all the categories not belonging to the selected bank.
            // This will then be handled by the enhanced selects event handlers.
            customSelectCategoryOptions.forEach((option) => {
                if (option.dataset.bankContextid != selectedBankContextId) {
                    option.dataset.enabled = 'disabled';
                } else {
                    option.dataset.enabled = 'enabled';
                }
            });
            this.updateCategorySelector(true);
        }

        // De-select the selected category if it does not belong to the selected bank.
        if (selectedCategoryId && selectedBankContextId && mappedData[selectedCategoryId] != selectedBankContextId) {
            const selectedCategoryElement = document.querySelector(
                '.search-categories span[role="option"][data-value="' + selectedCategoryId + '"]'
            );
            selectedCategoryElement.click();
        }
    }

    /**
     * @param {boolean} toEnable
     */
    updateCategorySelector(toEnable) {
        const warning = document.querySelector(ModalQuestionBankBulkmove.SELECTORS.CATEGORY_WARNING);
        const enhancedInput = document.querySelector(ModalQuestionBankBulkmove.SELECTORS.CATEGORY_ENHANCED_INPUT);
        const suggestionButton = document.querySelector(ModalQuestionBankBulkmove.SELECTORS.CATEGORY_SUGGESTION);

        if (toEnable) {
            warning.classList.add('d-none');
            enhancedInput.removeAttribute('disabled');
            suggestionButton.classList.remove('d-none');
        } else {
            warning.classList.remove('d-none');
            enhancedInput.setAttribute('disabled', 'disabled');
            suggestionButton.classList.add('d-none');
        }
    }

    /**
     * Disable the button if the selected category is the same as the one the questions already belong to. Enable it otherwise.
     */
    updateSaveButtonState() {
        const saveButton = document.querySelector(ModalQuestionBankBulkmove.SELECTORS.SAVE_BUTTON);
        const targetCategoryId = this.getTargetCategoryId();

        if (targetCategoryId && targetCategoryId != this.getCurrentCategoryId()) {
            saveButton.removeAttribute('disabled');
        } else {
            saveButton.setAttribute('disabled', 'disabled');
        }
    }

    /**
     * @param {integer} targetContextId
     * @param {integer} targetCategoryId
     * @return {Promise<void>}
     */
    async confirmed(targetContextId, targetCategoryId) {
        await this.setBody(Templates.render('core/loading', {}));
        const qelements = document.querySelectorAll(ModalQuestionBankBulkmove.SELECTORS.SELECTED_QUESTIONS);
        const questionids = [];
        qelements.forEach((element) => {
            if (element.checked) {
                const name = element.getAttribute('name');
                questionids.push(name.substr(1, name.length));
            }
        });
        if (questionids.length === 0) {
            await Notification.exception('No questions selected');
        }

        try {
            window.location.href = await submitMoveQuestions(
                targetContextId,
                targetCategoryId,
                questionids.join(),
                window.location.href
            );
        } catch (error) {
            await Notification.exception(error);
        }
    }

    /**
     * @param {NodeList} selects Custom select elements to enhance.
     * @return {Promise<Promise[]>}
     */
    async enhanceSelects(selects) {
        const placeholder = await getString('searchbyname', 'mod_quiz').then((placeholder) => {
            return placeholder;
        });

        const enhanced = [];

        if (selects.length > 0) {
            for (let i = 0; i < selects.length; i++) {
                enhanced.push(AutoComplete.enhance(
                        selects.item(i),
                        false,
                        '',
                        placeholder,
                        false,
                        true,
                        '',
                        true
                    )
                );
            }

            return Promise.all(enhanced);
        }

        return Promise.reject('No selects to enhance');
    }
}
