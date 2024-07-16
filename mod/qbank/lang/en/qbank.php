<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     mod_qbank
 * @category    string
 * @copyright   2021 Catalyst IT Australia Pty Ltd
 * @author      Safat Shahin <safatshahin@catalyst-au.net>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addanotherbank'] = 'Add question bank';
$string['modulename'] = 'Question bank';
$string['modulename_help'] = 'This activity allows a teacher to create, preview, and edit questions in a database of question categories.

These questions are then used by the quiz activity, or by other plugins.

Questions are given version control and statistics once they have been used, and other parameters.';
$string['modulenameplural'] = 'Question banks';
$string['pluginadministration'] = 'Question bank administration';
$string['pluginname'] = 'Question bank';
$string['privacy:metadata'] = 'The Question bank plugin does not store any personal data, core_question automatically tracks all sorts of data for questions.';
$string['noqbankinstances'] = 'There are no Question bank in this course.';
$string['qbank:addinstance'] = 'Add a new Question bank';
$string['qbankname'] = 'Question bank name';
$string['qbankname_help'] = 'Enter the Question bank name';
$string['saveanddisplay'] = 'Save and display';
$string['saveandreturn'] = 'Save and return to question bank list';
$string['showdescription'] = 'Display description on manage question banks page';
$string['showdescription_help'] = 'If enabled, the description above will be displayed on the question bank manage page just below the link to the bank.';
$string['systembankdescription'] = "This type of question bank is created automatically. It will be used for things such as backup restores that could not find a target context, and for when deleting a category and it's contents could not be deleted safely";
$string['unknownbanktype'] = 'Unknown question bank type {$a}';
$string['systembank'] = "System shared question bank";
$string['previewbank'] = "Preview shared question bank";
$string['sharedbank'] = '{$a} shared question bank';
$string['coursecategory'] = 'Shared teaching resources for category: {$a}';
$string['installnotfinished'] = "Adhoc task \\mod_qbank\\task\\install has not yet completed or has failed. Some of your pre-install banks may not have been transferred to mod_qbank instances yet. Any question categories they contained will not be able to be shared or managed until the task has completed successfully";
