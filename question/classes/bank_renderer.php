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
 * Renderers for outputting parts of the question bank.
 *
 * @package   core_question
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * This renderer outputs parts of the question bank.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_bank_renderer extends plugin_renderer_base {

    /**
     * Output the icon for a question type.
     *
     * @param string $qtype the question type.
     * @return string HTML fragment.
     */
    public function qtype_icon($qtype) {
        $qtype = question_bank::get_qtype($qtype, false);
        $namestr = $qtype->local_name();

        return $this->pix_icon('icon', $namestr, $qtype->plugin_name(), array('title' => $namestr));
    }

    /**
     * Print a control for creating a new question. This will open the question type
     * chooser, which in turn goes to question/question.php before getting back to
     * $params['returnurl'] (by default the question bank screen).
     *
     * @param int $categoryid The id of the category that the new question should be added to.
     * @param array $params Other parameters to add to the URL. You need either $params['cmid'] or
     *      $params['courseid'], and you should probably set $params['returnurl']
     * @param string $caption the text to display on the button.
     * @param string $tooltip a tooltip to add to the button (optional).
     * @param bool $disabled if true, the button will be disabled (optional, defaults to false).
     * @param array $allowedqtypes if not null, restrict the types of question
     *      that can be added (optional). This list must be consistent for all
     *      calls to this method on a page. (If you pass different values to
     *      different calls to this method, only the first one will be used.)
     */
    public function create_question_action($categoryid, $params, $caption,
            $tooltip = '', $disabled = false, $allowedqtypes = null) {
        $output = '';

        $params['category'] = $categoryid;
        $url = new moodle_url('/question/addquestion.php', $params);

        $buttonparams = array(
            'type'  => 'button',
            'value' => $caption,
            'class' => 'core_question_add_question_action',
        );
        if ($disabled) {
            $buttonparams['disabled'] = 'disabled';
        }
        if ($tooltip) {
            $buttonparams['title'] = $tooltip;
        }

        $buttonparams['data-category'] = $categoryid;
        if (isset($params['cmid'])) {
            $buttonparams['data-cmid'] = $params['cmid'];
        } else if (isset($params['courseid'])) {
            $buttonparams['data-courseid'] = $params['courseid'];
        }
        if (isset($params['returnurl'])) {
            $buttonparams['data-returnurl'] = $params['returnurl'];
        }
        if (isset($params['appendqnumstring'])) {
            $buttonparams['data-appendqnumstring'] = $params['appendqnumstring'];
        }
        if (isset($params['scrollpos'])) {
            $buttonparams['data-scrollpos'] = $params['scrollpos'];
        }

        $output .= html_writer::empty_tag('input', $buttonparams);

        if ($this->page->requires->should_create_one_time_item_now('core_question_qtypechooser')) {
            $this->page->requires->yui_module('moodle-question-chooser', 'M.question.init_chooser');
            list($real, $fake) = question_bank::get_qtype_chooser_options($allowedqtypes);
            $output .= html_writer::div($this->qbank_chooser($real, $fake),
                    null, array('id' => 'qtypechoicecontainer'));
        }

        return $output;
    }

    /**
     * Build the HTML for the question chooser javascript popup.
     *
     * @param array $real A set of real question types
     * @param array $fake A set of fake question types
     * @return string The composed HTML for the questionbank chooser
     */
    public function qbank_chooser($real, $fake) {

        // Start the form content.
        $formcontent = html_writer::start_tag('form', array('action' => new moodle_url('/question/question.php'),
                'id' => 'chooserform', 'method' => 'get'));

        // Put everything into one tag 'options'.
        $formcontent .= html_writer::start_tag('div', array('class' => 'options'));
        $formcontent .= html_writer::div(get_string('selectaqtypefordescription', 'question'), 'instruction');

        // Put all options into one tag 'qoptions' to allow us to handle scrolling.
        $formcontent .= html_writer::start_tag('div', array('class' => 'alloptions'));

        // First display real questions.
        $formcontent .= $this->qbank_chooser_title('questions', 'question');
        $formcontent .= $this->qbank_chooser_types($real);

        $formcontent .= html_writer::div('', 'separator');

        // Then fake questions.
        $formcontent .= $this->qbank_chooser_title('other');
        $formcontent .= $this->qbank_chooser_types($fake);

        // Options.
        $formcontent .= html_writer::end_tag('div');

        // Types.
        $formcontent .= html_writer::end_tag('div');

        // Add the form submission buttons.
        $submitbuttons = '';
        $submitbuttons .= html_writer::empty_tag('input',
                array('type' => 'submit', 'name' => 'submitbutton', 'class' => 'submitbutton', 'value' => get_string('add')));
        $submitbuttons .= html_writer::empty_tag('input',
                array('type' => 'submit', 'name' => 'addcancel', 'class' => 'addcancel', 'value' => get_string('cancel')));
        $formcontent .= html_writer::div($submitbuttons, 'submitbuttons');

        $formcontent .= html_writer::end_tag('form');

        // Wrap the whole form in a div.
        $formcontent = html_writer::tag('div', $formcontent, array('id' => 'chooseform'));

        // Generate the header and return the whole form.
        $header = html_writer::div(get_string('chooseqtypetoadd', 'question'), 'choosertitle hd');
        return $header . html_writer::div(html_writer::div($formcontent, 'choosercontainer'), 'chooserdialogue');
    }

    /**
     * Build the HTML for a specified set of question types.
     *
     * @param array $types A set of question types as used by the qbank_chooser_module function
     * @return string The composed HTML for the module
     */
    protected function qbank_chooser_types($types) {
        $return = '';
        foreach ($types as $type) {
            $return .= $this->qbank_chooser_qtype($type);
        }
        return $return;
    }

    /**
     * Return the HTML for the specified question type, adding any required classes.
     *
     * @param question_type $qtype The type to display.
     * @param array $classes Additional classes to add to the encompassing div element
     * @return string The composed HTML for the question type
     */
    protected function qbank_chooser_qtype(question_type $qtype, $classes = array()) {
        $output = '';
        $classes[] = 'option';
        $output .= html_writer::start_tag('div', array('class' => implode(' ', $classes)));
        $output .= html_writer::start_tag('label', array('for' => 'qtype_' . $qtype->plugin_name()));
        $output .= html_writer::empty_tag('input', array('type' => 'radio',
                'name' => 'qtype', 'id' => 'qtype_' . $qtype->plugin_name(), 'value' => $qtype->name()));

        $output .= html_writer::start_tag('span', array('class' => 'modicon'));
        // Add an icon if we have one.
        $output .= $this->pix_icon('icon', $qtype->local_name(), $qtype->plugin_name(),
                array('title' => $qtype->local_name(), 'class' => 'icon'));
        $output .= html_writer::end_tag('span');

        $output .= html_writer::span($qtype->menu_name(), 'typename');

        // Format the help text using markdown with the following options.
        $options = new stdClass();
        $options->trusted = false;
        $options->noclean = false;
        $options->smiley = false;
        $options->filter = false;
        $options->para = true;
        $options->newlines = false;
        $options->overflowdiv = false;
        $qtype->help = format_text(get_string('pluginnamesummary', $qtype->plugin_name()), FORMAT_MARKDOWN, $options);

        $output .= html_writer::span($qtype->help, 'typesummary');
        $output .= html_writer::end_tag('label');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Return the title for the question bank chooser.
     *
     * @param string $title The language string identifier
     * @param string $identifier The component identifier
     * @return string The composed HTML for the title
     */
    protected function qbank_chooser_title($title, $identifier = null) {
        $span = html_writer::span('', 'modicon');
        $span .= html_writer::span(get_string($title, $identifier), 'typename');

        return html_writer::div($span, 'option moduletypetitle');
    }
}
