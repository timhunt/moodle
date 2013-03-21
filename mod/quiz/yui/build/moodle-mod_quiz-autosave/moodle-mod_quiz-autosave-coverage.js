if (typeof _yuitest_coverage == "undefined"){
    _yuitest_coverage = {};
    _yuitest_coverline = function(src, line){
        var coverage = _yuitest_coverage[src];
        if (!coverage.lines[line]){
            coverage.calledLines++;
        }
        coverage.lines[line]++;
    };
    _yuitest_coverfunc = function(src, name, line){
        var coverage = _yuitest_coverage[src],
            funcId = name + ":" + line;
        if (!coverage.functions[funcId]){
            coverage.calledFunctions++;
        }
        coverage.functions[funcId]++;
    };
}
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"] = {
    lines: {},
    functions: {},
    coveredLines: 0,
    calledLines: 0,
    coveredFunctions: 0,
    calledFunctions: 0,
    path: "build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js",
    code: []
};
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].code=["YUI.add('moodle-mod_quiz-autosave', function (Y, NAME) {","","// This file is part of Moodle - http://moodle.org/","//","// Moodle is free software: you can redistribute it and/or modify","// it under the terms of the GNU General Public License as published by","// the Free Software Foundation, either version 3 of the License, or","// (at your option) any later version.","//","// Moodle is distributed in the hope that it will be useful,","// but WITHOUT ANY WARRANTY; without even the implied warranty of","// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the","// GNU General Public License for more details.","//","// You should have received a copy of the GNU General Public License","// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.","","","/**"," * Auto-save functionality for during quiz attempts."," *"," * @package   mod_quiz"," * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}"," * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later"," */","","M.mod_quiz = M.mod_quiz || {};","M.mod_quiz.autosave = {","    TINYMCE_DETECTION_DELAY:  100,","    TINYMCE_DETECTION_REPEATS: 20,","    WATCH_HIDDEN_DELAY:      1000,","","    /** The delay between a change being made, and it being auto-saved. */","    delay: 120000,","","    /** The form we are monitoring. */","    form: null,","","    /** Whether the form has been modified since the last save started. */","    dirty: false,","","    /** Timer handle for the delay between form modifaction and the save starting. */","    delay_timeout_handle: null,","","    /** Y.io transaction for the save ajax request. */","    save_transaction: null,","","    /** Properly bound key change handler. */","    editor_change_hander: null,","","    hidden_field_values: {},","","    /**","     * Initialise the autosave code.","     * @param delay the delay, in seconds, between a change being detected, and","     * a save happening.","     */","    init: function(delay) {","        this.form = Y.one('#responseform');","        if (!this.form) {","            return;","        }","","        this.delay = delay * 1000;","","        this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);","        this.form.delegate('change',      this.value_changed, 'select',   this);","","        this.init_tinymce(this.TINYMCE_DETECTION_REPEATS);","","        this.save_hidden_field_values();","        this.watch_hidden_fields();","    },","","    save_hidden_field_values: function() {","        this.form.all('input[type=hidden]').each(function(hidden) {","            var name  = hidden.get('name');","            if (!name) {","                return;","            }","            this.hidden_field_values[name] = hidden.get('value');","        }, this);","    },","","    watch_hidden_fields: function() {","        this.detect_hidden_field_changes();","        setTimeout(Y.bind(this.watch_hidden_fields, this), this.WATCH_HIDDEN_DELAY);","    },","","    detect_hidden_field_changes: function() {","        this.form.all('input[type=hidden]').each(function(hidden) {","            var name  = hidden.get('name'),","                value = hidden.get('value');","            if (!name) {","                return;","            }","            if (value !== this.hidden_field_values[name]) {","                this.hidden_field_values[name] = value;","                this.value_changed({target: hidden});","            }","        }, this);","    },","","    /**","     * @param repeatcount Because TinyMCE might load slowly, after us, we need","     * to keep trying every 10 seconds or so, until we detect TinyMCE is there,","     * or enough time has passed.","     */","    init_tinymce: function(repeatcount) {","        if (typeof tinymce === 'undefined') {","            if (repeatcount > 0) {","                var self = this;","                setTimeout(function() { self.init_tinymce(repeatcount - 1); },","                        this.TINYMCE_DETECTION_DELAY);","            }","            return;","        }","","        this.editor_change_hander = Y.bind(this.editor_changed, this);","        tinyMCE.onAddEditor.add(Y.bind(this.init_tinymce_editor, this));","    },","","    /**","     * @param repeatcount Because TinyMCE might load slowly, after us, we need","     * to keep trying every 10 seconds or so, until we detect TinyMCE is there,","     * or enough time has passed.","     */","    init_tinymce_editor: function(notused, editor) {","        editor.onChange.add(this.editor_change_hander);","        editor.onRedo.add(this.editor_change_hander);","        editor.onUndo.add(this.editor_change_hander);","        editor.onKeyDown.add(this.editor_change_hander);","    },","","    value_changed: function(e) {","        this.start_save_timer_if_necessary();","    },","","    editor_changed: function(editor) {","        this.start_save_timer_if_necessary();","    },","","    start_save_timer_if_necessary: function() {","        this.dirty = true;","","        if (this.delay_timeout_handle || this.save_transaction) {","            // Already counting down or daving.","            return;","        }","","        this.start_save_timer();","    },","","    start_save_timer: function() {","        this.cancel_delay();","        this.delay_timeout_handle = setTimeout(Y.bind(this.save_changes, this), this.delay);","    },","","    cancel_delay: function() {","        if (this.delay_timeout_handle) {","            clearTimeout(this.delay_timeout_handle);","        }","        this.delay_timeout_handle = null;","    },","","    save_changes: function() {","        this.cancel_delay();","        this.dirty = false;","","        if (this.is_time_nearly_over()) {","            this.delay_timeout_handle = true;","            return;","        }","","        this.save_transaction = Y.io(M.cfg.wwwroot + '/mod/quiz/autosave.php', {","            method:  'POST',","            form:    {id: this.form},","            on:      {complete: this.save_done},","            context: this","        });","    },","","    save_done: function() {","        this.save_transaction = null;","","        if (this.dirty) {","            this.start_save_timer();","        }","    },","","    is_time_nearly_over: function() {","        return M.mod_quiz.timer && new Date().getTime() + 2*this.delay > M.mod_quiz.timer.endtime;","    }","};","","","}, '@VERSION@', {\"requires\": [\"base\", \"node\", \"event\", \"event-valuechange\", \"node-event-delegate\", \"io-form\"]});"];
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].lines = {"1":0,"27":0,"28":0,"59":0,"60":0,"61":0,"64":0,"66":0,"67":0,"69":0,"71":0,"72":0,"76":0,"77":0,"78":0,"79":0,"81":0,"86":0,"87":0,"91":0,"92":0,"94":0,"95":0,"97":0,"98":0,"99":0,"110":0,"111":0,"112":0,"113":0,"116":0,"119":0,"120":0,"129":0,"130":0,"131":0,"132":0,"136":0,"140":0,"144":0,"146":0,"148":0,"151":0,"155":0,"156":0,"160":0,"161":0,"163":0,"167":0,"168":0,"170":0,"171":0,"172":0,"175":0,"184":0,"186":0,"187":0,"192":0};
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].functions = {"init:58":0,"(anonymous 2):76":0,"save_hidden_field_values:75":0,"watch_hidden_fields:85":0,"(anonymous 3):91":0,"detect_hidden_field_changes:90":0,"(anonymous 4):113":0,"init_tinymce:109":0,"init_tinymce_editor:128":0,"value_changed:135":0,"editor_changed:139":0,"start_save_timer_if_necessary:143":0,"start_save_timer:154":0,"cancel_delay:159":0,"save_changes:166":0,"save_done:183":0,"is_time_nearly_over:191":0,"(anonymous 1):1":0};
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].coveredLines = 58;
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].coveredFunctions = 18;
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 1);
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

_yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "(anonymous 1)", 1);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 27);
M.mod_quiz = M.mod_quiz || {};
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 28);
M.mod_quiz.autosave = {
    TINYMCE_DETECTION_DELAY:  100,
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
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "init", 58);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 59);
this.form = Y.one('#responseform');
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 60);
if (!this.form) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 61);
return;
        }

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 64);
this.delay = delay * 1000;

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 66);
this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 67);
this.form.delegate('change',      this.value_changed, 'select',   this);

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 69);
this.init_tinymce(this.TINYMCE_DETECTION_REPEATS);

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 71);
this.save_hidden_field_values();
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 72);
this.watch_hidden_fields();
    },

    save_hidden_field_values: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "save_hidden_field_values", 75);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 76);
this.form.all('input[type=hidden]').each(function(hidden) {
            _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "(anonymous 2)", 76);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 77);
var name  = hidden.get('name');
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 78);
if (!name) {
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 79);
return;
            }
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 81);
this.hidden_field_values[name] = hidden.get('value');
        }, this);
    },

    watch_hidden_fields: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "watch_hidden_fields", 85);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 86);
this.detect_hidden_field_changes();
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 87);
setTimeout(Y.bind(this.watch_hidden_fields, this), this.WATCH_HIDDEN_DELAY);
    },

    detect_hidden_field_changes: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "detect_hidden_field_changes", 90);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 91);
this.form.all('input[type=hidden]').each(function(hidden) {
            _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "(anonymous 3)", 91);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 92);
var name  = hidden.get('name'),
                value = hidden.get('value');
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 94);
if (!name) {
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 95);
return;
            }
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 97);
if (value !== this.hidden_field_values[name]) {
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 98);
this.hidden_field_values[name] = value;
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 99);
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
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "init_tinymce", 109);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 110);
if (typeof tinymce === 'undefined') {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 111);
if (repeatcount > 0) {
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 112);
var self = this;
                _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 113);
setTimeout(function() { _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "(anonymous 4)", 113);
self.init_tinymce(repeatcount - 1); },
                        this.TINYMCE_DETECTION_DELAY);
            }
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 116);
return;
        }

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 119);
this.editor_change_hander = Y.bind(this.editor_changed, this);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 120);
tinyMCE.onAddEditor.add(Y.bind(this.init_tinymce_editor, this));
    },

    /**
     * @param repeatcount Because TinyMCE might load slowly, after us, we need
     * to keep trying every 10 seconds or so, until we detect TinyMCE is there,
     * or enough time has passed.
     */
    init_tinymce_editor: function(notused, editor) {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "init_tinymce_editor", 128);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 129);
editor.onChange.add(this.editor_change_hander);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 130);
editor.onRedo.add(this.editor_change_hander);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 131);
editor.onUndo.add(this.editor_change_hander);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 132);
editor.onKeyDown.add(this.editor_change_hander);
    },

    value_changed: function(e) {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "value_changed", 135);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 136);
this.start_save_timer_if_necessary();
    },

    editor_changed: function(editor) {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "editor_changed", 139);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 140);
this.start_save_timer_if_necessary();
    },

    start_save_timer_if_necessary: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "start_save_timer_if_necessary", 143);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 144);
this.dirty = true;

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 146);
if (this.delay_timeout_handle || this.save_transaction) {
            // Already counting down or daving.
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 148);
return;
        }

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 151);
this.start_save_timer();
    },

    start_save_timer: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "start_save_timer", 154);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 155);
this.cancel_delay();
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 156);
this.delay_timeout_handle = setTimeout(Y.bind(this.save_changes, this), this.delay);
    },

    cancel_delay: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "cancel_delay", 159);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 160);
if (this.delay_timeout_handle) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 161);
clearTimeout(this.delay_timeout_handle);
        }
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 163);
this.delay_timeout_handle = null;
    },

    save_changes: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "save_changes", 166);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 167);
this.cancel_delay();
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 168);
this.dirty = false;

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 170);
if (this.is_time_nearly_over()) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 171);
this.delay_timeout_handle = true;
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 172);
return;
        }

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 175);
this.save_transaction = Y.io(M.cfg.wwwroot + '/mod/quiz/autosave.php', {
            method:  'POST',
            form:    {id: this.form},
            on:      {complete: this.save_done},
            context: this
        });
    },

    save_done: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "save_done", 183);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 184);
this.save_transaction = null;

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 186);
if (this.dirty) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 187);
this.start_save_timer();
        }
    },

    is_time_nearly_over: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "is_time_nearly_over", 191);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 192);
return M.mod_quiz.timer && new Date().getTime() + 2*this.delay > M.mod_quiz.timer.endtime;
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "event-valuechange", "node-event-delegate", "io-form"]});
