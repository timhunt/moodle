<?php

/////////////////////////////////////////////////////////////////////////////
//                                                                         //
// NOTICE OF COPYRIGHT                                                     //
//                                                                         //
// Moodle - Calendar extension                                             //
//                                                                         //
// Copyright (C) 2003-2004  Greek School Network            www.sch.gr     //
//                                                                         //
// Designed by:                                                            //
//     Avgoustos Tsinakos (tsinakos@teikav.edu.gr)                         //
//     Jon Papaioannou (pj@moodle.org)                                     //
//                                                                         //
// Programming and development:                                            //
//     Jon Papaioannou (pj@moodle.org)                                     //
//                                                                         //
// For bugs, suggestions, etc contact:                                     //
//     Jon Papaioannou (pj@moodle.org)                                     //
//                                                                         //
// The current module was developed at the University of Macedonia         //
// (www.uom.gr) under the funding of the Greek School Network (www.sch.gr) //
// The aim of this project is to provide additional and improved           //
// functionality to the Asynchronous Distance Education service that the   //
// Greek School Network deploys.                                           //
//                                                                         //
// This program is free software; you can redistribute it and/or modify    //
// it under the terms of the GNU General Public License as published by    //
// the Free Software Foundation; either version 2 of the License, or       //
// (at your option) any later version.                                     //
//                                                                         //
// This program is distributed in the hope that it will be useful,         //
// but WITHOUT ANY WARRANTY; without even the implied warranty of          //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           //
// GNU General Public License for more details:                            //
//                                                                         //
//          http://www.gnu.org/copyleft/gpl.html                           //
//                                                                         //
/////////////////////////////////////////////////////////////////////////////

/**
 * Display the calendar page.
 * @copyright 2003 Jon Papaioannou
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_calendar
 */

require_once('../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/calendar/lib.php');

$categoryid = optional_param('category', null, PARAM_INT);
$courseid = optional_param('course', SITEID, PARAM_INT);
$view = optional_param('view', 'upcoming', PARAM_ALPHA);
$day = optional_param('cal_d', 0, PARAM_INT);
$mon = optional_param('cal_m', 0, PARAM_INT);
$year = optional_param('cal_y', 0, PARAM_INT);
$time = optional_param('time', 0, PARAM_INT);
$lookahead = optional_param('lookahead', null, PARAM_INT);

$url = new moodle_url('/calendar/view.php');

// If a day, month and year were passed then convert it to a timestamp. If these were passed
// then we can assume the day, month and year are passed as Gregorian, as no where in core
// should we be passing these values rather than the time. This is done for BC.
if (!empty($day) && !empty($mon) && !empty($year)) {
    if (checkdate($mon, $day, $year)) {
        $time = make_timestamp($year, $mon, $day);
    }
}

if (empty($time)) {
    $time = time();
}

$iscoursecalendar = $courseid != SITEID;

if ($iscoursecalendar) {
    $url->param('course', $courseid);
}

if ($categoryid) {
    $url->param('categoryid', $categoryid);
}

if ($view !== 'upcoming') {
    $time = usergetmidnight($time);
    $url->param('view', $view);
}

$url->param('time', $time);

$PAGE->set_url($url);

$course = get_course($courseid);

if ($iscoursecalendar && !empty($courseid)) {
    navigation_node::override_active_url(new moodle_url('/course/view.php', array('id' => $course->id)));
    $PAGE->set_secondary_navigation(false);
} else if (!empty($categoryid)) {
    core_course_category::get($categoryid); // Check that category exists and can be accessed.
    $PAGE->set_category_by_id($categoryid);
    navigation_node::override_active_url(new moodle_url('/course/index.php', array('categoryid' => $categoryid)));
    $PAGE->navbar->add(
        get_string('calendar', 'calendar'),
        new moodle_url('/calendar/view.php', ['view' => 'month', 'category' => $categoryid])
    );
    $PAGE->set_secondary_navigation(false);
} else {
    $PAGE->set_context(context_system::instance());
}

// Auto log in guests on frontpage.
$autologinguest = !$iscoursecalendar;
require_login($course, $autologinguest);

$calendar = calendar_information::create($time, $courseid, $categoryid);

$pagetitle = '';

$strcalendar = get_string('calendar', 'calendar');

switch($view) {
    case 'day':
        $PAGE->navbar->add(userdate($time, get_string('strftimedate')));
        $pagetitle = get_string('dayviewtitle', 'calendar', userdate($time, get_string('strftimedaydate')));
    break;
    case 'month':
        $PAGE->navbar->add(userdate($time, get_string('strftimemonthyear')));
        $pagetitle = get_string('detailedmonthviewtitle', 'calendar', userdate($time, get_string('strftimemonthyear')));
    break;
    case 'upcoming':
        $pagetitle = get_string('upcomingevents', 'calendar');
    break;
}

$PAGE->set_show_course_index(false);
$PAGE->set_pagelayout('standard');

// Print title and header.
$PAGE->set_title("$course->shortname: $strcalendar: $pagetitle");

$headingstr = get_string('calendar', 'core_calendar');
// If the user is on the course page,
// then make the course name linkable to ease the user's navigation to the course page.
if ($iscoursecalendar) {
    $linkcourse = html_writer::link(
        course_get_url($course),
        format_string($course->shortname, options: ['context' => $PAGE->context]),
    );
    $headingstr = "{$headingstr}: {$linkcourse}";
}
$PAGE->set_heading($headingstr, false);

$renderer = $PAGE->get_renderer('core_calendar');
$calendar->add_sidecalendar_blocks($renderer, true, $view);

echo $OUTPUT->header();
echo $renderer->start_layout();
echo html_writer::start_tag('div', ['class' => 'heightcontainer', 'data-calendar-type' => 'main-block']);



list($data, $template) = calendar_get_view($calendar, $view, true, false, $lookahead);
echo $renderer->render_from_template($template, $data);

echo html_writer::end_tag('div');

list($data, $template) = calendar_get_footer_options($calendar);
echo $renderer->render_from_template($template, $data);

echo $renderer->complete_layout();
echo $OUTPUT->footer();
