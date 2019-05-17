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
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Tests for filter_multilang.
 *
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_multilang_filter_testcase extends advanced_testcase {

    public function setUp() {
        parent::setUp();

        $this->resetAfterTest(true);

        // Enable glossary filter at top level.
        filter_set_global_state('multilang', TEXTFILTER_ON);
    }

    /**
     * Setup parent language relationship.
     *
     * @param string $parent the parent langauge, e.g. 'fr'.
     * @param string $child the child langauge, e.g. 'fr_ca'.
     */
    protected function setup_parent_language(string $parent, string $child) {
        global $CFG;

        $langfolder = $CFG->dataroot . '/lang/' . $child;
        check_dir_exists($langfolder);
        $langconfig = "<?php\n\$string['parentlanguage'] = '$parent';";
        file_put_contents($langfolder . '/langconfig.php', $langconfig);
    }

    public function test_basic_case_en() {
        // Format text with the example given in the docs.
        $html = '<span lang="en" class="multilang">English</span><span lang="fr" class="multilang">Française</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('English', $filtered);
    }

    public function test_basic_case_fr() {
        global $SESSION;
        $SESSION->forcelang = 'fr';

        // Format text with the example given in the docs.
        $html = '<span lang="en" class="multilang">English</span><span lang="fr" class="multilang">Française</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Française', $filtered);
    }

    public function test_reversed_attributes_en() {
        // Example with the attributes in a different order.
        $html = '<span lang="fr" class="multilang">Française</span><span class="multilang" lang="en">English</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('English', $filtered);
    }

    public function test_reversed_attributes_case_fr() {
        global $SESSION;
        $SESSION->forcelang = 'fr';

        // Example with the attributes in a different order.
        $html = '<span class="multilang" lang="fr">Française</span><span lang="en" class="multilang">English</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Française', $filtered);
    }

    public function test_parent_language() {
        global $SESSION;
        $this->setup_parent_language('fr', 'fr_ca');
        $SESSION->forcelang = 'fr_ca';

        // Format text with the example given in the docs.
        $html = '<span lang="en" class="multilang">English</span><span lang="fr" class="multilang">Française</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Française', $filtered);
    }

    public function test_parent_language_with_both_provided_child() {
        global $SESSION;
        $this->setup_parent_language('fr', 'fr_ca');
        $SESSION->forcelang = 'fr_ca';

        // Example with both parent and child language present.
        $html = '<span lang="fr_ca" class="multilang">Québécois</span>
                <span lang="fr" class="multilang">Française</span>
                <span lang="en" class="multilang">English</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Québécois', $filtered);
    }

    public function test_parent_language_with_both_provided_parent() {
        global $SESSION;
        $this->setup_parent_language('fr', 'fr_ca');
        $SESSION->forcelang = 'fr';

        // Example with both parent and child language present.
        $html = '<span lang="fr_ca" class="multilang">Québécois</span>
                <span lang="fr" class="multilang">Française</span>
                <span lang="en" class="multilang">English</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Française', $filtered);
    }

    public function test_parent_language_with_both_provided_other_order_child() {
        global $SESSION;
        $this->setup_parent_language('fr', 'fr_ca');
        $SESSION->forcelang = 'fr_ca';

        // Example with both parent and child language present - reverse order.
        $html = '<span lang="en" class="multilang">English</span>
                <span lang="fr" class="multilang">Française</span>
                <span lang="fr_ca" class="multilang">Québécois</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Québécois', $filtered);
    }

    public function test_parent_language_with_both_provided_other_order_parent() {
        global $SESSION;
        $this->setup_parent_language('fr', 'fr_ca');
        $SESSION->forcelang = 'fr';

        // Example with both parent and child language present - reverse order.
        $html = '<span lang="en" class="multilang">English</span>
                <span lang="fr" class="multilang">Française</span>
                <span lang="fr_ca" class="multilang">Québécois</span>';
        $filtered = format_text($html, FORMAT_HTML, array('context' => context_system::instance()));
        $this->assertEquals('Française', $filtered);
    }
}
