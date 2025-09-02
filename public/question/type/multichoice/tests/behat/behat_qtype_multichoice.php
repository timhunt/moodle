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
 * Behat qtype_multichoice-related steps definitions.
 *
 * @package    qtype_multichoice
 * @category   test
 * @copyright  2020 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat custom step definitions and partial named selectors for qtype_multichoice.
 *
 * @package    qtype_multichoice
 * @category   test
 * @copyright  2020 Jun Pataleta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_multichoice extends behat_base {

    #[\Override]
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'edit test':
                return new moodle_url(
                    '/question/type/multichoice/testedit.php',
                    ['id' => $this->find_question_by_name($identifier)],
                );

            default:
                throw new Exception('Unrecognised qtype_multichoice page type "' . $type . '."');
        }
    }

    /**
     * Find a question from the question name.
     *
     * This is a helper used by resolve_page_instance_url.
     *
     * @param string $questionname
     * @return int question id.
     */
    protected function find_question_by_name(string $questionname): int {
        global $DB;
        return  $DB->get_field('question', 'id', ['name' => $questionname], MUST_EXIST);
    }

    /**
     * Return the list of partial named selectors for this plugin.
     *
     * @return behat_component_named_selector[]
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector(
                'Answer', [
                    <<<XPATH
    .//div[@data-region='answer-label']//*[contains(text(), %locator%)]
XPATH
                ]
            ),
        ];
    }
}
