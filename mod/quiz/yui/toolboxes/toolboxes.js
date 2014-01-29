YUI.add('moodle-mod_quiz-toolboxes', function(Y) {

    // The following properties contain common strings.
    // We separate them out here because when this JS is minified the content is less as
    // Variables get compacted to single/double characters and the full length of the string
    // exists only once.

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
        MODINDENTCOUNT : 'mod-indent-',
        MODINDENTHUGE : 'mod-indent-huge',
        MODULEIDPREFIX : 'module-',
        SECTIONHIDDENCLASS : 'hidden',
        SECTIONIDPREFIX : 'section-',
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
        ACTIVITYTITLE : 'input[name=maxmark]',
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
        SECTIONLI : 'li.section',
        SHOW : 'a.'+CSS.SHOW,
        SHOWHIDE : 'a.editing_showhide'
    },
    INDENTLIMITS = {
        MIN: 0,
        MAX: 16
    },
    BODY = Y.one(document.body);

    /**
     * The toolbox classes
     *
     * TOOLBOX is a generic class which should never be directly instantiated
     * RESOURCETOOLBOX is a class extending TOOLBOX containing code specific to resources
     * SECTIONTOOLBOX is a class extending TOOLBOX containing code specific to sections
     */
    var TOOLBOX = function() {
        TOOLBOX.superclass.constructor.apply(this, arguments);
    };

    Y.extend(TOOLBOX, Y.Base, {
        /**
         * Send a request using the REST API
         *
         * @method send_request
         * @param data The data to submit
         * @param statusspinner (optional) A statusspinner which may contain a section loader
         * @param {Function} success_callback The callback to use on success
         * @param optionalconfig (optional) Any additional configuration to submit
         * @return response responseText field from response - please use success_callback instead
         */
        send_request : function(data, statusspinner, success_callback, optionalconfig) {
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
            data.courseId = this.get('courseid');
            data.quizId = this.get('quizid');

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
                    failure : function(tid, response) {
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
            return responsetext;
        }
    },
    {
        NAME : 'mod_quiz-toolbox',
        ATTRS : {
            // The ID of the current course
            courseid : {
                'value' : 0
            },
            quizid : {
                'value' : 0
            },
            ajaxurl : {
                'value' : 0
            },
            config : {
                'value' : 0
            }
        }
    }
    );

    /**
     * Resource and activity toolbox class.
     *
     * This class is responsible for managing AJAX interactions with activities and resources
     * when viewing a course in editing mode.
     *
     * @namespace M.mod_quiz.toolbox
     * @class ResourceToolbox
     * @constructor
     */
    var RESOURCETOOLBOX = function() {
        RESOURCETOOLBOX.superclass.constructor.apply(this, arguments);
    }

    Y.extend(RESOURCETOOLBOX, TOOLBOX, {
        /**
         * No groups are being used.
         * @static
         * @const GROUPS_NONE
         * @type Number
         */
        GROUPS_NONE     : 0,
        /**
         * Separate groups are being used.
         * @static
         * @const GROUPS_SEPARATE
         * @type Number
         */
        GROUPS_SEPARATE : 1,
        /**
         * Visible groups are being used.
         * @static
         * @const GROUPS_VISIBLE
         * @type Number
         */
        GROUPS_VISIBLE  : 2,

        /**
         * Events that were added when editing a maxmark.
         * These should all be detached when editing is complete.
         * @property editmaxmarkevents
         * @type {Event[]}
         * @protected
         */
        editmaxmarkevents : [],

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
         */
        initializer : function() {
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
        handle_data_action : function(ev) {
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
            Y.log(ev.type);

            // Switch based upon the action and do the desired thing.
            switch (action) {
                case 'editmaxmark' :
                    // The user wishes to edit the maxmark of the event.
                    this.edit_maxmark(ev, node, activity, action);
                    break;
                case 'moveleft' :
                case 'moveright' :
                    // The user changing the indent of the activity.
                    this.change_indent(ev, node, activity, action);
                    break;
                case 'delete' :
                    // The user is deleting the activity.
                    this.delete_with_confirmation(ev, node, activity, action);
                    break;
                 case 'update' :
                default:
                    // Nothing to do here!
                    break;
            }
        },
        add_spinner: function(activity) {
            var actionarea = activity.one(SELECTOR.ACTIONAREA);
            return M.util.add_spinner(Y, actionarea);
        },

        /**
         * Change the indent of the activity or resource.
         *
         * @protected
         * @method change_indent
         * @param {EventFacade} ev The event that was fired.
         * @param {Node} button The button that triggered this action.
         * @param {Node} activity The activity node that this action will be performed on.
         * @param {String} action The action that has been requested. Will be 'moveleft' or 'moveright'.
         */
        change_indent : function(ev, button, activity, action) {
            // Prevent the default button action
            ev.preventDefault();

            var direction = (action === 'moveleft') ? -1 : 1;

            // And we need to determine the current and new indent level
            var indentdiv = activity.one(SELECTOR.MODINDENTDIV),
                indent = indentdiv.getAttribute('class').match(/mod-indent-(\d{1,})/),
                oldindent = 0,
                newindent;

            if (indent) {
                oldindent = parseInt(indent[1], 10);
            }
            newindent = oldindent + parseInt(direction, 10);

            if (newindent < INDENTLIMITS.MIN || newindent > INDENTLIMITS.MAX) {
                return;
            }

            if (indent) {
                indentdiv.removeClass(indent[0]);
            }

            // Perform the move
            indentdiv.addClass(CSS.MODINDENTCOUNT + newindent);
            var data = {
                'class' : 'resource',
                'field' : 'indent',
                'value' : newindent,
                'id'    : Y.Moodle.core_course.util.cm.getId(activity)
            };
            var spinner = this.add_spinner(activity);
            this.send_request(data, spinner);

            var remainingmove;

            // Handle removal/addition of the moveleft button.
            if (newindent === INDENTLIMITS.MIN) {
                button.addClass('hidden');
                remainingmove = activity.one('.editing_moveright');
            } else if (newindent > INDENTLIMITS.MIN && oldindent === INDENTLIMITS.MIN) {
                button.ancestor('.menu').one('[data-action=moveleft]').removeClass('hidden');
            }

            if (newindent === INDENTLIMITS.MAX) {
                button.addClass('hidden');
                remainingmove = activity.one('.editing_moveleft');
            } else if (newindent < INDENTLIMITS.MAX && oldindent === INDENTLIMITS.MAX) {
                button.ancestor('.menu').one('[data-action=moveright]').removeClass('hidden');
            }

            // Handle massive indentation to match non-ajax display
            var hashugeclass = indentdiv.hasClass(CSS.MODINDENTHUGE);
            if (newindent > 15 && !hashugeclass) {
                indentdiv.addClass(CSS.MODINDENTHUGE);
            } else if (newindent <= 15 && hashugeclass) {
                indentdiv.removeClass(CSS.MODINDENTHUGE);
            }

            if (ev.type && ev.type === "key" && remainingmove) {
                remainingmove.focus();
            }
        },

        /**
         * Deletes the given activity or resource after confirmation.
         *
         * @protected
         * @method delete_with_confirmation
         * @param {EventFacade} ev The event that was fired.
         * @param {Node} button The button that triggered this action.
         * @param {Node} activity The activity node that this action will be performed on.
         * @return Boolean
         */
        delete_with_confirmation : function(ev, button, activity) {
            // Prevent the default button action
            ev.preventDefault();

            // Get the element we're working on
            var element   = activity

            // Create confirm string (different if element has or does not have name)
            var confirmstring = '';
            var plugindata = {
                type : M.util.get_string('pluginname', element.getAttribute('class').match(/qtype_([^\s]*)/)[1])
            }
            if (Y.Moodle.core_course.util.cm.getName(element) != null) {
                plugindata.name = Y.Moodle.core_course.util.cm.getName(element)
                confirmstring = M.util.get_string('deletechecktypename', 'moodle', plugindata);
            } else {
                confirmstring = M.util.get_string('deletechecktype', 'moodle', plugindata)
            }

            // Confirm element removal
            if (!confirm(confirmstring)) {
                return false;
            }

            // Actually remove the element
            element.remove();
            var data = {
                'class' : 'resource',
                'action' : 'DELETE',
                'id'    : Y.Moodle.core_course.util.cm.getId(element)
            };
            this.send_request(data);
            if (M.core.actionmenu && M.core.actionmenu.instance) {
                M.core.actionmenu.instance.hideMenu();
            }
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
            var activityid = Y.Moodle.core_course.util.cm.getId(activity),
                instancemaxmark  = activity.one(SELECTOR.INSTANCEMAXMARK),
                instance = activity.one(SELECTOR.ACTIVITYINSTANCE),
                currentmaxmark = instancemaxmark.get('firstChild'),
                oldmaxmark = currentmaxmark.get('data'),
                maxmarktext = oldmaxmark,
                thisevent,
                anchor = instancemaxmark.ancestor('span'),// Grab the anchor so that we can swap it with the edit form.
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
                var editor = Y.Node.create('<input name="title" type="text" class="'+CSS.TITLEEDITOR+'" />').setAttrs({
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

            var newmaxmark = Y.Lang.trim(activity.one(SELECTOR.ACTIVITYFORM + ' ' + SELECTOR.ACTIVITYTITLE).get('value'));
            this.edit_maxmark_clear(activity);
            var spinner = this.add_spinner(activity);
            if (newmaxmark != null && newmaxmark != "" && newmaxmark != originalmaxmark) {
                var data = {
                    'class'   : 'resource',
                    'field'   : 'updatemaxmark',
                    'title'   : newmaxmark,
                    'id'      : Y.Moodle.core_course.util.cm.getId(activity)
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
            var thisevent;
            while (thisevent = this.editmaxmarkevents.shift()) {
                thisevent.detach();
            }
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
    }, {
        NAME : 'mod_quiz-resource-toolbox',
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

    var SECTIONTOOLBOX = function() {
        SECTIONTOOLBOX.superclass.constructor.apply(this, arguments);
    }

    Y.extend(SECTIONTOOLBOX, TOOLBOX, {
        /**
         * Initialize the toolboxes module
         *
         * Updates all span.commands with relevant handlers and other required changes
         */
        initializer : function(config) {
            this.setup_for_section();
            M.mod_quiz.quizbase.register_module(this);

            // Section Highlighting
            Y.delegate('click', this.toggle_highlight, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.HIGHLIGHT, this);
            // Section Visibility
            Y.delegate('click', this.toggle_hide_section, SELECTOR.PAGECONTENT, SELECTOR.SECTIONLI + ' ' + SELECTOR.SHOWHIDE, this);
        },
        /**
         * Update any section areas within the scope of the specified
         * selector with AJAX equivelants
         *
         * @param baseselector The selector to limit scope to
         * @return void
         */
        setup_for_section : function(baseselector) {
            // Left here for potential future use - not currently needed due to YUI delegation in initializer()
            /*if (!baseselector) {
                var baseselector = SELECTOR.PAGECONTENT;
            }

            Y.all(baseselector).each(this._setup_for_section, this);*/
        },
        _setup_for_section : function(toolboxtarget) {
            // Left here for potential future use - not currently needed due to YUI delegation in initializer()
        },
        toggle_hide_section : function(e) {
            // Prevent the default button action
            e.preventDefault();

            // Get the section we're working on
            var section = e.target.ancestor(M.mod_quiz.format.get_section_selector(Y));
            var button = e.target.ancestor('a', true);
            var hideicon = button.one('img');

            // The value to submit
            var value;
            // The text for strings and images. Also determines the icon to display.
            var action,
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
                'id'    : Y.Moodle.core_course.util.section.getId(section.ancestor(M.mod_quiz.format.get_section_wrapper(Y), true)),
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
                    var activityid = Y.Moodle.core_course.util.cm.getId(node);

                    // NOTE: resourcestotoggle is returned as a string instead
                    // of a Number so we must cast our activityid to a String.
                    if (Y.Array.indexOf(response.resourcestotoggle, "" + activityid) !== -1) {
                        M.mod_quiz.resource_toolbox.handle_resource_dim(button, node, action);
                    }
                }, this);
            });
        },
        toggle_highlight : function(e) {
            // Prevent the default button action
            e.preventDefault();

            // Get the section we're working on
            var section = e.target.ancestor(M.mod_quiz.format.get_section_selector(Y));
            var button = e.target.ancestor('a', true);
            var buttonicon = button.one('img');

            // Determine whether the marker is currently set
            var togglestatus = section.hasClass('current');
            var value = 0;

            // Set the current highlighted item text
            var old_string = M.util.get_string('markthistopic', 'moodle');
            Y.one(SELECTOR.PAGECONTENT)
                .all(M.mod_quiz.format.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT)
                .set('title', old_string);
            Y.one(SELECTOR.PAGECONTENT)
                .all(M.mod_quiz.format.get_section_selector(Y) + '.current ' + SELECTOR.HIGHLIGHT + ' img')
                .set('alt', old_string)
                .set('src', M.util.image_url('i/marker'));

            // Remove the highlighting from all sections
            var allsections = Y.one(SELECTOR.PAGECONTENT).all(M.mod_quiz.format.get_section_selector(Y))
                .removeClass('current');

            // Then add it if required to the selected section
            if (!togglestatus) {
                section.addClass('current');
                value = Y.Moodle.core_course.util.section.getId(section.ancestor(M.mod_quiz.format.get_section_wrapper(Y), true));
                var new_string = M.util.get_string('markedthistopic', 'moodle');
                button
                    .set('title', new_string);
                buttonicon
                    .set('alt', new_string)
                    .set('src', M.util.image_url('i/marked'));
            }

            // Change the highlight status
            var data = {
                'class' : 'course',
                'field' : 'marker',
                'value' : value
            };
            var lightbox = M.util.add_lightbox(Y, section);
            lightbox.show();
            this.send_request(data, lightbox);
        }
    }, {
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

    M.mod_quiz = M.mod_quiz || {};
    M.mod_quiz.resource_toolbox = null;
    M.mod_quiz.init_resource_toolbox = function(config) {
        M.mod_quiz.resource_toolbox = new RESOURCETOOLBOX(config);
        return M.mod_quiz.resource_toolbox;
    };

    M.mod_quiz.init_section_toolbox = function(config) {
        return new SECTIONTOOLBOX(config);
    };

},
'@VERSION@', {
    requires : ['base', 'event-key', 'node', 'io', 'moodle-course-coursebase', 'moodle-course-util', 'moodle-mod_quiz-quizbase']
}
);
