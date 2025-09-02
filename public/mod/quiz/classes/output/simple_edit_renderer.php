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

namespace mod_quiz\output;

use core_question\local\bank\question_version_status;
use \mod_quiz\structure;
use \html_writer;
use qbank_previewquestion\question_preview_options;
use question_bank;
use renderable;

/**
 * Renderer outputting the quiz editing UI.
 *
 * @copyright 2013 The Open University.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.7
 */
class simple_edit_renderer extends \plugin_renderer_base {

    /** @var string The toggle group name of the checkboxes for the toggle-all functionality. */
    protected $togglegroup = 'quiz-questions';

    /**
     * Render the edit page
     *
     * @param \mod_quiz\quiz_settings $quizobj object containing all the quiz settings information.
     * @param structure $structure object containing the structure of the quiz.
     * @param \core_question\local\bank\question_edit_contexts $contexts the relevant question bank contexts.
     * @param \moodle_url $pageurl the canonical URL of this page.
     * @param array $pagevars the variables from {@link question_edit_setup()}.
     * @return string HTML to output.
     */
    public function edit_page(
        \mod_quiz\quiz_settings $quizobj,
        structure $structure,
        \core_question\local\bank\question_edit_contexts $contexts,
        \moodle_url $pageurl,
        array $pagevars,
        ) {
        
    }
}
