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

namespace qtype_multichoice\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_question\output\question_version_info;
use question_utils;
use stdClass;

/**
 * Renderable to represent the editing view of a multiple choice question.
 *
 * @package qtype_multichoice
 * @copyright 2025 the Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inline_edit_view implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param stdClass $questiondata Data describing the question being edited.
     */
    public function __construct(
        /** @var stdClass Data describing the question being edited. */
        protected stdClass $questiondata,
    ) {
    }

    #[\Override]
    public function export_for_template(renderer_base $output): array {
        $question = \question_bank::make_question($this->questiondata);
        $data = [
            'questionid' => $this->questiondata->id,
            'questionname' => format_string($this->questiondata->name),
            'questiontext' => format_text(
                $this->questiondata->questiontext,
                $this->questiondata->questiontextformat,
            ),
            'defaultmark' => $this->questiondata->defaultmark,
            'answers' => [],
            'versioninfo' => (new question_version_info($question))->export_for_template($output),
        ];
        foreach ($this->questiondata->options->answers as $answer) {
            $data['answers'][] = [
                'id' => $answer->id,
                'answer' => format_text($answer->answer, $answer->answerformat),
                'isright' => $answer->fraction > 1 - question_utils::MARK_TOLERANCE,
            ];
        }
        return $data;
    }
}
