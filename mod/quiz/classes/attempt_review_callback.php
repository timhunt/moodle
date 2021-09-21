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

namespace mod_quiz;

abstract class attempt_review_callback {
    /**
     * Prepare the summary information which is shown at the top of the review page.
     *
     * $summarydata, and should be returned, with watever modifications you want to make.
     * (Normally just adding one or more rows at the end.)
     *
     * The $summarydata array has the rows of the summary table in order.
     * The key for each element of the array should be a short, meaningful, string.
     * The value is an array with two elements:
     * ['title' => $cell1, 'content' => $cell2]
     * each of those may be either a {@link renderable} or
     * (something that is effectively) a string
     * This is rendered by {@link mod_quiz_renderer::review_summary_table()}.
     *
     * @param array $summarydata the summary data so far assembled.
     * @param \quiz_attempt $attemptobj quiz attempt we are generating the summary for.
     * @param \mod_quiz_display_options $options display options in force for this review.
     * @param int $page which page is being shown.
     * @param bool $showall whether the review is showing all questions.
     * @return array[] structured as above.
     */
    public abstract function update_review_summary(array $summarydata,
            \quiz_attempt $attemptobj,
            \mod_quiz_display_options $options,
            int $page, bool $showall): array;
}
