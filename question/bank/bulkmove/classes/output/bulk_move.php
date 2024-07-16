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
 * @package    qbank_bulkmove
 * @copyright  2024 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_bulkmove\output;

use cm_info;
use core_question\local\bank\question_bank_helper;
use moodle_url;
use renderer_base;
use single_button;
use stdClass;

/**
 * Create a modal template with selects for question banks, question categories, and a move button.
 */
class bulk_move implements \renderable, \templatable {

    /** @var int The question bank id you are currently moving the question(s) from */
    private int $currentbankid;

    /** @var int The question category id you are moving the question(s) from */
    private int $currentcategoryid;

    public function __construct(int $currentbankid, int $currentcategoryid) {
        $this->currentbankid = $currentbankid;
        $this->currentcategoryid = $currentcategoryid;
    }

    public function export_for_template(renderer_base $output) {
        global $DB;

        [, $cmrec] = get_module_from_cmid($this->currentbankid);
        $currentbank = cm_info::create($cmrec);
        $bankstorender = [];
        $allcategories = [];

        // Get all shared banks and categories and make the current bank/category pre-selected, i.e. ordered first in the list.
        $openinstancegen = question_bank_helper::get_instances(question_bank_helper::OPEN,
            [],
            [],
            ['moodle/question:add'],
            true,
            $this->currentbankid
        );

        // Export the generator to an array for the template.
        foreach ($openinstancegen as $bank) {
            $bankstorender[] = $bank;
            if ($bank->modid == $this->currentbankid) {
                // If this is the current bank then sort the categories so that our current categoryid is first in the list.
                $this->sort_categories($bank->questioncategories, $this->currentcategoryid);
            }
            $allcategories[] = $bank->questioncategories;
        }

        // The current bank is not a shared bank, but grab the category records anyway so that we can at least allow them
        // to be moved to another local category in the bank.
        if (!plugin_supports('mod', $currentbank->modname, FEATURE_PUBLISHES_QUESTIONS, false)) {
            $closedbankcats = $DB->get_records_sql(
                'SELECT id,name,contextid FROM {question_categories} WHERE parent <> 0 AND contextid = :contextid',
                ['contextid' => $currentbank->context->id]
            );
            $closedbank = new stdClass();
            $closedbank->name = $currentbank->get_formatted_name();
            $closedbank->contextid = $currentbank->context->id;
            $this->sort_categories($closedbankcats, $this->currentcategoryid);
            // Add the current bank to the front of the banks list.
            array_unshift($allcategories, $closedbankcats);
            // Add the current category to the front of the categories list.
            array_unshift($bankstorender, $closedbank);
        }

        // Flatten all the categories into a 2D array.
        $allcategories = array_merge(...array_values($allcategories));

        $savebutton = new single_button(
            new moodle_url('#'),
            get_string('movequestions', 'qbank_bulkmove'),
            'post',
            single_button::BUTTON_PRIMARY,
            [
                'data-action' => 'bulkmovesave',
                'disabled' => 'disabled',
            ]
        );

        return [
            'allopenbanks' => $bankstorender,
            'allcategories' => $allcategories,
            'save' => $savebutton->export_for_template($output),
        ];
    }

    /**
     * Wrapped usort to move the currentcategoryid to the top of the list of question categories.
     *
     * @param array &$categories
     * @param int $currentcategoryid
     * @return void
     */
    private function sort_categories(array &$categories, int $currentcategoryid): void {
        usort($categories, static function($categorya, $categoryb) use ($currentcategoryid) {
            if ($categorya->id != $currentcategoryid && $categoryb->id == $currentcategoryid) {
                return 1;
            }
            if ($categorya->id == $currentcategoryid && $categoryb->id != $currentcategoryid) {
                return -1;
            }

            return $categoryb->id <=> $categorya->id;
        });
    }
}
