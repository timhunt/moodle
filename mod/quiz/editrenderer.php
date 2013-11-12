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
 * Base renderer for outputting edit quiz form.
 *
 * @package quiz
 * @copyright 2013 Colin Chambers
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.6
 */

defined('MOODLE_INTERNAL') || die();


/**
 * This is a convenience renderer which can be used by section based formats
 * to reduce code duplication. It is not necessary for all course formats to
 * use this and its likely to change in future releases.
 *
 * @package core
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */
class mod_quiz_edit_section_renderer extends mod_quiz_renderer {
/** @var contains instance of core course renderer */
    protected $quizeditrenderer;

/** @var contains instance of core course renderer */
    protected $courserenderer;

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->quizeditrenderer = $this->page->get_renderer('core', 'course');
        $this->quizrenderer = $this->page->get_renderer('mod_quiz');
    }

/**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'weeks'));
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
     * Generate the section title
     *
     * @param stdClass $section The quiz_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_heading($section, $course) {
        $heading = \mod_quiz\structure::get_quiz_section_heading($section);
        return $heading;
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
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->id,
            'class' => 'section main clearfix'.$sectionstyle, 'role'=>'region',
            'aria-label'=> \mod_quiz\structure::get_quiz_section_heading($section)));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
//         $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->heading)));

        // When on a section page, we only display the general section title, if title is not the default one
//         $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
//         if ($hasnamenotsecpg || $hasnamesecpg) {
//             $classes = '';
//         }
        $o.= $this->output->heading($this->section_heading($section, $course), 3, 'sectionname' . $classes);

        $o.= html_writer::start_tag('div', array('class' => 'summary'));
        $o.= $this->format_summary_text($section);

        $hasmanagequiz = has_capability('mod/quiz:manage', $PAGE->cm->context);
        if ($PAGE->user_is_editing() && $hasmanagequiz) {
            $url = new moodle_url('/course/editsection.php', array('id'=>$section->id, 'sr'=>$sectionreturn));
            $o.= html_writer::link($url,
                html_writer::empty_tag('img', array('src' => $this->output->pix_url('t/edit'),
                    'class' => 'iconsmall edit', 'alt' => get_string('edit'))),
                array('title' => get_string('editsummary')));
        }
        $o.= html_writer::end_tag('div');

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
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
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

    protected function get_questions($quiz){
        global $DB;
        $questions = array();
        if (!$quiz->questions) {
            return $questions;
        }

        list($usql, $params) = $DB->get_in_or_equal(explode(',', $quiz->questions));
        $params[] = $quiz->id;
        $questions = $DB->get_records_sql("SELECT q.*, qc.contextid, qqi.maxmark
                              FROM {question} q
                              JOIN {question_categories} qc ON qc.id = q.category
                              JOIN {quiz_question_instances} qqi ON qqi.questionid = q.id
                             WHERE q.id $usql AND qqi.quizid = ?", $params);

        return $questions;
    }

    /*
     * Edit Page
     */
    /**
     * Generates the edit page
     *
     * @param stdClass $course The course entry from DB
     * @param array $quiz Array containing quiz data
     * @param int $cm Course Module ID
     * @param int $context The page context ID
     */
    public function edit_page($course, $quiz, $cm, $context) {
        global $PAGE;
        global $Out;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        $slots = \mod_quiz\structure::get_quiz_slots($quiz);
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);
        //$sectiontoslotids = $quiz->sectiontoslotids;

        // Get questions
        $questions = $this->get_questions($quiz);
        $quiz->fullquestions = $questions;

        // Address missing question types.
        foreach($slots as $slot) {
            $questionid = $slot->questionid;
            if(!$questionid){
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

        // Get quiz question order
        $sections = \mod_quiz\structure::get_quiz_sections($quiz);

        // Now the list of sections..
        echo $this->start_section_list();

//         foreach ($sections as $section => $qnum) {
        $section = null;
        foreach ($sections as $section) {
            // For prototyping add required fields. Refactor to correct objects later
            $section->visible = 1;
            $section->uservisible = 1;
            $section->available = 1;
            $section->indent = 1;

            if ($section->firstslot == 1) {
                // 0-section is displayed a little differently than the others
                if ($section->heading or $PAGE->user_is_editing()) {
                    echo $this->section_header($section, $course, false, 0);
                    echo $this->quizrenderer->quiz_section_question_list($quiz, $course, $section, 0);
//                     echo $this->quizrenderer->quiz_section_add_question_control($quiz, $course, $section, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }

            echo $this->section_header($section, $course, false, 0);
            if ($section->uservisible) {
                echo $this->quizrenderer->quiz_section_question_list($quiz, $course, $section, 0);
//                 echo $this->quizrenderer->quiz_section_add_question_control($course, $section, 0);
            }
            echo $this->section_footer();
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {

            echo $this->end_section_list();

            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                      'increase' => true,
                      'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                          'increase' => false,
                          'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
        }

    }
}