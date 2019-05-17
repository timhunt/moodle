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
 * Unit tests.
 *
 * @package filter_multilang
 * @category test
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/glossary/filter.php'); // Include the code to test.

/**
 * Test case for glossary.
 */
class filter_multilang_filter_testcase extends advanced_testcase {

    public function test_basic_case_en() {
        global $CFG;
        $this->resetAfterTest(true);

        // Enable glossary filter at top level.
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $context = context_system::instance();

        // Format text with the example given in the docs.
        $html = '<span lang="en" class="multilang">English</span><span lang="fr" class="multilang">Française</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => $context));
        $this->assertEquals($filtered, 'English');
    }
}
