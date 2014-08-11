YUI.add('moodle-mod_quiz-toolboxes', function (Y, NAME) {

/**
 * Resource and activity toolbox class.
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a course in editing mode.
 *
 * @module moodle-course-toolboxes
 * @namespace M.course.toolboxes
 */

// The CSS classes we use.
    var CSS = {
        ACTIVITYINSTANCE : 'activityinstance',
        AVAILABILITYINFODIV : 'div.availabilityinfo',
        CONTENTWITHOUTLINK : 'contentwithoutlink',
        CONDITIONALHIDDEN : 'conditionalhidden',
        DIMCLASS : 'dimmed',
        DIMMEDTEXT : 'dimmed_text',
        EDITINSTRUCTIONS : 'editinstructions',
        EDITINGMAXMARK: 'editor_displayed',
        HIDE : 'hide',
        JOIN: 'page_join',
        MODINDENTCOUNT : 'mod-indent-',
        MODINDENTHUGE : 'mod-indent-huge',
        MODULEIDPREFIX : 'slot-',
        PAGE: 'page',
        SECTIONHIDDENCLASS : 'hidden',
        SECTIONIDPREFIX : 'section-',
        SLOT : 'slot',
        SHOW : 'editing_show',
        TITLEEDITOR : 'titleeditor'
    },
    // The CSS selectors we use.
    SELECTOR = {
        ACTIONAREA: '.actions',
        ACTIONLINKTEXT : '.actionlinktext',
        ACTIVITYACTION : 'a.cm-edit-action[data-action], a.editing_maxmark',
        ACTIVITYFORM : '.' + CSS.ACTIVITYINSTANCE + ' form',
        ACTIVITYICON : 'img.activityicon',
        ACTIVITYINSTANCE : '.' + CSS.ACTIVITYINSTANCE,
        ACTIVITYLINK: '.' + CSS.ACTIVITYINSTANCE + ' > a',
        ACTIVITYLI : 'li.activity',
        ACTIVITYMAXMARK : 'input[name=maxmark]',
        COMMANDSPAN : '.commands',
        CONTENTAFTERLINK : 'div.contentafterlink',
        CONTENTWITHOUTLINK : 'div.contentwithoutlink',
        EDITMAXMARK: 'a.editing_maxmark',
        HIDE : 'a.editing_hide',
        HIGHLIGHT : 'a.editing_highlight',
        INSTANCENAME : 'span.instancename',
        INSTANCEMAXMARK : 'span.instancemaxmark',
        MODINDENTDIV : '.mod-indent',
        MODINDENTOUTER : '.mod-indent-outer',
        PAGECONTENT : 'div#page-content',
        PAGELI : 'li.page',
        SECTIONUL : 'ul.section',
        SHOW : 'a.'+CSS.SHOW,
        SHOWHIDE : 'a.editing_showhide',
        SLOTLI : 'li.slot',
        SLOTNUMBER : 'span.slotnumber'
    },
    INDENTLIMITS = {
        MIN: 0,
        MAX: 16
    },
    CONSTANTS =  {
            PAGENUMBERPREFIX : 'Page '
        },
    BODY = Y.one(document.body);

// Setup the basic namespace.
M.mod_quiz = M.mod_quiz || {};

/**
 * The toolbox class is a generic class which should never be directly
 * instantiated. Please extend it instead.
 *
 * @class toolbox
 * @constructor
 * @protected
 * @extends Base
 */
var TOOLBOX = function() {
    TOOLBOX.superclass.constructor.apply(this, arguments);
};

Y.extend(TOOLBOX, Y.Base, {
    /**
     * Send a request using the REST API
     *
     * @method send_request
     * @param {Object} data The data to submit with the AJAX request
     * @param {Node} [statusspinner] A statusspinner which may contain a section loader
     * @param {Function} success_callback The callback to use on success
     * @param {Object} [optionalconfig] Any additional configuration to submit
     * @chainable
     */
    send_request: function(data, statusspinner, success_callback, optionalconfig) {
        // Default data structure
        if (!data) {
            data = {};
        }
        // Handle any variables which we must pass back through to
        var pageparams = this.get('config').pageparams,
            varname;
        for (varname in pageparams) {
            data[varname] = pageparams[varname];
        }

        data.sesskey = M.cfg.sesskey;
        data.courseid = this.get('courseid');
        data.quizid = this.get('quizid');

        var uri = M.cfg.wwwroot + this.get('ajaxurl');

        // Define the configuration to send with the request
        var responsetext = [];
        var config = {
            method: 'POST',
            data: data,
            on: {
                success: function(tid, response) {
                    try {
                        responsetext = Y.JSON.parse(response.responseText);
                        if (responsetext.error) {
                            new M.core.ajaxException(responsetext);
                        }
                    } catch (e) {}

                    // Run the callback if we have one.
                    if (success_callback) {
                        Y.bind(success_callback, this, responsetext)();
                    }

                    if (statusspinner) {
                        window.setTimeout(function() {
                            statusspinner.hide();
                        }, 400);
                    }
                },
                failure: function(tid, response) {
                    if (statusspinner) {
                        statusspinner.hide();
                    }
                    new M.core.ajaxException(response);
                }
            },
            context: this
        };

        // Apply optional config
        if (optionalconfig) {
            for (varname in optionalconfig) {
                config[varname] = optionalconfig[varname];
            }
        }

        if (statusspinner) {
            statusspinner.show();
        }

        // Send the request
        Y.io(uri, config);
        return this;
    }
},
{
    NAME: 'mod_quiz-toolbox',
    ATTRS: {
        /**
         * The ID of the Moodle Course being edited.
         *
         * @attribute courseid
         * @default 0
         * @type Number
         */
        courseid: {
            'value': 0
        },

        /**
         * The Moodle course format.
         *
         * @attribute format
         * @default 'topics'
         * @type String
         */
        quizid: {
            'value': 0
        },
        /**
         * The URL to use when submitting requests.
         * @attribute ajaxurl
         * @default null
         * @type String
         */
        ajaxurl: {
            'value': null
        },
        /**
         * Any additional configuration passed when creating the instance.
         *
         * @attribute config
         * @default {}
         * @type Object
         */
        config: {
            'value': {}
        }
    }
}
);

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
 * when viewing a course in editing mode.
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
     * An Array of events added when editing a max mark field.
     * These should all be detached when editing is complete.
     *
     * @property editmaxmarkevents
     * @protected
     * @type Array
     * @protected
     */
    editmaxmarkevents: [],
    
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
        BODY.delegate('key', this.handle_data_action, 'down:enter', SELECTOR.ACTIVITYACTION, this);
        Y.delegate('click', this.handle_data_action, BODY, SELECTOR.ACTIVITYACTION, this);
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
            case 'editmaxmark':
                // The user wishes to edit the maxmark of the resource.
                this.edit_maxmark(ev, node, activity, action);
                break;
            case 'delete':
                // The user is deleting the activity.
                this.delete_with_confirmation(ev, node, activity, action);
                break;
            case 'joinpage':
                // The user wishes to edit the maxmark of the resource.
                this.join_page(ev, node, activity, action);
                break;
            default:
                // Nothing to do here!
                break;
        }
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
     * @chainable
     */
    delete_with_confirmation: function(ev, button, activity) {
        // Prevent the default button action
        ev.preventDefault();

        // Get the element we're working on
        var element   = activity,
            // Create confirm string (different if element has or does not have name)
            confirmstring = '',
            qtypename = M.util.get_string('pluginname',
                        'qtype_' + element.getAttribute('class').match(/qtype_([^\s]*)/)[1]);
        confirmstring = M.util.get_string('confirmremovequestion', 'quiz', qtypename);

        // Create the confirmation dialogue.
        var confirm = new M.core.confirm({
            question: confirmstring,
            modal: true
        });

        // If it is confirmed.
        confirm.on('complete-yes', function() {

            // Actually remove the element.
            element.remove();
            var data = {
                'class': 'resource',
                'action': 'DELETE',
                'id': Y.Moodle.mod_quiz.util.slot.getId(element)
            };
            this.send_request(data);
            if (M.core.actionmenu && M.core.actionmenu.instance) {
                M.core.actionmenu.instance.hideMenu();
            }

        }, this);

        return this;
    },


    /**
     * Edit the maxmark for the resource
     *
     * @protected
     * @method edit_maxmark
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @param {String} action The action that has been requested.
     * @return Boolean
     */
    edit_maxmark : function(ev, button, activity) {
        // Get the element we're working on
        var activityid = Y.Moodle.mod_quiz.util.slot.getId(activity),
            instancemaxmark  = activity.one(SELECTOR.INSTANCEMAXMARK),
            instance = activity.one(SELECTOR.ACTIVITYINSTANCE),
            currentmaxmark = instancemaxmark.get('firstChild'),
            oldmaxmark = currentmaxmark.get('data'),
            maxmarktext = oldmaxmark,
            thisevent,
            anchor = instancemaxmark,// Grab the anchor so that we can swap it with the edit form.
            data = {
                'class'   : 'resource',
                'field'   : 'getmaxmark',
                'id'      : activityid
            };

        // Prevent the default actions.
        ev.preventDefault();

        this.send_request(data, null, function(response) {
            if (M.core.actionmenu && M.core.actionmenu.instance) {
                M.core.actionmenu.instance.hideMenu();
            }

            // Try to retrieve the existing string from the server
            if (response.instancemaxmark) {
                maxmarktext = response.instancemaxmark;
            }

            // Create the editor and submit button
            var editform = Y.Node.create('<form action="#" />');
            var editinstructions = Y.Node.create('<span class="'+CSS.EDITINSTRUCTIONS+'" id="id_editinstructions" />')
                .set('innerHTML', M.util.get_string('edittitleinstructions', 'moodle'));
            var editor = Y.Node.create('<input name="maxmark" type="text" class="'+CSS.TITLEEDITOR+'" />').setAttrs({
                'value' : maxmarktext,
                'autocomplete' : 'off',
                'aria-describedby' : 'id_editinstructions',
                'maxLength' : '4'
            });

            // Clear the existing content and put the editor in
            editform.appendChild(editor);
            editform.setData('anchor', anchor);
            instance.insert(editinstructions, 'before');
            anchor.replace(editform);

            // Force the editing instruction to match the mod-indent position.
            var padside = 'left';
            if (right_to_left()) {
                padside = 'right';
            }

            // We hide various components whilst editing:
            activity.addClass(CSS.EDITINGMAXMARK);

            // Focus and select the editor text
            editor.focus().select();

            // Cancel the edit if we lose focus or the escape key is pressed.
            thisevent = editor.on('blur', this.edit_maxmark_cancel, this, activity, false);
            this.editmaxmarkevents.push(thisevent);
            thisevent = editor.on('key', this.edit_maxmark_cancel, 'esc', this, activity, true);
            this.editmaxmarkevents.push(thisevent);

            // Handle form submission.
            thisevent = editform.on('submit', this.edit_maxmark_submit, this, activity, oldmaxmark);
            this.editmaxmarkevents.push(thisevent);
        });
    },

    /**
     * Handles the submit event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_submit
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {String} originalmaxmark The original maxmark the activity or resource had.
     */
    edit_maxmark_submit : function(ev, activity, originalmaxmark) {
        // We don't actually want to submit anything
        ev.preventDefault();

        var newmaxmark = Y.Lang.trim(activity.one(SELECTOR.ACTIVITYFORM + ' ' + SELECTOR.ACTIVITYMAXMARK).get('value'));
        this.edit_maxmark_clear(activity);
        var spinner = this.add_spinner(activity);
        if (newmaxmark !== null && newmaxmark !== "" && newmaxmark !== originalmaxmark) {
            var data = {
                'class'   : 'resource',
                'field'   : 'updatemaxmark',
                'maxmark'   : newmaxmark,
                'id'      : Y.Moodle.mod_quiz.util.slot.getId(activity)
            };
            this.send_request(data, spinner, function(response) {
                if (response.instancemaxmark) {
                    activity.one(SELECTOR.INSTANCEMAXMARK).setContent(response.instancemaxmark);
                }
            });
        }
    },

    /**
     * Handles the cancel event when editing the activity or resources maxmark.
     *
     * @protected
     * @method edit_maxmark_cancel
     * @param {EventFacade} ev The event that triggered this.
     * @param {Node} activity The activity whose maxmark we are altering.
     * @param {Boolean} preventdefault If true we should prevent the default action from occuring.
     */
    edit_maxmark_cancel : function(ev, activity, preventdefault) {
        if (preventdefault) {
            ev.preventDefault();
        }
        this.edit_maxmark_clear(activity);
    },

    /**
     * Handles clearing the editing UI and returning things to the original state they were in.
     *
     * @protected
     * @method edit_maxmark_clear
     * @param {Node} activity  The activity whose maxmark we were altering.
     */
    
    edit_maxmark_clear : function(activity) {
      // Detach all listen events to prevent duplicate triggers
      new Y.EventHandle(this.editmaxmarkevents).detach();

      var editform = activity.one(SELECTOR.ACTIVITYFORM),
          instructions = activity.one('#id_editinstructions');
      if (editform) {
          editform.replace(editform.getData('anchor'));
      }
      if (instructions) {
          instructions.remove();
      }

      // Remove the editing class again to revert the display.
      activity.removeClass(CSS.EDITINGMAXMARK);

      // Refocus the link which was clicked originally so the user can continue using keyboard nav.
      Y.later(100, this, function() {
          activity.one(SELECTOR.EDITMAXMARK).focus();
      });
    },

    /**
     * Joins or separates the given slot with the page of the previous slot. Reorders the pages of
     * the other slots
     *
     * @protected
     * @method join_page
     * @param {EventFacade} ev The event that was fired.
     * @param {Node} button The button that triggered this action.
     * @param {Node} activity The activity node that this action will be performed on.
     * @chainable
     */
    join_page: function(ev, button, activity) {
        // Prevent the default button action
        ev.preventDefault();

        // Get the element we're working on
        var element   = activity;

        // If it is confirmed.

        var spinner = this.add_spinner(activity);
        // Actually remove the element.
//            element.remove();
        var slotnumber = 0;
        
//        Y.Moodle.mod_quiz.util.slot.getSlotNumber(element);
        var data = {
            'class': 'resource',
            'field': 'linkslottopage',
            'id':    slotnumber,
            'value': 1
        };
        
        slotnumber = activity.previous('li.activity').one('.slotnumber').get('text');
        if (slotnumber) {
            data.id = Number(slotnumber);
        }
        this.send_request(data, spinner, function(response) {
            if (response.slots) {
                this.repaginate_slots(response.slots);
////                activity.one(SELECTOR.INSTANCEMAXMARK).setContent(response.instancemaxmark);
            }
        });
//            this.send_request(data);
//            if (M.core.actionmenu && M.core.actionmenu.instance) {
//                M.core.actionmenu.instance.hideMenu();
//            }


        return this;
    },
    repaginate_slots: function(slots) {
        console.log(slots);
        this.slots = slots;
        console.log(this.slots);
//        for(var x=0;i<slots.length;x++){
        var slot;
        var section = Y.one(SELECTOR.PAGECONTENT+' '+SELECTOR.SECTIONUL);
//        var resources = section
        for(var key in slots){
            
            if(!slots.hasOwnProperty(key)){
                continue;
            }
            
            console.log(key);
            slot = slots[key];
            console.log(slot);
            
        }
        
        var activities = section.all(SELECTOR.ACTIVITYLI);
        activities.each(function(node) {
            console.log('activities: 1');
            console.log(this.slots);
            
            // What element is it? page/slot/link
            // what is the current slot?
            var type;
            var slot;
            if(node.hasClass(CSS.PAGE)){
               type = this.NODE_PAGE; 
               slot = node.next(SELECTOR.SLOTLI);
            } else if (node.hasClass(CSS.SLOT)){
                type = this.NODE_SLOT;  
                slot = node;
            } else if (node.hasClass(CSS.JOIN)){
                type = this.NODE_JOIN;
                slot = node.previous(SELECTOR.SLOTLI);
            }
            
            // getSlotnumber() Should be a method of util.slot
            var slotnumber = Number(slot.one(SELECTOR.SLOTNUMBER).get('text'));
            console.log('type = '+type);
            console.log('slotnumber = '+slotnumber);
            if(!type){
                return;
            }
            
            // Is it correct?
            if(!this.slots.hasOwnProperty(slotnumber)){
                // An error. We should handle this.
                return;
            }
            
            var slotdata = this.slots[slotnumber];
            console.log(slotdata);
            
            if(type == this.NODE_PAGE){
                // Get page number
                var pagenumber = this.getPageNumber(node);
                console.log('pagenumber = '+pagenumber);
                // Is the page number correct?
                
            }
//            var button;
//            if (node.one(SELECTOR.SHOW)) {
//                button = node.one(SELECTOR.SHOW);
//            } else {
//                button = node.one(SELECTOR.HIDE);
//            }
//            var activityid = Y.Moodle.mod_quiz.util.slot.getId(node);
//
//            // NOTE: resourcestotoggle is returned as a string instead
//            // of a Number so we must cast our activityid to a String.
//            if (Y.Array.indexOf(response.resourcestotoggle, "" + activityid) !== -1) {
//                M.mod_quiz.resource_toolbox.handle_resource_dim(button, node, action);
//            }
        }, this);
    },
    
    /**
     * Determines the page Number for the provided page.
     *
     * @method getId
     * @param page {Node} The page to find an ID for.
     * @return {Number|false} The ID of the page in question or false if no ID was found.
     */
    getPageNumber: function(page) {
        // We perform a simple substitution operation to get the ID.
        var number = page.get('text').replace(
                CONSTANTS.PAGENUMBERPREFIX, '');

        // Attempt to validate the ID.
        number = parseInt(number, 10);
        if (typeof number === 'number' && isFinite(number)) {
            return number;
        }
        return false;
    }
},
{
    NAME : 'mod_quiz-resource-toolbox',
    ATTRS : {
        courseid : {
            'value' : 0
        },
        quizid : {
            'value' : 0
        }
    }
});

M.mod_quiz.resource_toolbox = null;
M.mod_quiz.init_resource_toolbox = function(config) {
    M.mod_quiz.resource_toolbox = new RESOURCETOOLBOX(config);
    return M.mod_quiz.resource_toolbox;
};
/**
 * Resource and activity toolbox class.
 *
 * This class is responsible for managing AJAX interactions with activities and resources
 * when viewing a course in editing mode.
 *
 * @module moodle-mod_quiz-toolboxes
 * @namespace M.mod_quiz.toolboxes
 */

/**
 * Section toolbox class.
 *
 * This class is responsible for managing AJAX interactions with sections
 * when viewing a course in editing mode.
 *
 * @class section
 * @constructor
 * @extends M.mod_quiz.toolboxes.toolbox
 */
var SECTIONTOOLBOX = function() {
    SECTIONTOOLBOX.superclass.constructor.apply(this, arguments);
};

Y.extend(SECTIONTOOLBOX, TOOLBOX, {
    /**
     * Initialize the section toolboxes module.
     *
     * Updates all span.commands with relevant handlers and other required changes.
     *
     * @method initializer
     * @protected
     */
    initializer : function() {
        M.mod_quiz.quizbase.register_module(this);

        // Section Highlighting.
        Y.delegate('click', this.toggle_highlight, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.HIGHLIGHT, this);

        // Section Visibility.
        Y.delegate('click', this.toggle_hide_section, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.SHOWHIDE, this);
    },

    toggle_hide_section : function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Get the section we're working on.
        var section = e.target.ancestor(M.mod_quiz.format.get_section_selector(Y)),
            button = e.target.ancestor('a', true),
            hideicon = button.one('img'),

        // The value to submit
            value,

        // The text for strings and images. Also determines the icon to display.
            action,
            nextaction;

        if (!section.hasClass(CSS.SECTIONHIDDENCLASS)) {
            section.addClass(CSS.SECTIONHIDDENCLASS);
            value = 0;
            action = 'hide';
            nextaction = 'show';
        } else {
            section.removeClass(CSS.SECTIONHIDDENCLASS);
            value = 1;
            action = 'show';
            nextaction = 'hide';
        }

        var newstring = M.util.get_string(nextaction + 'fromothers', 'format_' + this.get('format'));
        hideicon.setAttrs({
            'alt' : newstring,
            'src'   : M.util.image_url('i/' + nextaction)
        });
        button.set('title', newstring);

        // Change the highlight status
        var data = {
            'class' : 'section',
            'field' : 'visible',
            'id'    : Y.Moodle.core_course.util.section.getId(section.ancestor(M.mod_quiz.edit.get_section_wrapper(Y), true)),
            'value' : value
        };

        var lightbox = M.util.add_lightbox(Y, section);
        lightbox.show();

        this.send_request(data, lightbox, function(response) {
            var activities = section.all(SELECTOR.ACTIVITYLI);
            activities.each(function(node) {
                var button;
                if (node.one(SELECTOR.SHOW)) {
                    button = node.one(SELECTOR.SHOW);
                } else {
                    button = node.one(SELECTOR.HIDE);
                }
                var activityid = Y.Moodle.mod_quiz.util.slot.getId(node);

                // NOTE: resourcestotoggle is returned as a string instead
                // of a Number so we must cast our activityid to a String.
                if (Y.Array.indexOf(response.resourcestotoggle, "" + activityid) !== -1) {
                    M.mod_quiz.resource_toolbox.handle_resource_dim(button, node, action);
                }
            }, this);
        });
    },

    /**
     * Toggle highlighting the current section.
     *
     * @method toggle_highlight
     * @param {EventFacade} e
     */
    toggle_highlight : function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Get the section we're working on.
        var section = e.target.ancestor(M.mod_quiz.edit.get_section_selector(Y));
        var button = e.target.ancestor('a', true);
        var buttonicon = button.one('img');

        // Determine whether the marker is currently set.
        var togglestatus = section.hasClass('current');
        var value = 0;

        // Set the current highlighted item text.
        var old_string = M.util.get_string('markthistopic', 'moodle');
        Y.one(SELECTOR.PAGECONTENT)
            .all(M.mod_quiz.edit.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT)
            .set('title', old_string);
        Y.one(SELECTOR.PAGECONTENT)
            .all(M.mod_quiz.edit.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT + ' img')
            .set('alt', old_string)
            .set('src', M.util.image_url('i/marker'));

        // Remove the highlighting from all sections.
        Y.one(SELECTOR.PAGECONTENT).all(M.mod_quiz.edit.get_section_selector(Y))
            .removeClass('current');

        // Then add it if required to the selected section.
        if (!togglestatus) {
            section.addClass('current');
            value = Y.Moodle.core_course.util.section.getId(section.ancestor(M.mod_quiz.edit.get_section_wrapper(Y), true));
            var new_string = M.util.get_string('markedthistopic', 'moodle');
            button
                .set('title', new_string);
            buttonicon
                .set('alt', new_string)
                .set('src', M.util.image_url('i/marked'));
        }

        // Change the highlight status.
        var data = {
            'class' : 'course',
            'field' : 'marker',
            'value' : value
        };
        var lightbox = M.util.add_lightbox(Y, section);
        lightbox.show();
        this.send_request(data, lightbox);
    }
},  {
    NAME : 'mod_quiz-section-toolbox',
    ATTRS : {
        courseid : {
            'value' : 0
        },
        quizid : {
            'value' : 0
        },
        format : {
            'value' : 'topics'
        }
    }
});

M.mod_quiz.init_section_toolbox = function(config) {
    return new SECTIONTOOLBOX(config);
};


}, '@VERSION@', {"requires": ["node", "base", "event-key", "node", "io", "moodle-mod_quiz-quizbase"]});
