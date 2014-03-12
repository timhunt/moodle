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
 * Renderer outputting the quiz editing UI.
 *
 * @package mod_quiz
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Renderer outputting the quiz editing UI.
 *
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */
class mod_quiz_edit_renderer extends plugin_renderer_base {

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'slots'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('weeklyoutline');
    }

    /**
     * Generate the content to displayed on the right part of a section
     * before course modules are included
     *
     * @param stdClass $section The quiz_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_right_content($section, $course, $onsectionpage) {
        $o = $this->output->spacer();

        if ($section->firstslot > 1) {
            // TODO MDL-43089 what is this?
//             $controls = $this->section_edit_controls($course, $section, $onsectionpage);
//             if (!empty($controls)) {
//                 $o = implode('<br />', $controls);
//             }
        }

        return $o;
    }

    /**
     * Generate the content to be displayed on the left part of a section
     * before course modules are included
     *
     * @param stdClass $section The quiz_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_left_content($section) {
        $o = $this->output->spacer();

        return $o;
    }

    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The quiz_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->id,
            'class' => 'section main clearfix'.$sectionstyle, 'role'=>'region',
            'aria-label'=> $section->heading));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        return $o;
    }

    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        $o = html_writer::end_tag('div');
        $o.= html_writer::end_tag('li');

        return $o;
    }

    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The quiz_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) {

        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        return format_text($section->heading, FORMAT_MOODLE, $options);
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param stdClass $section The quiz_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    protected function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->uservisible) {
            $o .= html_writer::start_tag('div', array('class' => 'availabilityinfo'));
            // Note: We only get to this function if availableinfo is non-empty,
            // so there is definitely something to print.
            $o .= $section->availableinfo;
            $o .= html_writer::end_tag('div');
        } else if ($canviewhidden && !empty($CFG->enableavailability) && $section->visible) {
            $ci = new condition_info_section($section);
            $fullinfo = $ci->get_full_information();
            if ($fullinfo) {
                $o .= html_writer::start_tag('div', array('class' => 'availabilityinfo'));
                $o .= get_string(
                        ($section->showavailability ? 'userrestriction_visible' : 'userrestriction_hidden'),
                        'condition', $fullinfo);
                $o .= html_writer::end_tag('div');
            }
        }
        return $o;
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The quiz_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {

        if (!$this->page->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $baseurl = course_get_url($course, $section->section);
        } else {
            $baseurl = course_get_url($course);
        }
        $baseurl->param('sesskey', sesskey());

        $controls = array();

        $url = clone($baseurl);
            // TODO MDL-43089 ?
//         if (has_capability('moodle/course:sectionvisibility', $coursecontext)) {
//             if ($section->visible) { // Show the hide/show eye.
//                 $strhidefromothers = get_string('hidefromothers', 'format_'.$course->format);
//                 $url->param('hide', $section->section);
//                 $controls[] = html_writer::link($url,
//                     html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/hide'),
//                     'class' => 'icon hide', 'alt' => $strhidefromothers)),
//                     array('title' => $strhidefromothers, 'class' => 'editing_showhide'));
//             } else {
//                 $strshowfromothers = get_string('showfromothers', 'format_'.$course->format);
//                 $url->param('show',  $section->section);
//                 $controls[] = html_writer::link($url,
//                     html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/show'),
//                     'class' => 'icon hide', 'alt' => $strshowfromothers)),
//                     array('title' => $strshowfromothers, 'class' => 'editing_showhide'));
//             }
//         }

        if (!$onsectionpage && has_capability('moodle/course:movesections', $coursecontext)) {
            $url = clone($baseurl);
            if ($section->section > 1) { // Add a arrow to move section up.
                $url->param('section', $section->section);
                $url->param('move', -1);
                $strmoveup = get_string('moveup');

                $controls[] = html_writer::link($url,
                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/up'),
                    'class' => 'icon up', 'alt' => $strmoveup)),
                    array('title' => $strmoveup, 'class' => 'moveup'));
            }

            $url = clone($baseurl);
            if ($section->section < $course->numsections) { // Add a arrow to move section down.
                $url->param('section', $section->section);
                $url->param('move', 1);
                $strmovedown =  get_string('movedown');

                $controls[] = html_writer::link($url,
                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/down'),
                    'class' => 'icon down', 'alt' => $strmovedown)),
                    array('title' => $strmovedown, 'class' => 'movedown'));
            }
        }

        return $controls;
    }

    /**
     * Generates the edit page
     *
     * @param stdClass $course The course entry from DB
     * @param array $quiz Array containing quiz data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     */
    public function edit_page($course, $quiz, $structure, $cm, $context) {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        echo $this->heading(get_string('editingquizx', 'quiz', format_string($quiz->name)), 2);
        echo $this->help_icon('editingquiz', 'quiz', get_string('basicideasofquiz', 'quiz'));
        // Show status bar.
        echo $this->status_bar($quiz);

        $tabindex = 0;
        echo $this->maximum_grade_input($quiz, $this->page->url);

        $notifystrings = array();
        if (quiz_has_attempts($quiz->id)) {
            $reviewlink = quiz_attempt_summary_link_to_reports($quiz, $cm, $context);
            $notifystrings[] = get_string('cannoteditafterattempts', 'quiz', $reviewlink);
        }

        if ($quiz->shufflequestions) {
            $updateurl = new moodle_url("$CFG->wwwroot/course/mod.php",
                    array('return' => 'true', 'update' => $quiz->cmid, 'sesskey' => sesskey()));
            $updatelink = '<a href="'.$updateurl->out().'">' . get_string('updatethis', '',
                    get_string('modulename', 'quiz')) . '</a>';
            $notifystrings[] = get_string('shufflequestionsselected', 'quiz', $updatelink);
        }
        if (!empty($notifystrings)) {
            echo $this->box('<p>' . implode('</p><p>', $notifystrings) . '</p>', 'statusdisplay');
        }

        $slots = $structure->get_quiz_slots();
        $sections = $structure->get_quiz_sections();

        // Get questions.
        $questions = $DB->get_records_sql(
                "SELECT q.*, qc.contextid, slot.maxmark, slot.slot
                   FROM {question} q
                   JOIN {question_categories} qc ON qc.id = q.category
                   JOIN {quiz_slots} slot ON slot.questionid = q.id
                  WHERE slot.quizid = ?", array($quiz->id));

        $quiz->fullquestions = $questions;

        // Address missing question types.
        foreach ($slots as $slot) {
            $questionid = $slot->questionid;
            if (!$questionid) {
                continue;
            }

            // If the questiontype is missing change the question type.
            if ($questionid && !array_key_exists($questionid, $questions)) {
                $fakequestion = new stdClass();
                $fakequestion->id = $questionid;
                $fakequestion->category = 0;
                $fakequestion->qtype = 'missingtype';
                $fakequestion->name = get_string('missingquestion', 'quiz');
                $fakequestion->questiontext = ' ';
                $fakequestion->questiontextformat = FORMAT_HTML;
                $fakequestion->length = 1;
                $questions[$questionid] = $fakequestion;
                $quiz->grades[$questionid] = 0;

            } else if ($questionid && !question_bank::qtype_exists($questions[$questionid]->qtype)) {
                $questions[$questionid]->qtype = 'missingtype';
            }
        }

        // Display the add icon menu.
        if (!$quiz->fullquestions) {
            echo html_writer::tag('span', $this->add_menu_actions($quiz, ''));
        }

        // Now the list of sections.
        echo $this->start_section_list();

//         foreach ($sections as $section => $qnum) {
        $section = null;
        foreach ($sections as $section) {
            // For prototyping add required fields. Refactor to correct objects later.
            $section->visible = 1;
            $section->uservisible = 1;
            $section->available = 1;
            $section->indent = 1;

            if ($section->firstslot == 1) {
                // 0-section is displayed a little differently than the others.
                if ($section->heading or $this->page->user_is_editing()) {
                    echo $this->section_header($section, $course, false, 0);
                    echo $this->quiz_section_question_list($quiz, $structure, $course, $section, 0);
                    echo $this->section_footer();
                }
                continue;
            }

            echo $this->section_header($section, $course, false, 0);
            if ($section->uservisible) {
                echo $this->quiz_section_question_list($quiz, $structure, $course, $section, 0);
            }
            echo $this->section_footer();
        }

            echo $this->end_section_list();

    }

    /**
     * Render the status bar.
     *
     * @param object $quiz The quiz object of the quiz in question
     */
    public function status_bar($quiz) {
        global $DB;

        $bits = array();

        $bits[] = html_writer::tag('span',
                get_string('totalmarksx', 'quiz', quiz_format_grade($quiz, $quiz->sumgrades)),
                array('class' => 'totalpoints'));

        $bits[] = html_writer::tag('span',
                get_string('numquestionsx', 'quiz', $DB->count_records('quiz_slots', array('quizid' => $quiz->id))),
                array('class' => 'numberofquestions'));

        $timenow = time();

        // Exact open and close dates for the tool-tip.
        $dates = array();
        if ($quiz->timeopen > 0) {
            if ($timenow > $quiz->timeopen) {
                $dates[] = get_string('quizopenedon', 'quiz', userdate($quiz->timeopen));
            } else {
                $dates[] = get_string('quizwillopen', 'quiz', userdate($quiz->timeopen));
            }
        }
        if ($quiz->timeclose > 0) {
            if ($timenow > $quiz->timeclose) {
                $dates[] = get_string('quizclosed', 'quiz', userdate($quiz->timeclose));
            } else {
                $dates[] = get_string('quizcloseson', 'quiz', userdate($quiz->timeclose));
            }
        }
        if (empty($dates)) {
            $dates[] = get_string('alwaysavailable', 'quiz');
        }
        $tooltip = implode(', ', $dates);

        // Brief summary on the page.
        if ($timenow < $quiz->timeopen) {
            $currentstatus = get_string('quizisclosedwillopen', 'quiz',
                    userdate($quiz->timeopen, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($quiz->timeclose && $timenow <= $quiz->timeclose) {
            $currentstatus = get_string('quizisopenwillclose', 'quiz',
                    userdate($quiz->timeclose, get_string('strftimedatetimeshort', 'langconfig')));
        } else if ($quiz->timeclose && $timenow > $quiz->timeclose) {
            $currentstatus = get_string('quizisclosed', 'quiz');
        } else {
            $currentstatus = get_string('quizisopen', 'quiz');
        }

        $bits[] = html_writer::tag('span', $currentstatus,
                array('class' => 'quizopeningstatus', 'title' => implode(', ', $dates)));

        return html_writer::tag('div', implode(' | ', $bits), array('class' => 'statusbar'));
    }

    /**
     * Render the form for setting a quiz' overall grade
     *
     * @param object $quiz The quiz object of the quiz in question
     * @param object $pageurl The url of the current page with the parameters required
     *     for links returning to the current page, as a moodle_url object
     * @param int $tabindex The tabindex to start from for the form elements created
     * @return int The tabindex from which the calling page can continue, that is,
     *      the last value used +1.
     */
    function maximum_grade_input($quiz, $pageurl) {
        $o = '';
        $o .= '<form method="post" action="edit.php" class="quizsavegradesform"><div>';
        $o .= '<fieldset class="invisiblefieldset" style="display: block;">';
        $o .= "<input type=\"hidden\" name=\"sesskey\" value=\"" . sesskey() . "\" />";
        $o .= html_writer::input_hidden_params($pageurl);
        $a = '<input type="text" id="inputmaxgrade" name="maxgrade" size="' .
                ($quiz->decimalpoints + 2) .
                '" value="' . quiz_format_grade($quiz, $quiz->grade) . '" />';
        $o .= '<label for="inputmaxgrade">' . get_string('maximumgradex', '', $a) . "</label>";
        $o .= '<input type="hidden" name="savechanges" value="save" />';
        $o .= '<input type="submit" value="' . get_string('save', 'quiz') . '" />';
        $o .= '</fieldset>';
        $o .= "</div></form>\n";
        return $o;
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
    public function quiz_section_question_list_item($quiz, $structure, $course, &$completioninfo, $question, $sectionreturn, $displayoptions = array()) {
        $output = '';
        $slotid = $this->get_question_info($structure, $question->id, 'slotid');
        $slotnumber = $this->get_question_info($structure, $question->id, 'slot');
        $pagenumber = $this->get_question_info($structure, $question->id, 'page');
        $page = $pagenumber ? get_string('page') . ' ' . $pagenumber : null;
        $pagenumberclass = ''; // TODO MDL-43089 to add appropriate class name here
        $prevpage = $this->get_previous_page($structure, $slotnumber -1);
        if ($prevpage != $pagenumber) {
            $output .= html_writer::tag('div', $page,  array('class' => $pagenumberclass));
        }

        if ($questiontypehtml = $this->quiz_section_question($quiz, $structure, $course, $completioninfo, $question, $sectionreturn, $displayoptions)) {
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
    public function quiz_section_question_list($quiz, $structure, $course, $section, $sectionreturn = null, $displayoptions = array()) {
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

        $slots = $structure->get_quiz_slots();
        $sectiontoslotids = $structure->get_sections_and_slots();
        if (!empty($sectiontoslotids[$section->id])) {
//             foreach ($sections[$section->section] as $questionnumber => $questionid) {
            foreach ($sectiontoslotids[$section->id] as $slotid) {
                $slot = $slots[$slotid];
                $questionnumber = $slot->questionid;
                $question = $quiz->fullquestions[$questionnumber];

                if ($ismoving and $question->id == $USER->activitycopy) {
                    // do not display moving question type
                    continue;
                }

                if ($questiontypehtml = $this->quiz_section_question_list_item($quiz, $structure, $course,
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
        $url = $this->get_edit_question_url($quiz, $question);

//         $this->url = $modviews[$this->modname]
//                 ? new moodle_url('/mod/' . $this->modname . '/view.php', array('id'=>$this->id))
//                 : null;
        if (!$url) {
            return $output;
        }

        // Accessibility: for files get description via icon, this is very ugly hack!
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
     * @param object $quiz The quiz object of the quiz in question
     * @param object $question the question
     * @return the HTML for a marked out of question grade field.
     */
    public function marked_out_of_field($quiz, $question) {
        $output = '';
        $maxmark = html_writer::span(0 + $question->maxmark, 'instancemaxmark');
        $output .= html_writer::span($maxmark);
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
    public function quiz_section_question($quiz, $structure, $course, &$completioninfo, $question, $sectionreturn, $displayoptions = array()) {
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
        $slotnumber = $this->get_question_info($structure, $question->id, 'slot');

        if ($this->page->user_is_editing()) {
            $output .= $this->question_move($question, $sectionreturn);
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

            $output .= $this->marked_out_of_field($quiz, $question);

            if ($this->page->user_is_editing()) {
                $output .= ' ' . $this->regrade_action($question, $sectionreturn);
            }

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        $questionicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = $this->question_edit_actions($quiz, $question, null, $sectionreturn);
            $questionicons .= ' '. $this->quiz_section_question_edit_actions($editactions, $question, $displayoptions);
            $questionicons .= ' '. $this->add_menu_actions($quiz, $question);
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

    /**
     * Returns the regrade action.
     *
     * @param stdClass $question The question to produce editing buttons for
     * @param int $sr The section to link back to (used for creating the links)
     * @return The markup for the regrade action, or an empty string if not available.
     */
    public function regrade_action($question, $sr = null) {
        global $PAGE, $COURSE, $OUTPUT;

        static $baseurl;

        $hasmanagequiz = has_capability('mod/quiz:manage', $PAGE->cm->context);

        if (!isset($baseurl)) {
            $baseurl = new moodle_url('/quiz/question.php', array('sesskey' => sesskey()));
        }

        if ($sr !== null) {
            $baseurl->param('sr', $sr);
        }

        // AJAX edit title.
        if ($hasmanagequiz && course_ajax_enabled($COURSE)) {
            return html_writer::span(
                html_writer::link(
                    new moodle_url($baseurl, array('update' => $question->id)),
                    $OUTPUT->pix_icon('t/editstring', '', 'moodle', array('class' => 'iconsmall visibleifjs', 'title' => '')),
                    array(
                        'class' => 'editing_maxmark',
                        'data-action' => 'editmaxmark',
                        'title' => get_string('editmaxmark', 'quiz'),
                    )
                )
            );
        }
        return '';
    }

    /**
     * Returns the move action.
     *
     * @param object $question The module to produce a move button for
     * @param int $sr The section to link back to (used for creating the links)
     * @return The markup for the move action, or an empty string if not available.
     */
    public function question_move($question, $sr = null) {
        global $OUTPUT, $PAGE;

        static $str;
        static $baseurl;

        $hasmanagequiz = has_capability('mod/quiz:manage', $PAGE->cm->context);

        if (!isset($str)) {
            $str = get_strings(array('move'));
        }

        if (!isset($baseurl)) {
            $baseurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));

            if ($sr !== null) {
                $baseurl->param('sr', $sr);
            }
        }

        if ($hasmanagequiz) {
            $pixicon = 'i/dragdrop';

            return html_writer::link(
                new moodle_url($baseurl, array('copy' => $question->id)),
                $OUTPUT->pix_icon($pixicon, $str->move, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                array('class' => 'editing_move', 'data-action' => 'move')
            );
        }
        return '';
    }

    /**
     * Returns the list of all editing actions that current user can perform on the module
     *
     * @param object $question The module to produce editing buttons for
     * @param int $indent The current indenting (default -1 means no move left-right actions)
     * @param int $sr The section to link back to (used for creating the links)
     * @return array array of action_link or pix_icon objects
     */
    public function question_edit_actions($quiz, $question, $indent = -1, $sr = null) {
        global $COURSE, $SITE, $PAGE;

        static $str;

        // No permission to edit anything.
        $hasmanagequiz = has_capability('mod/quiz:manage', $PAGE->cm->context);

        if (!isset($str)) {
            $str = get_strings(array('delete', 'move', 'moveright', 'moveleft',
                'editsettings', 'duplicate', 'hide', 'show'), 'moodle');
            $str->assign         = get_string('assignroles', 'role');
            $str->groupsnone     = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsnone"));
            $str->groupsseparate = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsseparate"));
            $str->groupsvisible  = get_string('clicktochangeinbrackets', 'moodle', get_string("groupsvisible"));
        }

    //     $baseurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));
        $baseurl = $this->get_edit_question_url($quiz, $question);

        // TODO MDL-43089 fix this hack.
        global $thispageurl;
        $pageurl = new moodle_url($thispageurl, array('sesskey' => sesskey()));

        if ($sr !== null) {
            $baseurl->param('sr', $sr);
        }
        $actions = array();

        // Update.
        if ($hasmanagequiz) {
            $actions['update'] = new action_menu_link_secondary(
                new moodle_url($baseurl, array('update' => $question->id)),
                new pix_icon('t/edit', $str->editsettings, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str->editsettings,
                array('class' => 'editing_update', 'data-action' => 'update')
            );
        }

        // Delete.
        if ($hasmanagequiz) {
            $actions['delete'] = new action_menu_link_secondary(
                new moodle_url($pageurl, array('remove' => $question->slot)),
                new pix_icon('t/delete', $str->delete, 'moodle', array('class' => 'iconsmall', 'title' => '')),
                $str->delete,
                array('class' => 'editing_delete', 'data-action' => 'delete')
            );
        }

        return $actions;
    }

    /**
     * Retuns the list of adding actions
     * @param object $quiz, the quiz object
     * @param objet $question, the question object
     *
     */
    public function edit_menu_actions($quiz, $question) {
        global $DB, $PAGE;

        // No permission to edit anything.
        if (!has_capability('mod/quiz:manage', $PAGE->cm->context)) {
                return array();
        }

        if (!$quiz->fullquestions) {
            $context = context_module::instance($quiz->cmid);
            $questioncategoryid = $DB->get_field('question_categories', 'id', array('contextid' => $context->id));
        } else {
            $questioncategoryid = $question->category;
        }

        static $str;
        if (!isset($str)) {
            $str = get_strings(array('addasectionheading',
                                     'addaquestion',
                                    'addarandomquestion',
                                    'addarandomselectedquestion',
                                    'questionbankcontents'), 'quiz');
       }

        $baseurl = '/mod/quiz/edit.php';

        // Get section, page, slotnumber and maxmark.
        $actions = array();

        // Add a section heading.
        $params = array('cmid' => $quiz->cmid);
        $actions['addasectionheading'] = new action_menu_link_secondary(
            new moodle_url($baseurl, $params),
            new pix_icon('t/add', $str->addasectionheading, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addasectionheading, array('class' => 'editing_addasectionheading', 'data-action' => 'addasectionheading')
        );

        // Add a new question to the quiz.
        $returnurl = '/mod/quiz/edit.php';
        $params = array('returnurl' => $returnurl, 'cmid' => $quiz->cmid, 'courseid' => $quiz->course,
                        'category' => $questioncategoryid, 'appendqnumstring' => 'addquestion');
        $actions['addaquestion'] = new action_menu_link_secondary(
            new moodle_url('/question/question.php', $params),
            new pix_icon('t/add', $str->addaquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addaquestion, array('class' => 'editing_addaquestion', 'data-action' => 'addaquestion')
        );

        // Add a random question.
        $returnurl = new moodle_url('/mod/quiz/edit.php', array('cmid' => $quiz->cmid));
        $params = array('returnurl' => $returnurl, 'cmid' => $quiz->cmid);
        $actions['addarandomquestion'] = new action_menu_link_secondary(
            new moodle_url('/mod/quiz/addrandom.php', $params),
            new pix_icon('t/add', $str->addarandomquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addarandomquestion, array('class' => 'editing_addarandomquestion', 'data-action' => 'addarandomquestion')
        );

        // Add a random selected question.
        // TODO: We have to refine the functionality when adding random selected questions.
        $returnurl = new moodle_url('/mod/quiz/edit.php', array('cmid' => $quiz->cmid));
        $params = array('returnurl' => $returnurl, 'cmid' => $quiz->cmid);
        $actions['addarandomselectedquestion'] = new action_menu_link_secondary(
            new moodle_url('/mod/quiz/addrandom.php', $params),
            new pix_icon('t/add', $str->addarandomselectedquestion, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->addarandomselectedquestion, array('class' => 'editing_addarandomselectedquestion', 'data-action' => 'addarandomselectedquestion')
        );

        // Call question bank.
        // TODO: we have to write the code for qbank to be displayed as popup.
        $returnurl = '';///mod/quiz/edit.php';
        $params = array('returnurl' => $returnurl, 'cmid' => $quiz->cmid, 'qbanktool' => 1);
        $actions['questionbankcontents'] = new action_menu_link_secondary(
            new moodle_url('/question/questionbank.php', $params),
            new pix_icon('t/edit', $str->questionbankcontents, 'moodle', array('class' => 'iconsmall', 'title' => '')),
            $str->questionbankcontents, array('class' => 'editing_questionbankcontents', 'data-action' => 'questionbankcontents')
        );
        return $actions;
    }

    public function add_menu_actions($quiz, $question) {
        global $CFG;

        $actions = $this->edit_menu_actions($quiz, $question);
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

    protected function get_edit_question_url($quiz, $question) {
        // TODO MDL-43089 this should not be in the renderer.
        $questionparams = array(
                        'returnurl' => $this->page->url->out_as_local_url(),
                        'cmid' => $quiz->cmid,
                        'id' => $question->id);
        return new moodle_url('/question/question.php', $questionparams);
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
    public function quiz_section_add_question_control($quiz, $course, $section, $sectionreturn = null, $displayoptions = array()) {
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
    protected function get_section($structure, $sectionid) {
        if (!$sectionid) {
            // Possible, printout a notification or an error, but that should not happen.
            return null;
        }
        $sections = $structure->get_quiz_sections();
        if (!$sections) {
            return null;
        }
        foreach ($sections as $key => $section) {
            if ((int)$section->id === (int)$sectionid) {
                return $section->heading;
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
    protected function get_question_info($structure, $questionid, $info = 'all') {
        foreach ($structure->get_quiz_slots() as $slotid => $slot) {
            if ((int)$slot->questionid === (int)$questionid) {
                if ($info === 'all') {
                    return array($slot->sectionid, $slot->page, $slot->id, $slot->maxmark);
                }
                if ($info === 'section') {
                    return $this->get_section($structure, $slot->sectionid);
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

    protected function get_previous_page($structure, $prevslotnumber) {
        if ($prevslotnumber < 1) {
            return 0;
        }
        foreach ($structure->get_quiz_slots() as $slotid => $slot) {
            if ($slot->slot == $prevslotnumber) {
                return $slot->page;
            }
        }
        return 0;
    }
}
