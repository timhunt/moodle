/**
 * Resource and activity toolbox class.
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a quiz in editing mode.
 *
 * @module mod_quiz-resource-toolbox
 * @namespace M.mod_quiz.resource_toolbox
 */

/**
 * Resource and activity toolbox class.
 *
 * This is a class extending TOOLBOX containing code specific to resources
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a quiz in editing mode.
 *
 * @class resources
 * @constructor
 * @extends M.course.toolboxes.toolbox
 */
var RESOURCETOOLBOX = function() {
    RESOURCETOOLBOX.superclass.constructor.apply(this, arguments);
};

Y.extend(RESOURCETOOLBOX, TOOLBOX, {
    /**
     *
     */
    NODE_PAGE: 1,
    NODE_SLOT: 2,
    NODE_JOIN: 3,

    /**
     * Initialize the resource toolbox
     *
     * For each activity the commands are updated and a reference to the activity is attached.
     * This way it doesn't matter where the commands are going to called from they have a reference to the
     * activity that they relate to.
     * This is essential as some of the actions are displayed in an actionmenu which removes them from the
     * page flow.
     *
     * This function also creates a single event delegate to manage all AJAX actions for all activities on
     * the page.
     *
     * @method initializer
     * @protected
     */
    initializer: function() {
        M.mod_quiz.quizbase.register_module(this);
        Y.delegate('click', this.handle_data_action, BODY, SELECTOR.ACTIVITYACTION, this);
        Y.delegate('click', this.handle_data_action, BODY, SELECTOR.DEPENDENCY_LINK, this);
        document.body.addEventListener('core/inplace_editable:updated', this.handle_slot_mark_updated);
        this.initialise_select_multiple();
    },

    /**
     * Initialize the select multiple options
     *
     * Add actions to the buttons that enable multiple slots to be selected and managed at once.
     *
     * @method initialise_select_multiple
     * @protected
     */
    initialise_select_multiple: function() {
        // Click select multiple button to show the select all options.
        Y.one(SELECTOR.SELECTMULTIPLEBUTTON).on('click', function(e) {
            e.preventDefault();
            Y.one('body').addClass(CSS.SELECTMULTIPLE);
        });

        // Click cancel button to show the select all options.
        Y.one(SELECTOR.SELECTMULTIPLECANCELBUTTON).on('click', function(e) {
            e.preventDefault();
            Y.one('body').removeClass(CSS.SELECTMULTIPLE);
        });

        // Assign the delete method to the delete multiple button.
        Y.delegate('click', this.delete_multiple_action, BODY, SELECTOR.SELECTMULTIPLEDELETEBUTTON, this);
    },

    /**
     * Handles the delegation event. When this is fired someone has triggered an action.
     *
     * Note not all actions will result in an AJAX enhancement.
     *
     * @protected
     * @method handle_data_action
     * @param {EventFacade} ev The event that was triggered.
     * @returns {boolean}
     */
    handle_data_action: function(ev) {
        // We need to get the anchor element that triggered this event.
        var node = ev.target;
        if (!node.test('a')) {
            node = node.ancestor(SELECTOR.ACTIVITYACTION);
        }

        // From the anchor we can get both the activity (added during initialisation) and the action being
        // performed (added by the UI as a data attribute).
        var action = node.getData('action'),
            activity = node.ancestor(SELECTOR.ACTIVITYLI);

        if (!node.test('a') || !action || !activity) {
            // It wasn't a valid action node.
            return;
        }

        // Switch based upon the action and do the desired thing.
        switch (action) {
            case 'delete':
                // The user is deleting the activity.
                this.delete_with_confirmation(ev, node, activity, action);
                break;
            case 'addpagebreak':
            case 'removepagebreak':
                // The user is adding or removing a page break.
                this.update_page_break(ev, node, activity, action);
                break;
            case 'adddependency':
            case 'removedependency':
                // The user is adding or removing a dependency between questions.
                this.update_dependency(ev, node, activity, action);
                break;
            default:
                // Nothing to do here!
                break;
        }
    },

    /**
     * Event handler for when and inplace editable change has been saved.
     *
     * If it was the max mark for a slot, we need to get the new total, and update that.
     *
     * @param e the event we are handling.
     */
    handle_slot_mark_updated: function(e) {
        var editable = e.target.closest('.inplaceeditable');
        if (!editable || editable.dataset.itemtype !== 'slotmaxmark') {
            return; // Not one we need to handle.
        }

        var newTotal = editable.querySelector('[data-sum-marks]').dataset.sumMarks;
        document.querySelector('.mod_quiz_summarks').innerText = newTotal;
    },

    /**
     * Add a loading icon to the specified activity.
     *
     * The icon is added within the action area.
     *
     * @method add_spinner
     * @param {Node} activity The activity to add a loading icon to
     * @return {Node|null} The newly created icon, or null if the action area was not found.
     */
    add_spinner: function(activity) {
        var actionarea = activity.one(SELECTOR.ACTIONAREA);
        if (actionarea) {
            return M.util.add_spinner(Y, actionarea);
        }
        return null;
    },

    /**
     * Deletes the given activity or resource after confirmation.
     *
     * @protected
     * @method delete_with_confirmation
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     */
    delete_with_confirmation: function(ev, button, activity) {
        // Prevent the default button action.
        ev.preventDefault();

        // Get the element we're working on.
        var element = activity;
        // Create confirm string (different if element has or does not have name)
        var qtypename = M.util.get_string(
            'pluginname',
            'qtype_' + element.getAttribute('class').match(/qtype_([^\s]*)/)[1]
        );

        // Create the confirmation dialogue.
        require(['core/notification'], function(Notification) {
            Notification.saveCancelPromise(
                M.util.get_string('confirm', 'moodle'),
                M.util.get_string('confirmremovequestion', 'quiz', qtypename),
                M.util.get_string('yes', 'moodle')
            ).then(function() {
                var spinner = this.add_spinner(element);
                var data = {
                    'class': 'resource',
                    'action': 'DELETE',
                    'id': Y.Moodle.mod_quiz.util.slot.getId(element)
                };
                this.send_request(data, spinner, function(response) {
                    if (response.deleted) {
                        // Actually remove the element.
                        Y.Moodle.mod_quiz.util.slot.remove(element);
                        this.reorganise_edit_page();
                        if (M.core.actionmenu && M.core.actionmenu.instance) {
                            M.core.actionmenu.instance.hideMenu(ev);
                        }
                    }
                });

                return;
            }.bind(this)).catch(function() {
                // User cancelled.
            });
        }.bind(this));
    },

    /**
     * Finds the section that would become empty if we remove the selected slots.
     *
     * @protected
     * @method find_sections_that_would_become_empty
     * @returns {String} The name of the first section found
     */
    find_sections_that_would_become_empty: function() {
        var section;
        var sectionnodes = Y.all(SELECTOR.SECTIONLI);

        if (sectionnodes.size() > 1) {
            sectionnodes.some(function(node) {
                var sectionname = node.one(SELECTOR.INSTANCESECTION).getContent();
                var checked = node.all(SELECTOR.SELECTMULTIPLECHECKBOX + ':checked');
                var unchecked = node.all(SELECTOR.SELECTMULTIPLECHECKBOX + ':not(:checked)');

                if (!checked.isEmpty() && unchecked.isEmpty()) {
                    section = sectionname;
                }

                return section;
            });
        }

        return section;
    },

    /**
     * Takes care of what needs to happen when the user clicks on the delete multiple button.
     *
     * @protected
     * @method delete_multiple_action
     * @param {EventFacade} ev The event that was fired.
     */
    delete_multiple_action: function(ev) {
        var problemsection = this.find_sections_that_would_become_empty();

        if (typeof problemsection !== 'undefined') {
            require(['core/notification'], function(Notification) {
                Notification.alert(
                    M.util.get_string('cannotremoveslots', 'quiz'),
                    M.util.get_string('cannotremoveallsectionslots', 'quiz', problemsection)
                );
            });
        } else {
            this.delete_multiple_with_confirmation(ev);
        }
    },

    /**
     * Deletes the given activities or resources after confirmation.
     *
     * @protected
     * @method delete_multiple_with_confirmation
     * @param {EventFacade} ev The event that was fired.
     */
    delete_multiple_with_confirmation: function(ev) {
        ev.preventDefault();

        var ids = '';
        var slots = [];
        Y.all(SELECTOR.SELECTMULTIPLECHECKBOX + ':checked').each(function(node) {
            var slot = Y.Moodle.mod_quiz.util.slot.getSlotFromComponent(node);
            ids += ids === '' ? '' : ',';
            ids += Y.Moodle.mod_quiz.util.slot.getId(slot);
            slots.push(slot);
        });
        var element = Y.one('div.mod-quiz-edit-content');

        // Do nothing if no slots are selected.
        if (!slots || !slots.length) {
            return;
        }

        require(['core/notification'], function(Notification) {
            Notification.saveCancelPromise(
                M.util.get_string('confirm', 'moodle'),
                M.util.get_string('areyousureremoveselected', 'quiz'),
                M.util.get_string('yes', 'moodle')
            ).then(function() {
                var spinner = this.add_spinner(element);
                var data = {
                    'class': 'resource',
                    field: 'deletemultiple',
                    ids: ids
                };
                // Delete items on server.
                this.send_request(data, spinner, function(response) {
                    // Delete locally if deleted on server.
                    if (response.deleted) {
                        // Actually remove the element.
                        Y.all(SELECTOR.SELECTMULTIPLECHECKBOX + ':checked').each(function(node) {
                            Y.Moodle.mod_quiz.util.slot.remove(node.ancestor('li.activity'));
                        });
                        // Update the page numbers and sections.
                        this.reorganise_edit_page();

                        // Remove the select multiple options.
                        Y.one('body').removeClass(CSS.SELECTMULTIPLE);
                    }
                });

                return;
            }.bind(this)).catch(function() {
                // User cancelled.
            });
        }.bind(this));
    },

    /**
     * Joins or separates the given slot with the page of the previous slot. Reorders the pages of
     * the other slots
     *
     * @protected
     * @method update_page_break
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action, addpagebreak or removepagebreak.
     * @chainable
     */
    update_page_break: function(ev, button, activity, action) {
        // Prevent the default button action
        ev.preventDefault();

        var nextactivity = activity.next('li.activity.slot');
        var spinner = this.add_spinner(nextactivity);
        var value = action === 'removepagebreak' ? 1 : 2;

        var data = {
            'class': 'resource',
            'field': 'updatepagebreak',
            'id':    Y.Moodle.mod_quiz.util.slot.getId(nextactivity),
            'value': value
        };

        this.send_request(data, spinner, function(response) {
            if (response.slots) {
                if (action === 'addpagebreak') {
                    Y.Moodle.mod_quiz.util.page.add(activity);
                } else {
                    var page = activity.next(Y.Moodle.mod_quiz.util.page.SELECTORS.PAGE);
                    Y.Moodle.mod_quiz.util.page.remove(page, true);
                }
                this.reorganise_edit_page();
            }
        });

        return this;
    },

    /**
     * Updates a slot to either require the question in the previous slot to
     * have been answered, or not,
     *
     * @protected
     * @method update_page_break
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action, adddependency or removedependency.
     * @chainable
     */
    update_dependency: function(ev, button, activity, action) {
        // Prevent the default button action.
        ev.preventDefault();
        var spinner = this.add_spinner(activity);

        var data = {
            'class': 'resource',
            'field': 'updatedependency',
            'id':    Y.Moodle.mod_quiz.util.slot.getId(activity),
            'value': action === 'adddependency' ? 1 : 0
        };

        this.send_request(data, spinner, function(response) {
            if (response.hasOwnProperty('requireprevious')) {
                Y.Moodle.mod_quiz.util.slot.updateDependencyIcon(activity, response.requireprevious);
            }
        });

        return this;
    },

    /**
     * Reorganise the UI after every edit action.
     *
     * @protected
     * @method reorganise_edit_page
     */
    reorganise_edit_page: function() {
        Y.Moodle.mod_quiz.util.slot.reorderSlots();
        Y.Moodle.mod_quiz.util.slot.reorderPageBreaks();
        Y.Moodle.mod_quiz.util.page.reorderPages();
        Y.Moodle.mod_quiz.util.slot.updateOneSlotSections();
        Y.Moodle.mod_quiz.util.slot.updateAllDependencyIcons();
    },

    NAME: 'mod_quiz-resource-toolbox',
    ATTRS: {
        courseid: {
            'value': 0
        },
        quizid: {
            'value': 0
        }
    }

});

M.mod_quiz.resource_toolbox = null;
M.mod_quiz.init_resource_toolbox = function(config) {
    M.mod_quiz.resource_toolbox = new RESOURCETOOLBOX(config);
    return M.mod_quiz.resource_toolbox;
};
