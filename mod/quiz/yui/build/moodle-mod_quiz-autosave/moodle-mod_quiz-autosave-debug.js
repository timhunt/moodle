YUI.add('moodle-mod_quiz-autosave', function (Y, NAME) {

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
 * Auto-save functionality for during quiz attempts.
 *
 * @package   mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.autosave = {
    TINYMCE_DETECTION_DELAY:  500,
    TINYMCE_DETECTION_REPEATS: 20,
    WATCH_HIDDEN_DELAY:      1000,

    /** The delay between a change being made, and it being auto-saved. */
    delay: 120000,

    /** The form we are monitoring. */
    form: null,

    /** Whether the form has been modified since the last save started. */
    dirty: false,

    /** Timer handle for the delay between form modifaction and the save starting. */
    delay_timeout_handle: null,

    /** Y.io transaction for the save ajax request. */
    save_transaction: null,

    /** Properly bound key change handler. */
    editor_change_hander: null,

    hidden_field_values: {},

    /**
     * Initialise the autosave code.
     * @param delay the delay, in seconds, between a change being detected, and
     * a save happening.
     */
    init: function(delay) {
        this.form = Y.one('#responseform');
        if (!this.form) {
            return;
        }

        this.delay = delay * 1000;

        this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);
        this.form.delegate('change',      this.value_changed, 'input, select',   this);
        this.form.on('submit', this.stop_autosaving, this);

        this.init_tinymce(this.TINYMCE_DETECTION_REPEATS);

        this.save_hidden_field_values();
        this.watch_hidden_fields();
    },

    save_hidden_field_values: function() {
        this.form.all('input[type=hidden]').each(function(hidden) {
            var name  = hidden.get('name');
            if (!name) {
                return;
            }
            this.hidden_field_values[name] = hidden.get('value');
        }, this);
    },

    watch_hidden_fields: function() {
        this.detect_hidden_field_changes();
        setTimeout(Y.bind(this.watch_hidden_fields, this), this.WATCH_HIDDEN_DELAY);
    },

    detect_hidden_field_changes: function() {
        this.form.all('input[type=hidden]').each(function(hidden) {
            var name  = hidden.get('name'),
                value = hidden.get('value');
            if (!name) {
                return;
            }
            if (value !== this.hidden_field_values[name]) {
                this.hidden_field_values[name] = value;
                this.value_changed({target: hidden});
            }
        }, this);
    },

    /**
     * @param repeatcount Because TinyMCE might load slowly, after us, we need
     * to keep trying every 10 seconds or so, until we detect TinyMCE is there,
     * or enough time has passed.
     */
    init_tinymce: function(repeatcount) {
        if (typeof tinymce === 'undefined') {
            if (repeatcount > 0) {
                var self = this;
                setTimeout(function() { self.init_tinymce(repeatcount - 1); },
                        this.TINYMCE_DETECTION_DELAY);
            }
            return;
        }

        Y.log('Found TinyMCE.');
        this.editor_change_hander = Y.bind(this.editor_changed, this);
        tinyMCE.onAddEditor.add(Y.bind(this.init_tinymce_editor, this));
    },

    /**
     * @param repeatcount Because TinyMCE might load slowly, after us, we need
     * to keep trying every 10 seconds or so, until we detect TinyMCE is there,
     * or enough time has passed.
     */
    init_tinymce_editor: function(notused, editor) {
        Y.log('Found TinyMCE editor ' + editor.id + '.');
        editor.onChange.add(this.editor_change_hander);
        editor.onRedo.add(this.editor_change_hander);
        editor.onUndo.add(this.editor_change_hander);
        editor.onKeyDown.add(this.editor_change_hander);
    },

    value_changed: function(e) {
        if (e.target.get('name') === 'thispage') {
            return; // Not interesting.
        }
        Y.log('Detected a value change in element ' + e.target.get('name') + '.');
        this.start_save_timer_if_necessary();
    },

    editor_changed: function(editor) {
        Y.log('Detected a value change in editor ' + editor.id + '.');
        this.start_save_timer_if_necessary();
    },

    start_save_timer_if_necessary: function() {
        this.dirty = true;

        if (this.delay_timeout_handle || this.save_transaction) {
            // Already counting down or daving.
            return;
        }

        this.start_save_timer();
    },

    start_save_timer: function() {
        this.cancel_delay();
        this.delay_timeout_handle = setTimeout(Y.bind(this.save_changes, this), this.delay);
    },

    cancel_delay: function() {
        if (this.delay_timeout_handle) {
            clearTimeout(this.delay_timeout_handle);
        }
        this.delay_timeout_handle = null;
    },

    save_changes: function() {
        this.cancel_delay();
        this.dirty = false;

        if (this.is_time_nearly_over()) {
            Y.log('No more saving, time is nearly over.');
            this.stop_autosaving();
            return;
        }

        Y.log('Doing a save.');
        this.save_transaction = Y.io(M.cfg.wwwroot + '/mod/quiz/autosave.php', {
            method:  'POST',
            form:    {id: this.form},
            on:      {complete: this.save_done},
            context: this
        });
    },

    save_done: function() {
        Y.log('Save completed.');
        this.save_transaction = null;

        if (this.dirty) {
            Y.log('Dirty after save.');
            this.start_save_timer();
        }
    },

    is_time_nearly_over: function() {
        return M.mod_quiz.timer && M.mod_quiz.timer.endtime &&
                new Date().getTime() + 2*this.delay > M.mod_quiz.timer.endtime;
    },

    stop_autosaving: function() {
        this.cancel_delay();
        this.delay_timeout_handle = true;
        if (this.save_transaction) {
            this.save_transaction.abort();
        }
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "event-valuechange", "node-event-delegate", "io-form"]});
