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
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"] = {
    lines: {},
    functions: {},
    coveredLines: 0,
    calledLines: 0,
    coveredFunctions: 0,
    calledFunctions: 0,
    path: "build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js",
    code: []
};
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"].code=["YUI.add('moodle-quizaccess_securewindow-lockdown', function (Y, NAME) {","","// This file is part of Moodle - http://moodle.org/","//","// Moodle is free software: you can redistribute it and/or modify","// it under the terms of the GNU General Public License as published by","// the Free Software Foundation, either version 3 of the License, or","// (at your option) any later version.","//","// Moodle is distributed in the hope that it will be useful,","// but WITHOUT ANY WARRANTY; without even the implied warranty of","// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the","// GNU General Public License for more details.","//","// You should have received a copy of the GNU General Public License","// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.","","/**"," * JavaScript for the 'secure' window access rule."," *"," * @package   quizaccess_securewindow"," * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}"," * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later"," */","","M.quizaccess_securewindow = M.quizaccess_securewindow || {};","M.quizaccess_securewindow.lockdown = {","","    /**","     * Event handler for the quiz start attempt button.","     */","    start_attempt_action: function(e, args) {","        if (args.startattemptwarning === '') {","            openpopup(e, args);","        } else {","            M.util.show_confirm_dialog(e, {","                message: args.startattemptwarning,","                callback: function() {","                    openpopup(e, args);","                },","                continuelabel: M.util.get_string('startattempt', 'quiz')","            });","        }","    },","","        ","    init_attempt_page: function(Y) {","        function clear_status() {","            window.status = '';","            setTimeout(clear_status, 10);","        }","        clear_status();","","        function prevent(e) {","            alert(M.str.quiz.functiondisabledbysecuremode);","            e.halt();","        }","","        function prevent_mouse(e) {","            if (e.button === 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {","                // Left click on a button or similar. No worries.","                return;","            }","            e.halt();","        }","","        if (window.location.href.substring(0, 4) === 'file') {","            window.location = 'about:blank';","        }","        Y.delegate('contextmenu', prevent, document, '*');","        Y.delegate('mousedown',   prevent_mouse, document, '*');","        Y.delegate('mouseup',     prevent_mouse, document, '*');","        Y.delegate('dragstart',   prevent, document, '*');","        Y.delegate('selectstart', prevent, document, '*');","        Y.delegate('cut',         prevent, document, '*');","        Y.delegate('copy',        prevent, document, '*');","        Y.delegate('paste',       prevent, document, '*');","        ","        Y.on('beforeprint', function() {","            Y.one(document.body).setStyle('display', 'none');","        }, window);","        Y.on('afterprint', function() {","            Y.one(document.body).setStyle('display', 'block');","        }, window);","        Y.on('key', prevent, '*', 'press:67,86,88+ctrl');","        Y.on('key', prevent, '*', 'up:67,86,88+ctrl');","        Y.on('key', prevent, '*', 'down:67,86,88+ctrl');","        Y.on('key', prevent, '*', 'press:67,86,88+meta');","        Y.on('key', prevent, '*', 'up:67,86,88+meta');","        Y.on('key', prevent, '*', 'down:67,86,88+meta');","","        // If a finish review link is present, make it work.","        var finishreviewlink = Y.one('#finishreviewlink');","        if (finishreviewlink) {","            finishreviewlink.on('click', function(e) {","                M.quizaccess_securewindow.lockdown.close(e.target.href, 0);","            });","        }","    },","","    close: function(url, delay) {","        setTimeout(function() {","            if (window.opener) {","                window.opener.document.location.reload();","                window.close();","            } else {","                window.location.href = M.cfg.wwwroot + '/mod/quiz/view.php?id=' + cmid;","            }","        }, delay*1000);","    }","};","","","}, '@VERSION@', {\"requires\": [\"base\", \"node\", \"event\", \"dom\"]});"];
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"].lines = {"1":0,"26":0,"27":0,"33":0,"34":0,"36":0,"39":0,"48":0,"49":0,"50":0,"52":0,"54":0,"55":0,"56":0,"59":0,"60":0,"62":0,"64":0,"67":0,"68":0,"70":0,"71":0,"72":0,"73":0,"74":0,"75":0,"76":0,"77":0,"79":0,"80":0,"82":0,"83":0,"85":0,"86":0,"87":0,"88":0,"89":0,"90":0,"93":0,"94":0,"95":0,"96":0,"102":0,"103":0,"104":0,"105":0,"107":0};
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"].functions = {"callback:38":0,"start_attempt_action:32":0,"clear_status:48":0,"prevent:54":0,"prevent_mouse:59":0,"(anonymous 2):79":0,"(anonymous 3):82":0,"(anonymous 4):95":0,"init_attempt_page:47":0,"(anonymous 5):102":0,"close:101":0,"(anonymous 1):1":0};
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"].coveredLines = 47;
_yuitest_coverage["build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js"].coveredFunctions = 12;
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 1);
YUI.add('moodle-quizaccess_securewindow-lockdown', function (Y, NAME) {

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
 * JavaScript for the 'secure' window access rule.
 *
 * @package   quizaccess_securewindow
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

_yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "(anonymous 1)", 1);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 26);
M.quizaccess_securewindow = M.quizaccess_securewindow || {};
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 27);
M.quizaccess_securewindow.lockdown = {

    /**
     * Event handler for the quiz start attempt button.
     */
    start_attempt_action: function(e, args) {
        _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "start_attempt_action", 32);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 33);
if (args.startattemptwarning === '') {
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 34);
openpopup(e, args);
        } else {
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 36);
M.util.show_confirm_dialog(e, {
                message: args.startattemptwarning,
                callback: function() {
                    _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "callback", 38);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 39);
openpopup(e, args);
                },
                continuelabel: M.util.get_string('startattempt', 'quiz')
            });
        }
    },

        
    init_attempt_page: function(Y) {
        _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "init_attempt_page", 47);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 48);
function clear_status() {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "clear_status", 48);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 49);
window.status = '';
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 50);
setTimeout(clear_status, 10);
        }
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 52);
clear_status();

        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 54);
function prevent(e) {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "prevent", 54);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 55);
alert(M.str.quiz.functiondisabledbysecuremode);
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 56);
e.halt();
        }

        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 59);
function prevent_mouse(e) {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "prevent_mouse", 59);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 60);
if (e.button === 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
                // Left click on a button or similar. No worries.
                _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 62);
return;
            }
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 64);
e.halt();
        }

        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 67);
if (window.location.href.substring(0, 4) === 'file') {
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 68);
window.location = 'about:blank';
        }
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 70);
Y.delegate('contextmenu', prevent, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 71);
Y.delegate('mousedown',   prevent_mouse, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 72);
Y.delegate('mouseup',     prevent_mouse, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 73);
Y.delegate('dragstart',   prevent, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 74);
Y.delegate('selectstart', prevent, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 75);
Y.delegate('cut',         prevent, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 76);
Y.delegate('copy',        prevent, document, '*');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 77);
Y.delegate('paste',       prevent, document, '*');
        
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 79);
Y.on('beforeprint', function() {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "(anonymous 2)", 79);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 80);
Y.one(document.body).setStyle('display', 'none');
        }, window);
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 82);
Y.on('afterprint', function() {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "(anonymous 3)", 82);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 83);
Y.one(document.body).setStyle('display', 'block');
        }, window);
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 85);
Y.on('key', prevent, '*', 'press:67,86,88+ctrl');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 86);
Y.on('key', prevent, '*', 'up:67,86,88+ctrl');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 87);
Y.on('key', prevent, '*', 'down:67,86,88+ctrl');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 88);
Y.on('key', prevent, '*', 'press:67,86,88+meta');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 89);
Y.on('key', prevent, '*', 'up:67,86,88+meta');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 90);
Y.on('key', prevent, '*', 'down:67,86,88+meta');

        // If a finish review link is present, make it work.
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 93);
var finishreviewlink = Y.one('#finishreviewlink');
        _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 94);
if (finishreviewlink) {
            _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 95);
finishreviewlink.on('click', function(e) {
                _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "(anonymous 4)", 95);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 96);
M.quizaccess_securewindow.lockdown.close(e.target.href, 0);
            });
        }
    },

    close: function(url, delay) {
        _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "close", 101);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 102);
setTimeout(function() {
            _yuitest_coverfunc("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", "(anonymous 5)", 102);
_yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 103);
if (window.opener) {
                _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 104);
window.opener.document.location.reload();
                _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 105);
window.close();
            } else {
                _yuitest_coverline("build/moodle-quizaccess_securewindow-lockdown/moodle-quizaccess_securewindow-lockdown.js", 107);
window.location.href = M.cfg.wwwroot + '/mod/quiz/view.php?id=' + cmid;
            }
        }, delay*1000);
    }
};


}, '@VERSION@', {"requires": ["base", "node", "event", "dom"]});
