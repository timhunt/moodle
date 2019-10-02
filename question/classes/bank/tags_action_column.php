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
 * The question tags column subclass.
 *
 * @package   core_question
 * @copyright 2018 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_question\bank;
defined('MOODLE_INTERNAL') || die();


/**
 * Action to add and remove tags to questions.
 *
 * @copyright 2018 Simey Lameze <simey@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tags_action_column extends action_column_base implements action_can_go_in_menu {
    /**
     * @var string store this lang string for performance.
     */
    protected $managetags;

    public function init() {
        parent::init();
        $this->managetags = get_string('managetags', 'tag');
    }

    /**
     * Return the name for this column.
     *
     * @return string
     */
    public function get_name() {
        return 'tagsaction';
    }

    /**
     * Display tags column content.
     *
     * @param object $question The question database record.
     * @param string $rowclasses
     */
    protected function display_content($question, $rowclasses) {
        global $OUTPUT;

        if (\core_tag_tag::is_enabled('core_question', 'question') &&
                question_has_capability_on($question, 'view')) {

            [$url, $params] = $this->get_link_url_and_params($question);
            echo \html_writer::link($url, $OUTPUT->pix_icon('t/tags', $this->managetags), $params);
        }
    }

    /**
     * Helper used by display_content and get_action_menu_link.
     *
     * @param object $question the row from the $question table, augmented with extra information.
     * @return array with two elements, \moodle_url and array link URL and params need for this action.
     */
    protected function get_link_url_and_params($question) {
        $url = new \moodle_url($this->qbank->edit_question_url($question->id));

        $params = [
                'data-action' => 'edittags',
                'data-cantag' => question_has_capability_on($question, 'tag'),
                'data-contextid' => $this->qbank->get_most_specific_context()->id,
                'data-questionid' => $question->id
        ];

        return [$url, $params];
    }

    public function get_action_menu_link($question) {
        global $OUTPUT;

        if (!\core_tag_tag::is_enabled('core_question', 'question') ||
                !question_has_capability_on($question, 'view')) {
            return null;
        }

        [$url, $params] = $this->get_link_url_and_params($question);
        return new \action_menu_link_secondary($url, new \pix_icon('t/tags', ''),
                $this->managetags, $params);
    }
}
