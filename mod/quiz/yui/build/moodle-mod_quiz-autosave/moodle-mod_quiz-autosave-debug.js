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
    TYPING_DELAY: 10000, // 2 minutes. Well temporarily 10s during development.

    form: null,

    delay_timeout_handle: null,

    save_transaction: null,

    init: function() {
        this.form = Y.one('#responseform');
        if (!this.form) {
            return;
        }

        this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);
        this.form.delegate('change',      this.value_changed, 'input, select',   this);
    },

    value_changed: function(e) {
        Y.log('value_changed for element ' + e.target.id);
        this.cancel_delay();

        var self = this;
        this.delay_timeout_handle = setTimeout(function() {
            self.save_changes(null);
        }, this.TYPING_DELAY);
    },

    cancel_delay: function() {
        if (this.delay_timeout_handle) {
            clearTimeout(this.delay_timeout_handle);
        }
        this.delay_timeout_handle = null;
    },

    save_changes: function() {
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
        
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "event-valuechange", "node-event-delegate", "io-form"]});
