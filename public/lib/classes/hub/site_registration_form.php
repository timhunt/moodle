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
 * Class site_registration_form
 *
 * @package    core
 * @copyright  2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\hub;
defined('MOODLE_INTERNAL') || die();

use context_course;
use stdClass;
use html_writer;
use moodle_url;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The site registration form. Information will be sent to the sites directory.
 *
 * @author     Jerome Mouneyrac <jerome@mouneyrac.com>
 * @package    core
 * @copyright  2017 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_registration_form extends \moodleform {

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;

        $strrequired = get_string('required');
        $mform = & $this->_form;
        $admin = get_admin();
        $site = get_site();
        $registered = $this->_customdata['registered'];

        $siteinfo = registration::get_site_info([
            'name' => format_string($site->fullname, true, array('context' => context_course::instance(SITEID))),
            'description' => $site->summary,
            'contactname' => fullname($admin, true),
            'contactemail' => $admin->email,
            'contactphone' => $admin->phone1,
            'street' => '',
            'countrycode' => $admin->country ?: $CFG->country,
            'regioncode' => '-', // Not supported yet.
            'language' => explode('_', current_language())[0],
            'geolocation' => '',
            'emailalert' => 0,
            'commnews' => 0,
            'policyagreed' => 0,
            'organisationtype' => '',
        ]);

        // Fields that need to be highlighted.
        $highlightfields = registration::get_new_registration_fields();

        $mform->addElement('header', 'moodle', get_string('registrationinfo', 'hub'));

        $mform->addElement('text', 'name', get_string('sitename', 'hub'),
            array('class' => 'registration_textfield', 'maxlength' => 255));
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'sitename', 'hub');

        $organisationtypes = registration::get_site_organisation_type_options();
        \core_collator::asort($organisationtypes);
        // Prepend the empty/default value here. We are not using array_merge to preserve keys.
        $organisationtypes = ['' => get_string('siteorganisationtype:donotshare', 'hub')] + $organisationtypes;
        $mform->addElement('select', 'organisationtype', get_string('siteorganisationtype', 'hub'), $organisationtypes);
        $mform->setType('organisationtype', PARAM_ALPHANUM);
        $mform->addHelpButton('organisationtype', 'siteorganisationtype', 'hub');

        $mform->addElement('select', 'privacy', get_string('siteprivacy', 'hub'), registration::site_privacy_options());
        $mform->setType('privacy', PARAM_ALPHA);
        $mform->addHelpButton('privacy', 'siteprivacy', 'hub');
        unset($options);

        $mform->addElement('textarea', 'description', get_string('sitedesc', 'hub'),
            array('rows' => 3, 'cols' => 41));
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'sitedesc', 'hub');

        $languages = get_string_manager()->get_list_of_languages();
        \core_collator::asort($languages);
        $mform->addElement('select', 'language', get_string('sitelang', 'hub'), $languages);
        $mform->setType('language', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('language', 'sitelang', 'hub');

        // Postal address was part of this form before but not any more.
        $mform->addElement('hidden', 'street');
        $mform->setType('street', PARAM_TEXT);
        $mform->addHelpButton('street', 'postaladdress', 'hub');

        $mform->addElement('hidden', 'regioncode', '-');
        $mform->setType('regioncode', PARAM_ALPHANUMEXT);

        $countries = ['' => ''] + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'countrycode', get_string('sitecountry', 'hub'), $countries);
        $mform->setType('countrycode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('countrycode', 'sitecountry', 'hub');
        $mform->addRule('countrycode', $strrequired, 'required', null, 'client');

        // Geolocation was part of this form before but not any more.
        $mform->addElement('hidden', 'geolocation');
        $mform->setType('geolocation', PARAM_RAW);
        $mform->addHelpButton('geolocation', 'sitegeolocation', 'hub');

        // Admin name was part of this form before but not any more.
        $mform->addElement('hidden', 'contactname');
        $mform->setType('contactname', PARAM_TEXT);
        $mform->addHelpButton('contactname', 'siteadmin', 'hub');

        $mform->addElement('hidden', 'contactphone');
        $mform->setType('contactphone', PARAM_TEXT);
        $mform->addHelpButton('contactphone', 'sitephone', 'hub');

        $mform->addElement('text', 'contactemail', get_string('siteemail', 'hub'),
            array('class' => 'registration_textfield'));
        $mform->addRule('contactemail', $strrequired, 'required', null, 'client');
        $mform->setType('contactemail', PARAM_EMAIL);
        $mform->addHelpButton('contactemail', 'siteemail', 'hub');

        $this->add_checkbox_with_email('emailalert', 'siteregistrationemail', false, get_string('registrationyes'));

        $privacyurl = new moodle_url('https://moodle.com/privacy-notice/');
        $experttipsandinsightsdesc = html_writer::span(get_string('experttipsandinsightsdesc', 'hub', $privacyurl->out()));
        $this->add_checkbox_with_email(
            elementname: 'commnews',
            stridentifier: 'experttipsandinsights',
            highlight: in_array('commnews', $highlightfields),
            checkboxtext: $experttipsandinsightsdesc,
            showhelp: false,
        );

        // TODO site logo.
        $mform->addElement('hidden', 'imageurl', ''); // TODO: temporary.
        $mform->setType('imageurl', PARAM_URL);

        $mform->addElement('checkbox', 'policyagreed', get_string('policyagreed', 'hub'),
            get_string('policyagreeddesc', 'hub', HUB_MOODLEORGHUBURL . '/privacy'));
        $mform->addRule('policyagreed', $strrequired, 'required', null, 'client');

        $mform->addElement('header', 'sitestats', get_string('sendfollowinginfo', 'hub'));
        $mform->setExpanded('sitestats', !empty($highlightfields));
        $mform->addElement('static', 'urlstring', get_string('siteurl', 'hub'), $siteinfo['url']);
        $mform->addHelpButton('urlstring', 'siteurl', 'hub');

        // Display statistic that are going to be retrieve by the sites directory.
        $mform->addElement('static', 'siteinfosummary', get_string('sendfollowinginfo', 'hub'), registration::get_stats_summary($siteinfo));

        // Check if it's a first registration or update.
        if ($registered) {
            $buttonlabel = get_string('updatesiteregistration', 'core_hub');
            $mform->addElement('hidden', 'update', true);
            $mform->setType('update', PARAM_BOOL);
        } else {
            $buttonlabel = get_string('register', 'core_admin');
        }

        $this->add_action_buttons(false, $buttonlabel);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        // Prepare and set data.
        $siteinfo['emailalertnewemail'] = !empty($siteinfo['emailalert']) && !empty($siteinfo['emailalertemail']);
        if (empty($siteinfo['emailalertnewemail'])) {
            $siteinfo['emailalertemail'] = '';
        }
        $siteinfo['commnewsnewemail'] = !empty($siteinfo['commnews']) && !empty($siteinfo['commnewsemail']);
        if (empty($siteinfo['commnewsnewemail'])) {
            $siteinfo['commnewsemail'] = '';
        }

        // Set data. Always require to check policyagreed even if it was checked earlier.
        $this->set_data(['policyagreed' => 0] + $siteinfo);
    }

    /**
     * @deprecated since Moodle 3.11 - MDL-71460 The form elements using this have been converted to checkboxes
     */
    #[\core\attribute\deprecated(
        '\core\hub\site_registration_form::add_checkbox_with_email()',
        since: '3.11',
        mdl: 'MDL-71460',
        final: true,
    )]
    protected function add_select_with_email() {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
    }

    /**
     * Add yes/no checkbox with additional checkbox allowing to specify another email
     *
     * @param string $elementname
     * @param string $stridentifier
     * @param bool $highlight highlight as a new field
     * @param string $checkboxtext The text to show after the text.
     * @param bool $showhelp Show the help icon.
     */
    protected function add_checkbox_with_email(
        string $elementname,
        string $stridentifier,
        bool $highlight = false,
        string $checkboxtext = '',
        bool $showhelp = true,
    ): void {
        $mform = $this->_form;

        $group = [
            $mform->createElement('advcheckbox', $elementname, '', $checkboxtext, ['class' => 'pt-2']),
            $mform->createElement('static', $elementname . 'sep', '', '<br/>'),
            $mform->createElement('advcheckbox', $elementname . 'newemail', '', get_string('usedifferentemail', 'hub'),
                ['onchange' => "this.form.elements['{$elementname}email'].focus();"]),
            $mform->createElement('text', $elementname . 'email', get_string('email'))
        ];

        $element = $mform->addElement('group', $elementname . 'group', get_string($stridentifier, 'hub'), $group, '', false);
        if ($highlight) {
            $element->setAttributes(['class' => $element->getAttribute('class') . ' needsconfirmation mark']);
        }
        $mform->hideif($elementname . 'email', $elementname, 'eq', 0);
        $mform->hideif($elementname . 'newemail', $elementname, 'eq', 0);
        $mform->hideif($elementname . 'email', $elementname . 'newemail', 'notchecked');
        $mform->setType($elementname, PARAM_INT);
        $mform->setType($elementname . 'email', PARAM_RAW_TRIMMED); // E-mail will be validated in validation().
        if ($showhelp) {
            $mform->addHelpButton($elementname . 'group', $stridentifier, 'hub');
        }

    }

    /**
     * Validation of the form data
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // Validate optional emails. We do not use PARAM_EMAIL because it blindly clears the field if it is not a valid email.
        if (!empty($data['emailalert']) && !empty($data['emailalertnewemail']) && !validate_email($data['emailalertemail'])) {
            $errors['emailalertgroup'] = get_string('invalidemail');
        }
        if (!empty($data['commnews']) && !empty($data['commnewsnewemail']) && !validate_email($data['commnewsemail'])) {
            $errors['commnewsgroup'] = get_string('invalidemail');
        }
        return $errors;
    }

    /**
     * Returns the form data
     *
     * @return stdClass
     */
    public function get_data() {
        if ($data = parent::get_data()) {
            // Never return '*newemail' checkboxes, always return 'emailalertemail' and 'commnewsemail' even if not applicable.
            if (empty($data->emailalert) || empty($data->emailalertnewemail)) {
                $data->emailalertemail = null;
            }
            unset($data->emailalertnewemail);
            if (empty($data->commnews) || empty($data->commnewsnewemail)) {
                $data->commnewsemail = null;
            }
            unset($data->commnewsnewemail);

            if (debugging('', DEBUG_DEVELOPER)) {
                // Display debugging message for developers who added fields to the form and forgot to add them to registration::FORM_FIELDS.
                $keys = array_diff(array_keys((array)$data),
                    ['returnurl', 'mform_isexpanded_id_sitestats', 'submitbutton', 'update']);
                if ($extrafields = array_diff($keys, registration::FORM_FIELDS)) {
                    debugging('Found extra fields in the form results: ' . join(', ', $extrafields), DEBUG_DEVELOPER);
                }
                if ($missingfields = array_diff(registration::FORM_FIELDS, $keys)) {
                    debugging('Some fields are missing in the form results: ' . join(', ', $missingfields), DEBUG_DEVELOPER);
                }
            }
        }
        return $data;
    }

}

