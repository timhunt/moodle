<?php
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
 * Defines the renderer for the quiz module.
 *
 * @package   mod_quiz
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use calendartype_gregorian\structure;

defined('MOODLE_INTERNAL') || die();


/**
 * The renderer for the quiz module.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_renderer extends plugin_renderer_base {
    /**
     * Builds the review page
     *
     * @param quiz_attempt $attemptobj an instance of quiz_attempt.
     * @param array $slots an array of intgers relating to questions.
     * @param int $page the current page number
     * @param bool $showall whether to show entire attempt on one page.
     * @param bool $lastpage if true the current page is the last page.
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_page(quiz_attempt $attemptobj, $slots, $page, $showall,
                                $lastpage, mod_quiz_display_options $displayoptions,
                                $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, $page);
        $output .= $this->review_form($page, $showall, $displayoptions,
                $this->questions($attemptobj, true, $slots, $page, $showall, $displayoptions),
                $attemptobj);

        $output .= $this->review_next_navigation($attemptobj, $page, $lastpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param quiz_attempt $attemptobj an instance of quiz_attempt.
     * @param int $slot which question to display.
     * @param int $seq which step of the question attempt to show. null = latest.
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options.
     * @param array $summarydata contains all table data
     * @return $output containing html data.
     */
    public function review_question_page(quiz_attempt $attemptobj, $slot, $seq,
            mod_quiz_display_options $displayoptions, $summarydata) {

        $output = '';
        $output .= $this->header();
        $output .= $this->review_summary_table($summarydata, 0);

        if (!is_null($seq)) {
            $output .= $attemptobj->render_question_at_step($slot, $seq, true);
        } else {
            $output .= $attemptobj->render_question($slot, true);
        }

        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Renders the review question pop-up.
     *
     * @param string $message Why the review is not allowed.
     * @return string html to output.
     */
    public function review_question_not_allowed($message) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_quiz_name(), true,
                                  array("context" => $attemptobj->get_quizobj()->get_context())));
        $output .= $this->notification($message);
        $output .= $this->close_window_button();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Filters the summarydata array.
     *
     * @param array $summarydata contains row data for table
     * @param int $page the current page number
     * @return $summarydata containing filtered row data
     */
    protected function filter_review_summary_table($summarydata, $page) {
        if ($page == 0) {
            return $summarydata;
        }

        // Only show some of summary table on subsequent pages.
        foreach ($summarydata as $key => $rowdata) {
            if (!in_array($key, array('user', 'attemptlist'))) {
                unset($summarydata[$key]);
            }
        }

        return $summarydata;
    }

    /**
     * Outputs the table containing data from summary data array
     *
     * @param array $summarydata contains row data for table
     * @param int $page contains the current page number
     */
    public function review_summary_table($summarydata, $page) {
        $summarydata = $this->filter_review_summary_table($summarydata, $page);
        if (empty($summarydata)) {
            return '';
        }

        $output = '';
        $output .= html_writer::start_tag('table', array(
                'class' => 'generaltable generalbox quizreviewsummary'));
        $output .= html_writer::start_tag('tbody');
        foreach ($summarydata as $rowdata) {
            if ($rowdata['title'] instanceof renderable) {
                $title = $this->render($rowdata['title']);
            } else {
                $title = $rowdata['title'];
            }

            if ($rowdata['content'] instanceof renderable) {
                $content = $this->render($rowdata['content']);
            } else {
                $content = $rowdata['content'];
            }

            $output .= html_writer::tag('tr',
                html_writer::tag('th', $title, array('class' => 'cell', 'scope' => 'row')) .
                        html_writer::tag('td', $content, array('class' => 'cell'))
            );
        }

        $output .= html_writer::end_tag('tbody');
        $output .= html_writer::end_tag('table');
        return $output;
    }

    /**
     * Renders each question
     *
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param bool $reviewing
     * @param array $slots array of intgers relating to questions
     * @param int $page current page number
     * @param bool $showall if true shows attempt on single page
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options
     */
    public function questions(quiz_attempt $attemptobj, $reviewing, $slots, $page, $showall,
                              mod_quiz_display_options $displayoptions) {
        $output = '';
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, $reviewing,
                    $attemptobj->review_url($slot, $page, $showall));
        }
        return $output;
    }

    /**
     * Renders the main bit of the review page.
     *
     * @param array $summarydata contain row data for table
     * @param int $page current page number
     * @param mod_quiz_display_options $displayoptions instance of mod_quiz_display_options
     * @param $content contains each question
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param bool $showall if true display attempt on one page
     */
    public function review_form($page, $showall, $displayoptions, $content, $attemptobj) {
        if ($displayoptions->flags != question_display_options::EDITABLE) {
            return $content;
        }

        $this->page->requires->js_init_call('M.mod_quiz.init_review_form', null, false,
                quiz_get_js_module());

        $output = '';
        $output .= html_writer::start_tag('form', array('action' => $attemptobj->review_url(null,
                $page, $showall), 'method' => 'post', 'class' => 'questionflagsaveform'));
        $output .= html_writer::start_tag('div');
        $output .= $content;
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                'class' => 'questionflagsavebutton', 'name' => 'savingflags',
                'value' => get_string('saveflags', 'question')));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        return $output;
    }

    /**
     * Returns either a liink or button
     *
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     */
    public function finish_review_link(quiz_attempt $attemptobj) {
        $url = $attemptobj->view_url();

        if ($attemptobj->get_access_manager(time())->attempt_must_be_in_popup()) {
            $this->page->requires->js_init_call('M.mod_quiz.secure_window.init_close_button',
                    array($url), quiz_get_js_module());
            return html_writer::empty_tag('input', array('type' => 'button',
                    'value' => get_string('finishreview', 'quiz'),
                    'id' => 'secureclosebutton'));

        } else {
            return html_writer::link($url, get_string('finishreview', 'quiz'));
        }
    }

    /**
     * Creates a next page arrow or the finishing link
     *
     * @param quiz_attempt $attemptobj instance of quiz_attempt
     * @param int $page the current page
     * @param bool $lastpage if true current page is the last page
     */
    public function review_next_navigation(quiz_attempt $attemptobj, $page, $lastpage) {
        if ($lastpage) {
            $nav = $this->finish_review_link($attemptobj);
        } else {
            $nav = link_arrow_right(get_string('next'), $attemptobj->review_url(null, $page + 1));
        }
        return html_writer::tag('div', $nav, array('class' => 'submitbtns'));
    }

    /**
     * Return the HTML of the quiz timer.
     * @return string HTML content.
     */
    public function countdown_timer(quiz_attempt $attemptobj, $timenow) {

        $timeleft = $attemptobj->get_time_left_display($timenow);
        if ($timeleft !== false) {
            $ispreview = $attemptobj->is_preview();
            $timerstartvalue = $timeleft;
            if (!$ispreview) {
                // Make sure the timer starts just above zero. If $timeleft was <= 0, then
                // this will just have the effect of causing the quiz to be submitted immediately.
                $timerstartvalue = max($timerstartvalue, 1);
            }
            $this->initialise_timer($timerstartvalue, $ispreview);
        }

        return html_writer::tag('div', get_string('timeleft', 'quiz') . ' ' .
                html_writer::tag('span', '', array('id' => 'quiz-time-left')),
                array('id' => 'quiz-timer', 'role' => 'timer',
                    'aria-atomic' => 'true', 'aria-relevant' => 'text'));
    }

    /**
     * Create a preview link
     *
     * @param $url contains a url to the given page
     */
    public function restart_preview_button($url) {
        return $this->single_button($url, get_string('startnewpreview', 'quiz'));
    }

    /**
     * Outputs the navigation block panel
     *
     * @param quiz_nav_panel_base $panel instance of quiz_nav_panel_base
     */
    public function navigation_panel(quiz_nav_panel_base $panel) {

        $output = '';
        $userpicture = $panel->user_picture();
        if ($userpicture) {
            $fullname = fullname($userpicture->user);
            if ($userpicture->size === true) {
                $fullname = html_writer::div($fullname);
            }
            $output .= html_writer::tag('div', $this->render($userpicture) . $fullname,
                    array('id' => 'user-picture', 'class' => 'clearfix'));
        }
        $output .= $panel->render_before_button_bits($this);

        $bcc = $panel->get_button_container_class();
        $output .= html_writer::start_tag('div', array('class' => "qn_buttons $bcc"));
        foreach ($panel->get_question_buttons() as $button) {
            $output .= $this->render($button);
        }
        $output .= html_writer::end_tag('div');

        $output .= html_writer::tag('div', $panel->render_end_bits($this),
                array('class' => 'othernav'));

        $this->page->requires->js_init_call('M.mod_quiz.nav.init', null, false,
                quiz_get_js_module());

        return $output;
    }

    /**
     * Returns the quizzes navigation button
     *
     * @param quiz_nav_question_button $button
     */
    protected function render_quiz_nav_question_button(quiz_nav_question_button $button) {
        $classes = array('qnbutton', $button->stateclass, $button->navmethod);
        $attributes = array();

        if ($button->currentpage) {
            $classes[] = 'thispage';
            $attributes[] = get_string('onthispage', 'quiz');
        }

        // Flagged?
        if ($button->flagged) {
            $classes[] = 'flagged';
            $flaglabel = get_string('flagged', 'question');
        } else {
            $flaglabel = '';
        }
        $attributes[] = html_writer::tag('span', $flaglabel, array('class' => 'flagstate'));

        if (is_numeric($button->number)) {
            $qnostring = 'questionnonav';
        } else {
            $qnostring = 'questionnonavinfo';
        }

        $a = new stdClass();
        $a->number = $button->number;
        $a->attributes = implode(' ', $attributes);
        $tagcontents = html_writer::tag('span', '', array('class' => 'thispageholder')) .
                        html_writer::tag('span', '', array('class' => 'trafficlight')) .
                        get_string($qnostring, 'quiz', $a);
        $tagattributes = array('class' => implode(' ', $classes), 'id' => $button->id,
                                  'title' => $button->statestring);

        if ($button->url) {
            return html_writer::link($button->url, $tagcontents, $tagattributes);
        } else {
            return html_writer::tag('span', $tagcontents, $tagattributes);
        }
    }

    /**
     * outputs the link the other attempts.
     *
     * @param mod_quiz_links_to_other_attempts $links
     */
    protected function render_mod_quiz_links_to_other_attempts(
            mod_quiz_links_to_other_attempts $links) {
        $attemptlinks = array();
        foreach ($links->links as $attempt => $url) {
            if ($url) {
                $attemptlinks[] = html_writer::link($url, $attempt);
            } else {
                $attemptlinks[] = html_writer::tag('strong', $attempt);
            }
        }
        return implode(', ', $attemptlinks);
    }

    public function start_attempt_page(quiz $quizobj, mod_quiz_preflight_check_form $mform) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($quizobj->get_quiz_name(), true,
                                  array("context" => $quizobj->get_context())));
        $output .= $this->quiz_intro($quizobj->get_quiz(), $quizobj->get_cm());
        ob_start();
        $mform->display();
        $output .= ob_get_clean();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Attempt Page
     *
     * @param quiz_attempt $attemptobj Instance of quiz_attempt
     * @param int $page Current page number
     * @param quiz_access_manager $accessmanager Instance of quiz_access_manager
     * @param array $messages An array of messages
     * @param array $slots Contains an array of integers that relate to questions
     * @param int $id The ID of an attempt
     * @param int $nextpage The number of the next page
     */
    public function attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id,
            $nextpage) {
        $output = '';
        $output .= $this->header();
        $output .= $this->quiz_notices($messages);
        $output .= $this->attempt_form($attemptobj, $page, $slots, $id, $nextpage);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Returns any notices.
     *
     * @param array $messages
     */
    public function quiz_notices($messages) {
        if (!$messages) {
            return '';
        }
        return $this->box($this->heading(get_string('accessnoticesheader', 'quiz'), 3) .
                $this->access_messages($messages), 'quizaccessnotices');
    }

    /**
     * Ouputs the form for making an attempt
     *
     * @param quiz_attempt $attemptobj
     * @param int $page Current page number
     * @param array $slots Array of integers relating to questions
     * @param int $id ID of the attempt
     * @param int $nextpage Next page number
     */
    public function attempt_form($attemptobj, $page, $slots, $id, $nextpage) {
        $output = '';

        // Start the form.
        $output .= html_writer::start_tag('form',
                array('action' => $attemptobj->processattempt_url(), 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'));
        $output .= html_writer::start_tag('div');

        // Print all the questions.
        foreach ($slots as $slot) {
            $output .= $attemptobj->render_question($slot, false,
                    $attemptobj->attempt_url($slot, $page));
        }

        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'next',
                'value' => get_string('next')));
        $output .= html_writer::end_tag('div');

        // Some hidden fields to trach what is going on.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'attempt',
                'value' => $attemptobj->get_attemptid()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'thispage',
                'value' => $page, 'id' => 'followingpage'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'nextpage',
                'value' => $nextpage));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'timeup',
                'value' => '0', 'id' => 'timeup'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos',
                'value' => '', 'id' => 'scrollpos'));

        // Add a hidden field with questionids. Do this at the end of the form, so
        // if you navigate before the form has finished loading, it does not wipe all
        // the student's answers.
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
                'value' => implode(',', $slots)));

        // Finish the form.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');

        $output .= $this->connection_warning();

        return $output;
    }

    /**
     * Output the JavaScript required to initialise the countdown timer.
     * @param int $timerstartvalue time remaining, in seconds.
     */
    public function initialise_timer($timerstartvalue, $ispreview) {
        $options = array($timerstartvalue, (bool)$ispreview);
        $this->page->requires->js_init_call('M.mod_quiz.timer.init', $options, false, quiz_get_js_module());
    }

    /**
     * Output a page with an optional message, and JavaScript code to close the
     * current window and redirect the parent window to a new URL.
     * @param moodle_url $url the URL to redirect the parent window to.
     * @param string $message message to display before closing the window. (optional)
     * @return string HTML to output.
     */
    public function close_attempt_popup($url, $message = '') {
        $output = '';
        $output .= $this->header();
        $output .= $this->box_start();

        if ($message) {
            $output .= html_writer::tag('p', $message);
            $output .= html_writer::tag('p', get_string('windowclosing', 'quiz'));
            $delay = 5;
        } else {
            $output .= html_writer::tag('p', get_string('pleaseclose', 'quiz'));
            $delay = 0;
        }
        $this->page->requires->js_init_call('M.mod_quiz.secure_window.close',
                array($url, $delay), false, quiz_get_js_module());

        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
    }

    /**
     * Print each message in an array, surrounded by &lt;p>, &lt;/p> tags.
     *
     * @param array $messages the array of message strings.
     * @param bool $return if true, return a string, instead of outputting.
     *
     * @return string HTML to output.
     */
    public function access_messages($messages) {
        $output = '';
        foreach ($messages as $message) {
            $output .= html_writer::tag('p', $message) . "\n";
        }
        return $output;
    }

    /*
     * Summary Page
     */
    /**
     * Create the summary page
     *
     * @param quiz_attempt $attemptobj
     * @param mod_quiz_display_options $displayoptions
     */
    public function summary_page($attemptobj, $displayoptions) {
        $output = '';
        $output .= $this->header();
        $output .= $this->heading(format_string($attemptobj->get_quiz_name()));
        $output .= $this->heading(get_string('summaryofattempt', 'quiz'), 3);
        $output .= $this->summary_table($attemptobj, $displayoptions);
        $output .= $this->summary_page_controls($attemptobj);
        $output .= $this->footer();
        return $output;
    }

    /**
     * Generates the table of summarydata
     *
     * @param quiz_attempt $attemptobj
     * @param mod_quiz_display_options $displayoptions
     */
    public function summary_table($attemptobj, $displayoptions) {
        // Prepare the summary table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizsummaryofattempt boxaligncenter';
        $table->head = array(get_string('question', 'quiz'), get_string('status', 'quiz'));
        $table->align = array('left', 'left');
        $table->size = array('', '');
        $markscolumn = $displayoptions->marks >= question_display_options::MARK_AND_MAX;
        if ($markscolumn) {
            $table->head[] = get_string('marks', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }
        $table->data = array();

        // Get the summary info for each question.
        $slots = $attemptobj->get_slots();
        foreach ($slots as $slot) {
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }
            $flag = '';
            if ($attemptobj->is_question_flagged($slot)) {
                $flag = html_writer::empty_tag('img', array('src' => $this->pix_url('i/flagged'),
                        'alt' => get_string('flagged', 'question'), 'class' => 'questionflag icon-post'));
            }
            if ($attemptobj->can_navigate_to($slot)) {
                $row = array(html_writer::link($attemptobj->attempt_url($slot),
                        $attemptobj->get_question_number($slot) . $flag),
                        $attemptobj->get_question_status($slot, $displayoptions->correctness));
            } else {
                $row = array($attemptobj->get_question_number($slot) . $flag,
                                $attemptobj->get_question_status($slot, $displayoptions->correctness));
            }
            if ($markscolumn) {
                $row[] = $attemptobj->get_question_mark($slot);
            }
            $table->data[] = $row;
            $table->rowclasses[] = $attemptobj->get_question_state_class(
                    $slot, $displayoptions->correctness);
        }

        // Print the summary table.
        $output = html_writer::table($table);

        return $output;
    }

    /**
     * Creates any controls a the page should have.
     *
     * @param quiz_attempt $attemptobj
     */
    public function summary_page_controls($attemptobj) {
        $output = '';

        // Return to place button.
        if ($attemptobj->get_state() == quiz_attempt::IN_PROGRESS) {
            $button = new single_button(
                    new moodle_url($attemptobj->attempt_url(null, $attemptobj->get_currentpage())),
                    get_string('returnattempt', 'quiz'));
            $output .= $this->container($this->container($this->render($button),
                    'controls'), 'submitbtns mdl-align');
        }

        // Finish attempt button.
        $options = array(
            'attempt' => $attemptobj->get_attemptid(),
            'finishattempt' => 1,
            'timeup' => 0,
            'slots' => '',
            'sesskey' => sesskey(),
        );

        $button = new single_button(
                new moodle_url($attemptobj->processattempt_url(), $options),
                get_string('submitallandfinish', 'quiz'));
        $button->id = 'responseform';
        if ($attemptobj->get_state() == quiz_attempt::IN_PROGRESS) {
            $button->add_action(new confirm_action(get_string('confirmclose', 'quiz'), null,
                    get_string('submitallandfinish', 'quiz')));
        }

        $duedate = $attemptobj->get_due_date();
        $message = '';
        if ($attemptobj->get_state() == quiz_attempt::OVERDUE) {
            $message = get_string('overduemustbesubmittedby', 'quiz', userdate($duedate));

        } else if ($duedate) {
            $message = get_string('mustbesubmittedby', 'quiz', userdate($duedate));
        }

        $output .= $this->countdown_timer($attemptobj, time());
        $output .= $this->container($message . $this->container(
                $this->render($button), 'controls'), 'submitbtns mdl-align');

        return $output;
    }

    /*
     * View Page
     */
    /**
     * Generates the view page
     *
     * @param int $course The id of the course
     * @param array $quiz Array conting quiz data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     * @param array $infomessages information about this quiz
     * @param mod_quiz_view_object $viewobj
     * @param string $buttontext text for the start/continue attempt button, if
     *      it should be shown.
     * @param array $infomessages further information about why the student cannot
     *      attempt this quiz now, if appicable this quiz
     */
    public function view_page($course, $quiz, $cm, $context, $viewobj) {
        $output = '';
        $output .= $this->view_information($quiz, $cm, $context, $viewobj->infomessages);
        $output .= $this->view_table($quiz, $context, $viewobj);
        $output .= $this->view_result_info($quiz, $context, $cm, $viewobj);
        $output .= $this->box($this->view_page_buttons($viewobj), 'quizattempt');
        return $output;
    }

    /**
     * Work out, and render, whatever buttons, and surrounding info, should appear
     * at the end of the review page.
     * @param mod_quiz_view_object $viewobj the information required to display
     * the view page.
     * @return string HTML to output.
     */
    public function view_page_buttons(mod_quiz_view_object $viewobj) {
        global $CFG;
        $output = '';

        if (!$viewobj->quizhasquestions) {
            $output .= $this->no_questions_message($viewobj->canedit, $viewobj->editurl);
        }

        $output .= $this->access_messages($viewobj->preventmessages);

        if ($viewobj->buttontext) {
            $output .= $this->start_attempt_button($viewobj->buttontext,
                    $viewobj->startattempturl, $viewobj->startattemptwarning,
                    $viewobj->popuprequired, $viewobj->popupoptions);

        }

        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl,
                    get_string('backtocourse', 'quiz'), 'get',
                    array('class' => 'continuebutton'));
        }

        return $output;
    }

    /**
     * Generates the view attempt button
     *
     * @param int $course The course ID
     * @param array $quiz Array containging quiz date
     * @param int $cm The Course Module ID
     * @param int $context The page Context ID
     * @param mod_quiz_view_object $viewobj
     * @param string $buttontext
     */
    public function start_attempt_button($buttontext, moodle_url $url,
            $startattemptwarning, $popuprequired, $popupoptions) {

        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';

        $warning = '';
        if ($popuprequired) {
            $this->page->requires->js_module(quiz_get_js_module());
            $this->page->requires->js('/mod/quiz/module.js');
            $popupaction = new popup_action('click', $url, 'quizpopup', $popupoptions);

            $button->class .= ' quizsecuremoderequired';
            $button->add_action(new component_action('click',
                    'M.mod_quiz.secure_window.start_attempt_action', array(
                        'url' => $url->out(false),
                        'windowname' => 'quizpopup',
                        'options' => $popupaction->get_js_options(),
                        'fullscreen' => true,
                        'startattemptwarning' => $startattemptwarning,
                    )));

            $warning = html_writer::tag('noscript', $this->heading(get_string('noscript', 'quiz')));

        } else if ($startattemptwarning) {
            $button->add_action(new confirm_action($startattemptwarning, null,
                    get_string('startattempt', 'quiz')));
        }

        return $this->render($button) . $warning;
    }

    /**
     * Generate a message saying that this quiz has no questions, with a button to
     * go to the edit page, if the user has the right capability.
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param object $context the quiz context.
     * @return string HTML to output.
     */
    public function no_questions_message($canedit, $editurl) {
        $output = '';
        $output .= $this->notification(get_string('noquestions', 'quiz'));
        if ($canedit) {
            $output .= $this->single_button($editurl, get_string('editquiz', 'quiz'), 'get');
        }

        return $output;
    }

    /**
     * Outputs an error message for any guests accessing the quiz
     *
     * @param int $course The course ID
     * @param array $quiz Array contingin quiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_guest($course, $quiz, $cm, $context, $messages) {
        $output = '';
        $output .= $this->view_information($quiz, $cm, $context, $messages);
        $guestno = html_writer::tag('p', get_string('guestsno', 'quiz'));
        $liketologin = html_writer::tag('p', get_string('liketologin'));
        $output .= $this->confirm($guestno."\n\n".$liketologin."\n", get_login_url(),
                get_referer(false));
        return $output;
    }

    /**
     * Outputs and error message for anyone who is not enrolle don the course
     *
     * @param int $course The course ID
     * @param array $quiz Array contingin quiz data
     * @param int $cm Course Module ID
     * @param int $context The page contect ID
     * @param array $messages Array containing any messages
     */
    public function view_page_notenrolled($course, $quiz, $cm, $context, $messages) {
        global $CFG;
        $output = '';
        $output .= $this->view_information($quiz, $cm, $context, $messages);
        $youneedtoenrol = html_writer::tag('p', get_string('youneedtoenrol', 'quiz'));
        $button = html_writer::tag('p',
                $this->continue_button($CFG->wwwroot . '/course/view.php?id=' . $course->id));
        $output .= $this->box($youneedtoenrol."\n\n".$button."\n", 'generalbox', 'notice');
        return $output;
    }

    /**
     * Output the page information
     *
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param object $context the quiz context.
     * @param array $messages any access messages that should be described.
     * @return string HTML to output.
     */
    public function view_information($quiz, $cm, $context, $messages) {
        global $CFG;

        $output = '';

        // Print quiz name and description.
        $output .= $this->heading(format_string($quiz->name));
        $output .= $this->quiz_intro($quiz, $cm);

        // Output any access messages.
        if ($messages) {
            $output .= $this->box($this->access_messages($messages), 'quizinfo');
        }

        // Show number of attempts summary to those who can view reports.
        if (has_capability('mod/quiz:viewreports', $context)) {
            if ($strattemptnum = $this->quiz_attempt_summary_link_to_reports($quiz, $cm,
                    $context)) {
                $output .= html_writer::tag('div', $strattemptnum,
                        array('class' => 'quizattemptcounts'));
            }
        }
        return $output;
    }

    /**
     * Output the quiz intro.
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @return string HTML to output.
     */
    public function quiz_intro($quiz, $cm) {
        if (html_is_blank($quiz->intro)) {
            return '';
        }

        return $this->box(format_module_intro('quiz', $quiz, $cm->id), 'generalbox', 'intro');
    }

    /**
     * Generates the table heading.
     */
    public function view_table_heading() {
        return $this->heading(get_string('summaryofattempts', 'quiz'), 3);
    }

    /**
     * Generates the table of data
     *
     * @param array $quiz Array contining quiz data
     * @param int $context The page context ID
     * @param mod_quiz_view_object $viewobj
     */
    public function view_table($quiz, $context, $viewobj) {
        if (!$viewobj->attempts) {
            return '';
        }

        // Prepare table header.
        $table = new html_table();
        $table->attributes['class'] = 'generaltable quizattemptsummary';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        if ($viewobj->attemptcolumn) {
            $table->head[] = get_string('attemptnumber', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        $table->head[] = get_string('attemptstate', 'quiz');
        $table->align[] = 'left';
        $table->size[] = '';
        if ($viewobj->markcolumn) {
            $table->head[] = get_string('marks', 'quiz') . ' / ' .
                    quiz_format_grade($quiz, $quiz->sumgrades);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->gradecolumn) {
            $table->head[] = get_string('grade') . ' / ' .
                    quiz_format_grade($quiz, $quiz->grade);
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->canreviewmine) {
            $table->head[] = get_string('review', 'quiz');
            $table->align[] = 'center';
            $table->size[] = '';
        }
        if ($viewobj->feedbackcolumn) {
            $table->head[] = get_string('feedback', 'quiz');
            $table->align[] = 'left';
            $table->size[] = '';
        }

        // One row for each attempt.
        foreach ($viewobj->attemptobjs as $attemptobj) {
            $attemptoptions = $attemptobj->get_display_options(true);
            $row = array();

            // Add the attempt number.
            if ($viewobj->attemptcolumn) {
                if ($attemptobj->is_preview()) {
                    $row[] = get_string('preview', 'quiz');
                } else {
                    $row[] = $attemptobj->get_attempt_number();
                }
            }

            $row[] = $this->attempt_state($attemptobj);

            if ($viewobj->markcolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {
                    $row[] = quiz_format_grade($quiz, $attemptobj->get_sum_marks());
                } else {
                    $row[] = '';
                }
            }

            // Ouside the if because we may be showing feedback but not grades.
            $attemptgrade = quiz_rescale_grade($attemptobj->get_sum_marks(), $quiz, false);

            if ($viewobj->gradecolumn) {
                if ($attemptoptions->marks >= question_display_options::MARK_AND_MAX &&
                        $attemptobj->is_finished()) {

                    // Highlight the highest grade if appropriate.
                    if ($viewobj->overallstats && !$attemptobj->is_preview()
                            && $viewobj->numattempts > 1 && !is_null($viewobj->mygrade)
                            && $attemptobj->get_state() == quiz_attempt::FINISHED
                            && $attemptgrade == $viewobj->mygrade
                            && $quiz->grademethod == QUIZ_GRADEHIGHEST) {
                        $table->rowclasses[$attemptobj->get_attempt_number()] = 'bestrow';
                    }

                    $row[] = quiz_format_grade($quiz, $attemptgrade);
                } else {
                    $row[] = '';
                }
            }

            if ($viewobj->canreviewmine) {
                $row[] = $viewobj->accessmanager->make_review_link($attemptobj->get_attempt(),
                        $attemptoptions, $this);
            }

            if ($viewobj->feedbackcolumn && $attemptobj->is_finished()) {
                if ($attemptoptions->overallfeedback) {
                    $row[] = quiz_feedback_for_grade($attemptgrade, $quiz, $context);
                } else {
                    $row[] = '';
                }
            }

            if ($attemptobj->is_preview()) {
                $table->data['preview'] = $row;
            } else {
                $table->data[$attemptobj->get_attempt_number()] = $row;
            }
        } // End of loop over attempts.

        $output = '';
        $output .= $this->view_table_heading();
        $output .= html_writer::table($table);
        return $output;
    }

    /**
     * Generate a brief textual desciption of the current state of an attempt.
     * @param quiz_attempt $attemptobj the attempt
     * @param int $timenow the time to use as 'now'.
     * @return string the appropriate lang string to describe the state.
     */
    public function attempt_state($attemptobj) {
        switch ($attemptobj->get_state()) {
            case quiz_attempt::IN_PROGRESS:
                return get_string('stateinprogress', 'quiz');

            case quiz_attempt::OVERDUE:
                return get_string('stateoverdue', 'quiz') . html_writer::tag('span',
                        get_string('stateoverduedetails', 'quiz',
                                userdate($attemptobj->get_due_date())),
                        array('class' => 'statedetails'));

            case quiz_attempt::FINISHED:
                return get_string('statefinished', 'quiz') . html_writer::tag('span',
                        get_string('statefinisheddetails', 'quiz',
                                userdate($attemptobj->get_submitted_date())),
                        array('class' => 'statedetails'));

            case quiz_attempt::ABANDONED:
                return get_string('stateabandoned', 'quiz');
        }
    }

    /**
     * Generates data pertaining to quiz results
     *
     * @param array $quiz Array containing quiz data
     * @param int $context The page context ID
     * @param int $cm The Course Module Id
     * @param mod_quiz_view_object $viewobj
     */
    public function view_result_info($quiz, $context, $cm, $viewobj) {
        $output = '';
        if (!$viewobj->numattempts && !$viewobj->gradecolumn && is_null($viewobj->mygrade)) {
            return $output;
        }
        $resultinfo = '';

        if ($viewobj->overallstats) {
            if ($viewobj->moreattempts) {
                $a = new stdClass();
                $a->method = quiz_get_grading_option_name($quiz->grademethod);
                $a->mygrade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->quizgrade = quiz_format_grade($quiz, $quiz->grade);
                $resultinfo .= $this->heading(get_string('gradesofar', 'quiz', $a), 3);
            } else {
                $a = new stdClass();
                $a->grade = quiz_format_grade($quiz, $viewobj->mygrade);
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                $a = get_string('outofshort', 'quiz', $a);
                $resultinfo .= $this->heading(get_string('yourfinalgradeis', 'quiz', $a), 3);
            }
        }

        if ($viewobj->mygradeoverridden) {

            $resultinfo .= html_writer::tag('p', get_string('overriddennotice', 'grades'),
                    array('class' => 'overriddennotice'))."\n";
        }
        if ($viewobj->gradebookfeedback) {
            $resultinfo .= $this->heading(get_string('comment', 'quiz'), 3);
            $resultinfo .= html_writer::div($viewobj->gradebookfeedback, 'quizteacherfeedback') . "\n";
        }
        if ($viewobj->feedbackcolumn) {
            $resultinfo .= $this->heading(get_string('overallfeedback', 'quiz'), 3);
            $resultinfo .= html_writer::div(
                    quiz_feedback_for_grade($viewobj->mygrade, $quiz, $context),
                    'quizgradefeedback') . "\n";
        }

        if ($resultinfo) {
            $output .= $this->box($resultinfo, 'generalbox', 'feedback');
        }
        return $output;
    }

    /**
     * Output either a link to the review page for an attempt, or a button to
     * open the review in a popup window.
     *
     * @param moodle_url $url of the target page.
     * @param bool $reviewinpopup whether a pop-up is required.
     * @param array $popupoptions options to pass to the popup_action constructor.
     * @return string HTML to output.
     */
    public function review_link($url, $reviewinpopup, $popupoptions) {
        if ($reviewinpopup) {
            $button = new single_button($url, get_string('review', 'quiz'));
            $button->add_action(new popup_action('click', $url, 'quizpopup', $popupoptions));
            return $this->render($button);

        } else {
            return html_writer::link($url, get_string('review', 'quiz'),
                    array('title' => get_string('reviewthisattempt', 'quiz')));
        }
    }

    /**
     * Displayed where there might normally be a review link, to explain why the
     * review is not available at this time.
     * @param string $message optional message explaining why the review is not possible.
     * @return string HTML to output.
     */
    public function no_review_message($message) {
        return html_writer::nonempty_tag('span', $message,
                array('class' => 'noreviewmessage'));
    }

    /**
     * Returns the same as {@link quiz_num_attempt_summary()} but wrapped in a link
     * to the quiz reports.
     *
     * @param object $quiz the quiz object. Only $quiz->id is used at the moment.
     * @param object $cm the cm object. Only $cm->course, $cm->groupmode and $cm->groupingid
     * fields are used at the moment.
     * @param object $context the quiz context.
     * @param bool $returnzero if false (default), when no attempts have been made '' is returned
     * instead of 'Attempts: 0'.
     * @param int $currentgroup if there is a concept of current group where this method is being
     * called
     *         (e.g. a report) pass it in here. Default 0 which means no current group.
     * @return string HTML fragment for the link.
     */
    public function quiz_attempt_summary_link_to_reports($quiz, $cm, $context,
                                                          $returnzero = false, $currentgroup = 0) {
        global $CFG;
        $summary = quiz_num_attempt_summary($quiz, $cm, $returnzero, $currentgroup);
        if (!$summary) {
            return '';
        }

        require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
        $url = new moodle_url('/mod/quiz/report.php', array(
                'id' => $cm->id, 'mode' => quiz_report_default_report($context)));
        return html_writer::link($url, $summary);
    }

    /**
     * Output a graph, or a message saying that GD is required.
     * @param moodle_url $url the URL of the graph.
     * @param string $title the title to display above the graph.
     * @return string HTML fragment for the graph.
     */
    public function graph(moodle_url $url, $title) {
        global $CFG;

        $graph = html_writer::empty_tag('img', array('src' => $url, 'alt' => $title));

        return $this->heading($title, 3) . html_writer::tag('div', $graph, array('class' => 'graph'));
    }

    /**
     * Output the connection warning messages, which are initially hidden, and
     * only revealed by JavaScript if necessary.
     */
    public function connection_warning() {
        $options = array('filter' => false, 'newlines' => false);
        $warning = format_text(get_string('connectionerror', 'quiz'), FORMAT_MARKDOWN, $options);
        $ok = format_text(get_string('connectionok', 'quiz'), FORMAT_MARKDOWN, $options);
        return html_writer::tag('div', $warning, array('id' => 'connection-error', 'style' => 'display: none;', 'role' => 'alert')) .
                html_writer::tag('div', $ok, array('id' => 'connection-ok', 'style' => 'display: none;', 'role' => 'alert'));
    }

    /**
     * Renders html to display a name with the link to the question on a quiz edit page
     *
     * If question is unavailable for the user but still needs to be displayed
     * in the list, just the name is returned without a link
     *
     * Note, that for question that never have separate pages (i.e. labels)
     * this function returns an empty string
     *
     * @param question $question
     * @param array $displayoptions
     * @return string
     */
    public function quiz_section_question_name($quiz, $question, $displayoptions = array()) {
        global $CFG;
        $output = '';
//         if (!$question->uservisible &&
//                 (empty($question->showavailability) || empty($question->availableinfo))) {
//             // nothing to be displayed to the user
//             return $output;
//         }
        $url = '';
        $url = mod_quiz_renderer::quiz_question_get_url($quiz, $question);

//         $this->url = $modviews[$this->modname]
//                 ? new moodle_url('/mod/' . $this->modname . '/view.php', array('id'=>$this->id))
//                 : null;
        if (!$url) {
            return $output;
        }

        //Accessibility: for files get description via icon, this is very ugly hack!
        $instancename = quiz_question_tostring($question);
        $altname = $question->name;
        // Avoid unnecessary duplication: if e.g. a forum name already
        // includes the word forum (or Forum, etc) then it is unhelpful
        // to include that in the accessible description that is added.
        if (false !== strpos(core_text::strtolower($instancename),
                core_text::strtolower($altname))) {
            $altname = '';
        }
        // File type after name, for alphabetic lists (screen reader).
        if ($altname) {
            $altname = get_accesshide(' '.$altname);
        }

        // For items which are hidden but available to current user
        // ($question->uservisible), we show those as dimmed only if the user has
        // viewhiddenactivities, so that teachers see 'items which might not
        // be available to some students' dimmed but students do not see 'item
        // which is actually available to current student' dimmed.
        $linkclasses = '';
        $accesstext = '';
        $textclasses = '';
//         if ($question->uservisible) {
//             $conditionalhidden = $this->is_question_conditionally_hidden($question);
//             $accessiblebutdim = (!$question->visible || $conditionalhidden) &&
//                 has_capability('moodle/course:viewhiddenactivities',
//                         context_course::instance($question->course));
//             if ($accessiblebutdim) {
//                 $linkclasses .= ' dimmed';
//                 $textclasses .= ' dimmed_text';
//                 if ($conditionalhidden) {
//                     $linkclasses .= ' conditionalhidden';
//                     $textclasses .= ' conditionalhidden';
//                 }
//                 // Show accessibility note only if user can access the module himself.
//                 $accesstext = get_accesshide(get_string('hiddenfromstudents').':'. $question->modfullname);
//             }
//         } else {
//             $linkclasses .= ' dimmed';
//             $textclasses .= ' dimmed_text';
//         }

        // Get on-click attribute value if specified and decode the onclick - it
        // has already been encoded for display (puke).
        $onclick = ''; //htmlspecialchars_decode($question->get_on_click(), ENT_QUOTES);

        $groupinglabel = '';
//         if (!empty($question->groupingid) && has_capability('moodle/course:managegroups', context_course::instance($question->course))) {
//             $groupings = groups_get_all_groupings($question->course);
//             $groupinglabel = html_writer::tag('span', '('.format_string($groupings[$question->groupingid]->name).')',
//                     array('class' => 'groupinglabel '.$textclasses));
//         }

        $qtype = question_bank::get_qtype($question->qtype, false);
        $namestr = $qtype->local_name();

        $icon = $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr,
                'class' => 'iconlarge activityicon', 'alt' => ' ', 'role' => 'presentation'));
        // Display link itself.
        $activitylink = $icon . $accesstext .
                html_writer::tag('span', $instancename . $altname, array('class' => 'instancename'));
//         if ($question->uservisible) {
            $output .= html_writer::link($url, $activitylink, array('class' => $linkclasses, 'onclick' => $onclick)) .
                    $groupinglabel;
//         }
//         else {
//             // We may be displaying this just in order to show information
//             // about visibility, without the actual link ($question->uservisible)
//             $output .= html_writer::tag('div', $activitylink, array('class' => $textclasses)) .
//                     $groupinglabel;
//         }
        return $output;
    }

    /**
     * Renders HTML to display one question in a quiz section
     *
     * This includes link, content, availability, completion info and additional information
     * that module type wants to display (i.e. number of unread forum posts)
     *
     * This function calls:
     * {@link mod_quiz_renderer::quiz_section_question_name()}
     * {@link cm_info::get_after_link()}
     * {@link mod_quiz_renderer::quiz_section_question_text()}
     * {@link core_course_renderer::course_section_question_availability()}
     * {@link core_course_renderer::course_section_question_completion()}
     * {@link question_get_question_edit_actions()}
     * {@link mod_quiz_renderer::quiz_section_question_edit_actions()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $question
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return string
     */
    public function quiz_section_question($quiz, $course, &$completioninfo, $question, $sectionreturn, $displayoptions = array()) {
        $output = '';
        // We return empty string (because quiz question will not be displayed at all)
        // if:
        // 1) The activity is not visible to users
        // and
        // 2a) The 'showavailability' option is not set (if that is set,
        //     we need to display the activity so we can show
        //     availability info)
        // or
        // 2b) The 'availableinfo' is empty, i.e. the activity was
        //     hidden in a way that leaves no info, such as using the
        //     eye icon.
//         if (!$question->uservisible &&
//             (empty($question->showavailability) || empty($question->availableinfo))) {
//             return $output;
//         }

        $indentclasses = 'mod-indent';
        if (!empty($question->indent)) {
            $indentclasses .= ' mod-indent-'.$question->indent;
            if ($question->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        // Print slot number.
        // TODO: We have to write a function to deal with description questions.
        // Currently there is a functionality that translates the slotnumber of a
        // description question to 'i' for information. That could be confusing when
        // you have lots of description questions, in particular if you have consecative
        // description question types. We can either have the slot number prefixes the 'i'
        // or use 'i' followed by a number (when morethan one) which can be incremented.
        $slotnumber = $this->get_question_info($quiz, $question->id, 'slot');

        if ($this->page->user_is_editing()) {
            $output .= quiz_get_question_move($question, $sectionreturn);
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer'));
        $output .= html_writer::tag('span', $slotnumber, array('class' => 'slotnumber'));

        // This div is used to indent the content.
        $output .= html_writer::div('', $indentclasses);

        // Start a wrapper for the actual content to keep the indentation consistent
        $output .= html_writer::start_tag('div');

        // Display the link to the question (or do nothing if question has no url)
        $cmname = $this->quiz_section_question_name($quiz, $question, $displayoptions);

        if (!empty($cmname)) {
            // Start the div for the activity title, excluding the edit icons.
            $output .= html_writer::start_tag('div', array('class' => 'activityinstance'));
            $output .= $cmname;



            // Module can put text after the link (e.g. forum unread)
//             $output .= $question->get_after_link();

            $output .= quiz_question_preview_button($quiz, $question);

            $output .= quiz_question_marked_out_of_field($quiz, $question);

            if ($this->page->user_is_editing()) {
                $output .= ' ' . quiz_get_question_regrade_action($question, $sectionreturn);
            }

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        $questionicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = quiz_get_question_edit_actions($quiz, $question, null, $sectionreturn);
            $questionicons .= ' '. $this->quiz_section_question_edit_actions($editactions, $question, $displayoptions);
            $questionicons .= ' '. $this->quiz_add_menu_actions($quiz, $question);
//             $questionicons .= $question->get_after_edit_icons();

            $output .= html_writer::span($questionicons, 'actions');
        }

        // show availability info (if module is not available)
//         $output .= $this->course_section_question_availability($question, $displayoptions);

        $output .= html_writer::end_tag('div'); // $indentclasses

        // End of indentation div.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');

        return $output;
    }

    public function quiz_add_menu_actions($quiz, $question) {
        global $CFG;

        $actions = quiz_get_edit_menu_actions($quiz, $question);
        if (empty($actions)) {
            return '';
        }
        $menu = new action_menu();
        $menu->set_alignment(action_menu::BR, action_menu::BR);
        $menu->set_menu_trigger(html_writer::tag('span', 'add', array('class' => 'add-menu')));// TODO: To be replace by an icon

        foreach ($actions as $action) {
            if ($action instanceof action_menu_link) {
                $action->add_class('add-menu');
            }
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' section-cm-edit-actions commands';

        // Prioritise the menu ahead of all other actions.
        $menu->prioritise = true;

        return $this->render($menu);
    }

    /**
     * Renders HTML for displaying the sequence of quiz question editing buttons
     *
     * @see quiz_get_question_edit_actions()
     *
     * @param action_link[] $actions Array of action_link objects
     * @param $question The question we are displaying actions for.
     * @param array $displayoptions additional display options:
     *     ownerselector => A JS/CSS selector that can be used to find an cm node.
     *         If specified the owning node will be given the class 'action-menu-shown' when the action
     *         menu is being displayed.
     *     constraintselector => A JS/CSS selector that can be used to find the parent node for which to constrain
     *         the action menu to when it is being displayed.
     *     donotenhance => If set to true the action menu that gets displayed won't be enhanced by JS.
     * @return string
     */
    public function quiz_section_question_edit_actions($actions, $question = null, $displayoptions = array()) {
        global $CFG;

        if (empty($actions)) {
            return '';
        }

        if (isset($displayoptions['ownerselector'])) {
            $ownerselector = $displayoptions['ownerselector'];
        } else if ($question) {
            $ownerselector = '#module-'.$question->id;
        } else {
            debugging('You should upgrade your call to '.__FUNCTION__.' and provide $question', DEBUG_DEVELOPER);
            $ownerselector = 'li.activity';
        }

        if (isset($displayoptions['constraintselector'])) {
            $constraint = $displayoptions['constraintselector'];
        } else {
            $constraint = '.course-content';
        }

        $menu = new action_menu();
        $menu->set_owner_selector($ownerselector);
        $menu->set_constraint($constraint);
        $menu->set_alignment(action_menu::TR, action_menu::BR);
        $menu->set_menu_trigger(get_string('edit'));
        if (isset($CFG->modeditingmenu) && !$CFG->modeditingmenu || !empty($displayoptions['donotenhance'])) {
            $menu->do_not_enhance();

            // Swap the left/right icons.
            // Normally we have have right, then left but this does not
            // make sense when modactionmenu is disabled.
            $moveright = null;
            $_actions = array();
            foreach ($actions as $key => $value) {
                if ($key === 'moveright') {

                    // Save moveright for later.
                    $moveright = $value;
                } else if ($moveright) {

                    // This assumes that the order was moveright, moveleft.
                    // If we have a moveright, then we should place it immediately after the current value.
                    $_actions[$key] = $value;
                    $_actions['moveright'] = $moveright;

                    // Clear the value to prevent it being used multiple times.
                    $moveright = null;
                } else {

                    $_actions[$key] = $value;
                }
            }
            $actions = $_actions;
            unset($_actions);
        }
        foreach ($actions as $action) {
            if ($action instanceof action_menu_link) {
                $action->add_class('cm-edit-action');
            }
            $menu->add($action);
        }
        $menu->attributes['class'] .= ' section-cm-edit-actions commands';

        // Prioritise the menu ahead of all other actions.
        $menu->prioritise = true;

        return $this->render($menu);
    }

    static public function quiz_question_get_url($quiz, $question) {
        global $PAGE;
        $questionparams = array(
                        'returnurl' => $PAGE->url->out_as_local_url(),
                        'cmid' => $quiz->cmid,
                        'id' => $question->id);
        $url = new moodle_url('/question/question.php', $questionparams);
        return $url;
    }

    /**
     * Renders HTML to display one course module for display within a section.
     *
     * This function calls:
     * {@link core_course_renderer::quiz_section_question()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $question
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return String
     */
    public function quiz_section_question_list_item($quiz, $course, &$completioninfo, $question, $sectionreturn, $displayoptions = array()) {
        $output = '';
        $slotid = $this->get_question_info($quiz, $question->id, 'slotid');
        $slotnumber = $this->get_question_info($quiz, $question->id, 'slot');
        $pagenumber = $this->get_question_info($quiz, $question->id, 'page');
        $page = $pagenumber ? get_string('page') . ' ' . $pagenumber : null;
        $Pagenumbercss = ''; // TODO: to add appropriate CSS here
        $prevpage = $this->get_previous_page($quiz, $slotnumber -1);
        if ($prevpage != $pagenumber) {
            $output .= html_writer::tag('div', $page,  array('class' => $Pagenumbercss));
        }

        if ($questiontypehtml = $this->quiz_section_question($quiz, $course, $completioninfo, $question, $sectionreturn, $displayoptions)) {
            $questionclasses = 'activity ' . $question->qtype . 'qtype_' . $question->qtype;
            $output .= html_writer::tag('li', $questiontypehtml, array('class' => $questionclasses, 'id' => 'module-' . $slotid));
        }
        return $output;
    }

    /**
     * Renders HTML to display a list of course modules in a course section
     * Also displays "move here" controls in Javascript-disabled mode
     *
     * This function calls {@link core_course_renderer::quiz_section_question()}
     *
     * @param stdClass $course course object
     * @param int|stdClass|section_info $section relative section number or section object
     * @param int $sectionreturn section number to return to
     * @param int $displayoptions
     * @return void
     */
    public function quiz_section_question_list($quiz, $course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $USER;

        $output = '';
//         $modinfo = get_fast_modinfo($course);
//         $questions = array();
//         if (is_object($section)) {
//             $section = $questions->get_section_info($section->section);
//         } else {
//             $section = $questions->get_section_info($section);
//         }
//         $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $movingpix = new pix_icon('movehere', get_string('movehere'), 'moodle', array('class' => 'movetarget'));
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of question types visible to user (excluding the question type being moved if there is one)
        $questionshtml = array();
        //$sections = explode(',', $quiz->questions);
        $slots = \mod_quiz\structure::get_quiz_slots($quiz);
        $sectiontoslotids = $quiz->sectiontoslotids;
        if (!empty($sectiontoslotids[$section->id])) {
            /*
             * During prototyping questions and sections are treated as the same thing.
             * When the underlying data structure and related classes support sections
             * these can be properly implemented here.
             */
//             foreach ($sections[$section->section] as $questionnumber => $questionid) {
            foreach ($sectiontoslotids[$section->id] as $slotid) {
                $slot =  $slots[$slotid];
                $questionnumber = $slot->questionid;
                $question = $quiz->fullquestions[$questionnumber];

                if ($ismoving and $question->id == $USER->activitycopy) {
                    // do not display moving question type
                    continue;
                }

                if ($questiontypehtml = $this->quiz_section_question_list_item($quiz, $course,
                        $completioninfo, $question, $sectionreturn, $displayoptions)) {
                    $questionshtml[$questionnumber] = $questiontypehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($questionshtml) || $ismoving) {
            foreach ($questionshtml as $questionnumber => $questiontypehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/quiz/edit.php', array('moveto' => $questionnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag('li', html_writer::link($movingurl, $this->output->render($movingpix)),
                            array('class' => 'movehere', 'title' => $strmovefull));
                }
                $sectionoutput .= $questiontypehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/quiz/edit.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag('li', html_writer::link($movingurl, $this->output->render($movingpix)),
                        array('class' => 'movehere', 'title' => $strmovefull));
            }
        }

        // Always output the section module list.
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        return $output;
    }

    /**
     * Renders HTML for the menus to add activities and resources to the current course
     *
     * Note, if theme overwrites this function and it does not use modchooser,
     * see also {@link core_course_renderer::add_modchoosertoggle()}
     *
     * @param stdClass $course
     * @param int $section relative section number (field quiz_sections.section)
     * @param int $sectionreturn The section to link back to
     * @param array $displayoptions additional display options, for example blocks add
     *     option 'inblock' => true, suggesting to display controls vertically
     * @return string
     */
    function quiz_section_add_question_control($quiz, $course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $CFG, $PAGE;

        $vertical = !empty($displayoptions['inblock']);

        $hasmanagequiz = has_capability('mod/quiz:manage', $PAGE->cm->context);
        // check to see if user can add menus and there are modules to add
        if (!has_capability('mod/quiz:manage', $PAGE->cm->context)
                || !$this->page->user_is_editing()
                || !($questionnames = get_module_types_names()) || empty($questionnames)) {
            return '';
        }

        // Retrieve all modules with associated metadata
        $questions = get_module_metadata($course, $questionnames, $sectionreturn);
        $urlparams = array('section' => $section);

        // We'll sort resources and activities into two lists
        $activities = array(MOD_CLASS_ACTIVITY => array(), MOD_CLASS_RESOURCE => array());

        foreach ($questions as $question) {
            if (isset($question->types)) {
                // This module has a subtype
                // NOTE: this is legacy stuff, module subtypes are very strongly discouraged!!
                $subtypes = array();
                foreach ($question->types as $subtype) {
                    $link = $subtype->link->out(true, $urlparams);
                    $subtypes[$link] = $subtype->title;
                }

                // Sort module subtypes into the list
                $activityclass = MOD_CLASS_ACTIVITY;
                if ($question->archetype == MOD_CLASS_RESOURCE) {
                    $activityclass = MOD_CLASS_RESOURCE;
                }
                if (!empty($question->title)) {
                    // This grouping has a name
                    $activities[$activityclass][] = array($question->title => $subtypes);
                } else {
                    // This grouping does not have a name
                    $activities[$activityclass] = array_merge($activities[$activityclass], $subtypes);
                }
            } else {
                // This module has no subtypes
                $activityclass = MOD_CLASS_ACTIVITY;
                if ($question->archetype == MOD_ARCHETYPE_RESOURCE) {
                    $activityclass = MOD_CLASS_RESOURCE;
                } else if ($question->archetype === MOD_ARCHETYPE_SYSTEM) {
                    // System modules cannot be added by user, do not add to dropdown
                    continue;
                }
                $link = $question->link->out(true, $urlparams);
                $activities[$activityclass][$link] = $question->title;
            }
        }

        $straddactivity = get_string('addactivity');
        $straddresource = get_string('addresource');
        $sectionname = get_section_name($course, $section);
        $strresourcelabel = get_string('addresourcetosection', null, $sectionname);
        $stractivitylabel = get_string('addactivitytosection', null, $sectionname);

        $output = html_writer::start_tag('div', array('class' => 'section_add_menus', 'id' => 'add_menus-section-' . $section->id));

        if (!$vertical) {
            $output .= html_writer::start_tag('div', array('class' => 'horizontal'));
        }

        if (!empty($activities[MOD_CLASS_RESOURCE])) {
            $select = new url_select($activities[MOD_CLASS_RESOURCE], '', array(''=>$straddresource), "ressection$section");
            $select->set_help_icon('resources');
            $select->set_label($strresourcelabel, array('class' => 'accesshide'));
            $output .= $this->output->render($select);
        }

        if (!empty($activities[MOD_CLASS_ACTIVITY])) {
            $select = new url_select($activities[MOD_CLASS_ACTIVITY], '', array(''=>$straddactivity), "section$section");
            $select->set_help_icon('activities');
            $select->set_label($stractivitylabel, array('class' => 'accesshide'));
            $output .= $this->output->render($select);
        }

        if (!$vertical) {
            $output .= html_writer::end_tag('div');
        }

        $output .= html_writer::end_tag('div');

        if (course_ajax_enabled($course) && $course->id == $this->page->course->id) {
            // modchooser can be added only for the current course set on the page!
            $straddeither = get_string('addresourceoractivity');
            // The module chooser link
            $modchooser = html_writer::start_tag('div', array('class' => 'mdl-right'));
            $modchooser.= html_writer::start_tag('div', array('class' => 'section-modchooser'));
            $icon = $this->output->pix_icon('t/add', '');
            $span = html_writer::tag('span', $straddeither, array('class' => 'section-modchooser-text'));
            $modchooser .= html_writer::tag('span', $icon . $span, array('class' => 'section-modchooser-link'));
            $modchooser.= html_writer::end_tag('div');
            $modchooser.= html_writer::end_tag('div');

            // Wrap the normal output in a noscript div
            $usemodchooser = get_user_preferences('usemodchooser', $CFG->modchooserdefault);
            if ($usemodchooser) {
                $output = html_writer::tag('div', $output, array('class' => 'hiddenifjs addresourcedropdown'));
                $modchooser = html_writer::tag('div', $modchooser, array('class' => 'visibleifjs addresourcemodchooser'));
            } else {
                // If the module chooser is disabled, we need to ensure that the dropdowns are shown even if javascript is disabled
                $output = html_writer::tag('div', $output, array('class' => 'show addresourcedropdown'));
                $modchooser = html_writer::tag('div', $modchooser, array('class' => 'hide addresourcemodchooser'));
            }
            $output = $this->course_modchooser($questions, $course) . $modchooser . $output;
        }

        return $output;
    }

/**
     * Build the HTML for the module chooser javascript popup
     *
     * @param array $modules A set of modules as returned form @see
     * get_module_metadata
     * @param object $course The course that will be displayed
     * @return string The composed HTML for the module
     */
    public function course_modchooser($modules, $course) {
        static $isdisplayed = false;
        if ($isdisplayed) {
            return '';
        }
        $isdisplayed = true;

        // Add the module chooser
        $this->page->requires->yui_module('moodle-course-modchooser',
        'M.course.init_chooser',
        array(array('courseid' => $course->id, 'closeButtonTitle' => get_string('close', 'editor')))
        );
        $this->page->requires->strings_for_js(array(
                'addresourceoractivity',
                'modchooserenable',
                'modchooserdisable',
        ), 'moodle');

        // Add the header
        $header = html_writer::tag('div', get_string('addresourceoractivity', 'moodle'),
                array('class' => 'hd choosertitle'));

        $formcontent = html_writer::start_tag('form', array('action' => new moodle_url('/course/jumpto.php'),
                'id' => 'chooserform', 'method' => 'post'));
        $formcontent .= html_writer::start_tag('div', array('id' => 'typeformdiv'));
        $formcontent .= html_writer::tag('input', '', array('type' => 'hidden', 'id' => 'course',
                'name' => 'course', 'value' => $course->id));
        $formcontent .= html_writer::tag('input', '',
                array('type' => 'hidden', 'class' => 'jump', 'name' => 'jump', 'value' => ''));
        $formcontent .= html_writer::tag('input', '', array('type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()));
        $formcontent .= html_writer::end_tag('div');

        // Put everything into one tag 'options'
        $formcontent .= html_writer::start_tag('div', array('class' => 'options'));
        $formcontent .= html_writer::tag('div', get_string('selectmoduletoviewhelp', 'moodle'),
                array('class' => 'instruction'));
        // Put all options into one tag 'alloptions' to allow us to handle scrolling
        $formcontent .= html_writer::start_tag('div', array('class' => 'alloptions'));

         // Activities
        $activities = array_filter($modules, create_function('$mod', 'return ($mod->archetype !== MOD_ARCHETYPE_RESOURCE && $mod->archetype !== MOD_ARCHETYPE_SYSTEM);'));
        if (count($activities)) {
            $formcontent .= $this->course_modchooser_title('activities');
            $formcontent .= $this->course_modchooser_module_types($activities);
        }

        // Resources
        $resources = array_filter($modules, create_function('$mod', 'return ($mod->archetype === MOD_ARCHETYPE_RESOURCE);'));
        if (count($resources)) {
            $formcontent .= $this->course_modchooser_title('resources');
            $formcontent .= $this->course_modchooser_module_types($resources);
        }

        $formcontent .= html_writer::end_tag('div'); // modoptions
        $formcontent .= html_writer::end_tag('div'); // types

        $formcontent .= html_writer::start_tag('div', array('class' => 'submitbuttons'));
        $formcontent .= html_writer::tag('input', '',
                array('type' => 'submit', 'name' => 'submitbutton', 'class' => 'submitbutton', 'value' => get_string('add')));
        $formcontent .= html_writer::tag('input', '',
                array('type' => 'submit', 'name' => 'addcancel', 'class' => 'addcancel', 'value' => get_string('cancel')));
        $formcontent .= html_writer::end_tag('div');
        $formcontent .= html_writer::end_tag('form');

        // Wrap the whole form in a div
        $formcontent = html_writer::tag('div', $formcontent, array('id' => 'chooseform'));

        // Put all of the content together
        $content = $formcontent;

        $content = html_writer::tag('div', $content, array('class' => 'choosercontainer'));
        return $header . html_writer::tag('div', $content, array('class' => 'chooserdialoguebody'));
    }

    /**
     * Build the HTML for a specified set of modules
     *
     * @param array $modules A set of modules as used by the
     * course_modchooser_module function
     * @return string The composed HTML for the module
     */
    protected function course_modchooser_module_types($modules) {
        $return = '';
        foreach ($modules as $module) {
            if (!isset($module->types)) {
                $return .= $this->course_modchooser_module($module);
            } else {
                $return .= $this->course_modchooser_module($module, array('nonoption'));
                foreach ($module->types as $type) {
                    $return .= $this->course_modchooser_module($type, array('option', 'subtype'));
                }
            }
        }
        return $return;
    }

    /**
     * Return the HTML for the specified module adding any required classes
     *
     * @param object $module An object containing the title, and link. An
     * icon, and help text may optionally be specified. If the module
     * contains subtypes in the types option, then these will also be
     * displayed.
     * @param array $classes Additional classes to add to the encompassing
     * div element
     * @return string The composed HTML for the module
     */
    protected function course_modchooser_module($module, $classes = array('option')) {
        $output = '';
        $output .= html_writer::start_tag('div', array('class' => implode(' ', $classes)));
        $output .= html_writer::start_tag('label', array('for' => 'module_' . $module->name));
        if (!isset($module->types)) {
            $output .= html_writer::tag('input', '', array('type' => 'radio',
                    'name' => 'jumplink', 'id' => 'module_' . $module->name, 'value' => $module->link));
        }

        $output .= html_writer::start_tag('span', array('class' => 'modicon'));
        if (isset($module->icon)) {
            // Add an icon if we have one
            $output .= $module->icon;
        }
        $output .= html_writer::end_tag('span');

        $output .= html_writer::tag('span', $module->title, array('class' => 'typename'));
        if (!isset($module->help)) {
            // Add help if found
            $module->help = get_string('nohelpforactivityorresource', 'moodle');
        }

        // Format the help text using markdown with the following options
        $options = new stdClass();
        $options->trusted = false;
        $options->noclean = false;
        $options->smiley = false;
        $options->filter = false;
        $options->para = true;
        $options->newlines = false;
        $options->overflowdiv = false;
        $module->help = format_text($module->help, FORMAT_MARKDOWN, $options);
        $output .= html_writer::tag('span', $module->help, array('class' => 'typesummary'));
        $output .= html_writer::end_tag('label');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    protected function course_modchooser_title($title, $identifier = null) {
        $module = new stdClass();
        $module->name = $title;
        $module->types = array();
        $module->title = get_string($title, $identifier);
        $module->help = '';
        return $this->course_modchooser_module($module, array('moduletypetitle'));
    }

    /**
     *
     * @param object $quiz
     * @param int $questionid
     * @return array, a list (sectionid, page-number, slot-number, maxmark)
     */
    protected function get_section($quiz, $sectionid) {
        if (!$sectionid) {
            // Possible, printout a notification or an error, but that should not happen.
            return null;
        }
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        if (!$sections) {
            return null;
        }
        Foreach ($sections as $key => $section) {
            if ((int)$section->id === (int)$sectionid) {
                return \mod_quiz\structure::get_quiz_section_heading($section);
            }
        }
        return null;
    }

    /**
     *
     * @param object $quiz
     * @param int $questionid
     * @param string, 'all' for returning list (sectionid, page-number, slot-number, maxmark),
     * 'section' for returning section heding, 'page' for returning page number,
     * 'slot' for returning slot-number and 'mark' for returning maxmark.
     * @return array, a list (sectionid, page-number, slot-number, maxmark), or the value for the given string
     */
    protected function get_question_info($quiz, $questionid, $info = 'all') {
        if (!$quiz->slots) {
            // Possible, printout a notification or an error, but that should not happen.
            return null;
        }
        // TODO: If slots are organised with keys as questionids, I could get rid of the loop.
        foreach ($quiz->slots as $slotid => $slot) {
            if ((int)$slot->questionid === (int)$questionid) {
                if ($info === 'all') {
                    return array($slot->sectionid, $slot->page, $slot->id, $slot->maxmark);
                }
                if ($info === 'section') {
                    return $this->get_section($quiz, $slot->sectionid);
                }
                if ($info === 'page') {
                    return $slot->page;
                }
                if ($info === 'slot') {
                    return $slot->slot;
                }
                if ($info === 'mark') {
                    return $slot->maxmark;
                }
                if ($info === 'slotid') {
                    return $slot->id;
                }
            }
        }
        return null;
    }

    protected function get_previous_page($quiz, $prevslotnumber) {
        if ($prevslotnumber < 1) {
            return 0;
        }
        foreach ($quiz->slots as $slotid => $slot) {
            if ($slot->slot == $prevslotnumber) {
                return $slot->page;
            }
        }
        return 0;
    }

}
class mod_quiz_links_to_other_attempts implements renderable {
    /**
     * @var array string attempt number => url, or null for the current attempt.
     */
    public $links = array();
}

class mod_quiz_view_object {
    /** @var array $infomessages of messages with information to display about the quiz. */
    public $infomessages;
    /** @var array $attempts contains all the user's attempts at this quiz. */
    public $attempts;
    /** @var array $attemptobjs quiz_attempt objects corresponding to $attempts. */
    public $attemptobjs;
    /** @var quiz_access_manager $accessmanager contains various access rules. */
    public $accessmanager;
    /** @var bool $canreviewmine whether the current user has the capability to
     *       review their own attempts. */
    public $canreviewmine;
    /** @var bool $canedit whether the current user has the capability to edit the quiz. */
    public $canedit;
    /** @var moodle_url $editurl the URL for editing this quiz. */
    public $editurl;
    /** @var int $attemptcolumn contains the number of attempts done. */
    public $attemptcolumn;
    /** @var int $gradecolumn contains the grades of any attempts. */
    public $gradecolumn;
    /** @var int $markcolumn contains the marks of any attempt. */
    public $markcolumn;
    /** @var int $overallstats contains all marks for any attempt. */
    public $overallstats;
    /** @var string $feedbackcolumn contains any feedback for and attempt. */
    public $feedbackcolumn;
    /** @var string $timenow contains a timestamp in string format. */
    public $timenow;
    /** @var int $numattempts contains the total number of attempts. */
    public $numattempts;
    /** @var float $mygrade contains the user's final grade for a quiz. */
    public $mygrade;
    /** @var bool $moreattempts whether this user is allowed more attempts. */
    public $moreattempts;
    /** @var int $mygradeoverridden contains an overriden grade. */
    public $mygradeoverridden;
    /** @var string $gradebookfeedback contains any feedback for a gradebook. */
    public $gradebookfeedback;
    /** @var bool $unfinished contains 1 if an attempt is unfinished. */
    public $unfinished;
    /** @var object $lastfinishedattempt the last attempt from the attempts array. */
    public $lastfinishedattempt;
    /** @var array $preventmessages of messages telling the user why they can't
     *       attempt the quiz now. */
    public $preventmessages;
    /** @var string $buttontext caption for the start attempt button. If this is null, show no
     *      button, or if it is '' show a back to the course button. */
    public $buttontext;
    /** @var string $startattemptwarning alert to show the user before starting an attempt. */
    public $startattemptwarning;
    /** @var moodle_url $startattempturl URL to start an attempt. */
    public $startattempturl;
    /** @var moodle_url $startattempturl URL for any Back to the course button. */
    public $backtocourseurl;
    /** @var bool $showbacktocourse should we show a back to the course button? */
    public $showbacktocourse;
    /** @var bool whether the attempt must take place in a popup window. */
    public $popuprequired;
    /** @var array options to use for the popup window, if required. */
    public $popupoptions;
    /** @var bool $quizhasquestions whether the quiz has any questions. */
    public $quizhasquestions;
}
