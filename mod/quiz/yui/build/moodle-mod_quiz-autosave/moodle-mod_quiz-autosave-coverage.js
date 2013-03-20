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
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].code=["YUI.add('moodle-mod_quiz-autosave', function (Y, NAME) {","","// This file is part of Moodle - http://moodle.org/","//","// Moodle is free software: you can redistribute it and/or modify","// it under the terms of the GNU General Public License as published by","// the Free Software Foundation, either version 3 of the License, or","// (at your option) any later version.","//","// Moodle is distributed in the hope that it will be useful,","// but WITHOUT ANY WARRANTY; without even the implied warranty of","// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the","// GNU General Public License for more details.","//","// You should have received a copy of the GNU General Public License","// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.","","","/**"," * Auto-save functionality for during quiz attempts."," *"," * @package   mod_quiz"," * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}"," * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later"," */","","M.mod_quiz = M.mod_quiz || {};","M.mod_quiz.autosave = {","    TYPING_DELAY: 10000, // 2 minutes. Well temporarily 10s during development.","","    form: null,","","    delay_timeout_handle: null,","","    save_transaction: null,","","    init: function() {","        this.form = Y.one('#responseform');","        if (!this.form) {","            return;","        }","","        this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);","        this.form.delegate('change',      this.value_changed, 'input, select',   this);","    },","","    value_changed: function(e) {","        this.cancel_delay();","","        var self = this;","        this.delay_timeout_handle = setTimeout(function() {","            self.save_changes(null);","        }, this.TYPING_DELAY);","    },","","    cancel_delay: function() {","        if (this.delay_timeout_handle) {","            clearTimeout(this.delay_timeout_handle);","        }","        this.delay_timeout_handle = null;","    },","","    save_changes: function() {","        this.save_transaction = Y.io(M.cfg.wwwroot + '/mod/quiz/autosave.php', {","            method:  'POST',","            form:    {id: this.form},","            on:      {complete: this.save_done},","            context: this","        });","    },","","    save_done: function() {","        ","    }","};","","","}, '@VERSION@', {\"requires\": [\"base\", \"node\", \"event\", \"event-valuechange\", \"node-event-delegate\", \"io-form\"]});"];
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].lines = {"1":0,"27":0,"28":0,"38":0,"39":0,"40":0,"43":0,"44":0,"48":0,"50":0,"51":0,"52":0,"57":0,"58":0,"60":0,"64":0};
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].functions = {"init:37":0,"(anonymous 2):51":0,"value_changed:47":0,"cancel_delay:56":0,"save_changes:63":0,"(anonymous 1):1":0};
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].coveredLines = 16;
_yuitest_coverage["build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js"].coveredFunctions = 6;
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
    TYPING_DELAY: 10000, // 2 minutes. Well temporarily 10s during development.

    form: null,

    delay_timeout_handle: null,

    save_transaction: null,

    init: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "init", 37);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 38);
this.form = Y.one('#responseform');
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 39);
if (!this.form) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 40);
return;
        }

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 43);
this.form.delegate('valuechange', this.value_changed, 'input, textarea', this);
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 44);
this.form.delegate('change',      this.value_changed, 'input, select',   this);
    },

    value_changed: function(e) {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "value_changed", 47);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 48);
this.cancel_delay();

        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 50);
var self = this;
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 51);
this.delay_timeout_handle = setTimeout(function() {
            _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "(anonymous 2)", 51);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 52);
self.save_changes(null);
        }, this.TYPING_DELAY);
    },

    cancel_delay: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "cancel_delay", 56);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 57);
if (this.delay_timeout_handle) {
            _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 58);
clearTimeout(this.delay_timeout_handle);
        }
        _yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 60);
this.delay_timeout_handle = null;
    },

    save_changes: function() {
        _yuitest_coverfunc("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", "save_changes", 63);
_yuitest_coverline("build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js", 64);
this.save_transaction = Y.io(M.cfg.wwwroot + '/mod/quiz/autosave.php', {
            method:  'POST',
            form:    {id: this.form},
            on:      {complete: this.save_done},
            context: this
        });
    },

    save_done: function() {
        
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "event-valuechange", "node-event-delegate", "io-form"]});
