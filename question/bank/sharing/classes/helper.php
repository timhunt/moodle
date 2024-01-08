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
 * Helper class for qbank sharing.
 *
 * @package    qbank_sharing
 * @copyright  2023 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author     Simon Adams <simon.adams@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_sharing;

use core_component;
use core_course\local\entity\content_item;

class helper {

    /**
     * Filter out any plugins that support FEATURE_PUBLISHES_QUESTIONS.
     * For example, when rendering to the course page we don't want to show these types.
     *
     * @param content_item[] $modules
     * @return content_item[]
     */
    public static function filter_plugins(array $modules): array {
        return array_filter($modules, static function($module) {
            [$type, $name] = core_component::normalize_component($module->get_component_name());
            return !plugin_supports($type, $name, FEATURE_PUBLISHES_QUESTIONS);
        });
    }
}
