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

M.quizaccess_securewindow = M.quizaccess_securewindow || {};
M.quizaccess_securewindow.lockdown = {

    init_view_page() {
        // TODO find the actual start attemtp button - either the one in the
        // are you sure confirmation, or if there is no confirmation, the one
        // on the page, then sieze control over it's event handler.
        function start_attempt_action(e, args) {
            if (args.startattemptwarning === '') {
                openpopup(e, args);
            } else {
                M.util.show_confirm_dialog(e, {
                    message: args.startattemptwarning,
                    callback: function() {
                        openpopup(e, args);
                    },
                    continuelabel: M.util.get_string('startattempt', 'quiz')
                });
            }
        }

        Y.all();
        // TODO
        if ($reviewinpopup) {
            $button = new single_button($url, get_string('review', 'quiz'));
            $button->add_action(new popup_action('click', $url, 'quizpopup', $popupoptions));
            return $this->render($button);
        }
        if ($popuprequired) {
            $this->page->requires->js_module(quiz_get_js_module());
            $this->page->requires->js('/mod/quiz/module.js');
            $popupaction = new popup_action('click', $url, 'quizpopup', $popupoptions);

            $button->class .= ' quizsecuremoderequired';
            $button->add_action(new component_action('click',
                    'M.quizaccess_securewindow.lockdown.start_attempt_action', array(
                        'url' => $url->out(false),
                        'windowname' => 'quizpopup',
                        'options' => $popupaction->get_js_options(),
                        'fullscreen' => true,
                        'startattemptwarning' => $startattemptwarning,
                    )));

            $warning = html_writer::tag('noscript', $this->heading(get_string('noscript', 'quiz')));

        }

    }

    close: function(url, delay) {
        setTimeout(function() {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else {
                window.location.href = M.cfg.wwwroot + '/mod/quiz/view.php?id=' + cmid;
            }
        }, delay*1000);
    }

    init_attempt_page: function(Y) {
        function clear_status() {
            window.status = '';
            setTimeout(clear_status, 10);
        }
        clear_status();

        function prevent(e) {
            alert(M.str.quiz.functiondisabledbysecuremode);
            e.halt();
        }

        function prevent_mouse(e) {
            if (e.button === 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
                // Left click on a button or similar. No worries.
                return;
            }
            e.halt();
        }

        if (window.location.href.substring(0, 4) === 'file') {
            window.location = 'about:blank';
        }
        Y.delegate('contextmenu', prevent, document, '*');
        Y.delegate('mousedown',   prevent_mouse, document, '*');
        Y.delegate('mouseup',     prevent_mouse, document, '*');
        Y.delegate('dragstart',   prevent, document, '*');
        Y.delegate('selectstart', prevent, document, '*');
        Y.delegate('cut',         prevent, document, '*');
        Y.delegate('copy',        prevent, document, '*');
        Y.delegate('paste',       prevent, document, '*');
        
        Y.on('beforeprint', function() {
            Y.one(document.body).setStyle('display', 'none');
        }, window);
        Y.on('afterprint', function() {
            Y.one(document.body).setStyle('display', 'block');
        }, window);
        Y.on('key', prevent, '*', 'press:67,86,88+ctrl');
        Y.on('key', prevent, '*', 'up:67,86,88+ctrl');
        Y.on('key', prevent, '*', 'down:67,86,88+ctrl');
        Y.on('key', prevent, '*', 'press:67,86,88+meta');
        Y.on('key', prevent, '*', 'up:67,86,88+meta');
        Y.on('key', prevent, '*', 'down:67,86,88+meta');

        // If a finish review link is present, make it work.
        var finishreviewlink = Y.one('#finishreviewlink');
        if (finishreviewlink) {
            finishreviewlink.on('click', function(e) {
                M.quizaccess_securewindow.lockdown.close(e.target.href, 0);
            });
        }

        // If this is the 'enter quiz password' page, then make the close button
        // close the window instead of submitting the form.
        Y.one('body#page-mod-quiz-view #id_cancel').on('click', function(e) {
            if (window.opener) {
                e.halt();
                window.opener.document.location.reload();
                window.close();
            }
        });
    },
};
