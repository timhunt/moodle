<?php

////////////////////////////////////////////////////////////////////////////
/// Blackboard 6.x Examview Questions
///
/// This Moodle class provides all functions necessary to import and export
///
///
////////////////////////////////////////////////////////////////////////////

// Based on default.php, included by ../import.php
/**
 * @package questionbank
 * @subpackage importexport
 */

// NOTE: export from examview always set ishtml
// and there is never different feedbacks for different answers
// it is only provided for geneneral feedback, so many fields in the
// incoming xml can be ignored, thereby simplifiying this code

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/xmlize.php');

class qformat_examview_bbsixplus extends qformat_default {

    private $currentquestion = NULL;
    private $filebase = NULL;
    private $temp_dir = '';

    public function provide_import() {
        return true;
    }

    public function mime_type() {
        return 'application/zip';
    }

    // check if directory exists, maybe create it, return success
    public function check_dir_exists($dir,$create=false) {

        global $CFG;

        $status = true;
        if(!is_dir($dir)) {
            if (!$create) {
                $status = false;
            } else {
                umask(0000);
                $status = mkdir ($dir,$CFG->directorypermissions);
            }
        }
        return $status;
    }

    //Function to check and create the needed dir structure to unzip file to
    public function check_and_create_import_dir($unique_code) {
        global $CFG;

        $status = $this->check_dir_exists($CFG->dataroot."/temp",true);
        if ($status) {
            $status = $this->check_dir_exists($CFG->dataroot."/temp/bbquiz_import",true);
        }
        if ($status) {
            $status = $this->check_dir_exists($CFG->dataroot."/temp/bbquiz_import/".$unique_code,true);
        }

        return $status;
    }

    public function importpostprocess() {
    /// Does any post-processing that may be desired
    /// Argument is a simple array of question ids that
    /// have just been added.

        // need to clean up temporary directory
        removeDirectory($this->temp_dir);
        return true;
    }

    /**
     * process text string from xml file
     * @param array $text bit of xml tree after ['text']
     * @return string processed text.
     */
    public function import_text($text) {
        // quick sanity check
        if (empty($text)) {
            return '';
        }
        $data = $text[0]['#'];
        return trim($data);
    }

    /**
     * return the value of a node, given a path to the node
     * if it doesn't exist return the default value
     * @param array xml data to read
     * @param array path path to node expressed as array
     * @param mixed default
     * @param bool istext process as text
     * @param string error if set value must exist, return false and issue message if not
     * @return mixed value
     */
    public function getpath($xml, $path, $default, $istext=false, $error='') {
        foreach ($path as $index) {
            if (!isset($xml[$index])) {
                if (!empty($error)) {
                    $this->error($error);
                    return false;
                } else {
                    return $default;
                }
            }

            $xml = $xml[$index];
        }

        if ($istext) {
            if (!is_string($xml)) {
                $this->error(get_string('invalidxml', 'qformat_examview_bbsixplus'));
            }
            $xml = trim($xml);
        }

        return $xml;
    }

    /**
     * find all images tags in html text
     * if the source is a valid file inside
     * the unzipped files from the import
     * then it is added to the result
     * @param string text containing urls to files
     * @return array of files
     */
    public function process_img_tags($text) {
        GLOBAL $OUTPUT;
        // step one, find all file refs then add to array

       $files = array();
       preg_match_all('|<img[^>]+src="([^"]*)"|i', $text, $out); // find all src refs
       foreach( $out[1] as $path ) {
          if (strncmp($path, "http:", 5) != 0) {
             $fullpath = $this->filebase . $path; // full path to tmp working area
             $filename = basename($path);

             if(is_readable($fullpath)) {
                $data = new stdclass;
                $data->content = base64_encode(file_get_contents($fullpath));
                $data->encoding = 'base64';
                $data->name = $filename;
                $files[] = $data;
             } else {
                 echo $OUTPUT->notification(get_string('imagenotfound', 'qformat_examview_bbsixplus', $fullpath));
             }

          }

       }
       return $files;
    }

    /**
     * find all images tags in html text
     * and recode them into urls suitable
     * for Moodle filesystem
     * @param string text text to recode
     * @return string
     */
    public function recode_urls($text) {
    // step one, find all file refs then add to array

       preg_match_all('|<img[^>]+src="([^"]*)"|i', $text, $out); // find all src refs
       foreach( $out[1] as $path ) {
          if (strncmp($path, "http:", 5) != 0) {
            $dirpath = dirname($path);
            $text = preg_replace("|$dirpath|","@@PLUGINFILE@@",$text);
          }

       }
       return $text;
    }

    public function remove_imgtags($text) {

        return preg_replace("/<img[^>]+\>/i", '', $text);

    }

    public function process_text($text) {
        $data = array();
        $data['files'] = $this->process_img_tags($text);
        $data['text'] = $this->recode_urls($text);
        $data['format'] = FORMAT_HTML;
        return $data;
    }

    // clean up common text input problems
    public function cleaninput($str) {
        $html_code_list = array(
            "&#8217;" => "'",
            "&#091;" => "[",
            "&#8220;" => "\"",
            "&#8221;" => "\"",
            "&#093;" => "]",
            "&#039;" => "'",
            "&#8211;" => "-",
            "&#8212;" => "-" );

            return strtr($str,$html_code_list);
    }

    protected function add_blank_combined_feedback($question) {
        $question->correctfeedback['text'] = '';
        $question->correctfeedback['format'] = $question->questiontextformat;
        $question->correctfeedback['files'] = array();
        $question->partiallycorrectfeedback['text'] = '';
        $question->partiallycorrectfeedback['format'] = $question->questiontextformat;
        $question->partiallycorrectfeedback['files'] = array();
        $question->incorrectfeedback['text'] = '';
        $question->incorrectfeedback['format'] = $question->questiontextformat;
        $question->incorrectfeedback['files'] = array();
        return $question;
    }

    public function readdata($filename) {
    /// Returns complete file with an array, one item per line
        global $CFG, $COURSE;

        $unique_code = time();
        $temp_dir = $CFG->dataroot."/temp/bbquiz_import/".$unique_code;
        $this->temp_dir = $temp_dir;
        if ($this->check_and_create_import_dir($unique_code)) {
            if(is_readable($filename)) {

                if (!copy($filename, "$temp_dir/bboard.zip")) {
                    $this->error(get_string('couldnotcopy', 'qformat_examview_bbsixplus'));
                }
                if(unzip_file("$temp_dir/bboard.zip", '', false)) {
                    // assuming that the information is in res0001.dat
                    // after looking at 6 examples this was always the case

                    $dom = new DomDocument();

                    if (!$dom->load("$temp_dir/imsmanifest.xml")) {
                      $this->error(get_string('errormanifest', 'qformat_examview_bbsixplus'));
                      exit;
                    }

                    $xpath = new DOMXPath($dom);

                    // We starts from the root element
                    $query = '//resources/resource[1]';
                    $q_base = 'res00001';
                    $q_file = "$temp_dir/res00001.dat";

                    $examfiles = $xpath->query($query);
                    $q_file = $examfiles->item(0)->getAttribute('file');
                    $q_base = $examfiles->item(0)->getAttribute('baseurl');
                    if ($q_base) {
                        $this->filebase = $temp_dir."/".$q_base."/";
                    } else {
                        $this->filebase = $temp_dir."/";
                    }
                    $q_file = "$temp_dir/$q_file";
                    //print $qfile;
                    if (is_file($q_file)) {
                        if (is_readable($q_file)) {
                            $filearray = file($q_file);

                            // Check for Macintosh OS line returns (ie file on one line),
                            // and fix
                            if (ereg("\r", $filearray[0]) AND !ereg("\n", $filearray[0])) {
                                return explode("\r", $filearray[0]);
                            } else {
                                return $filearray;
                            }
                            return false;
                        }
                    }
                    else {
                        $this->error(get_string('noquestiondatafile', 'qformat_examview_bbsixplus'));

                    }
                }
                else {
                    $this->error(get_string('couldnotunzipfile', 'qformat_examview_bbsixplus', $temp_dir . '/' . $filename));
                }
            }
            else {
                $this->error(get_string('couldnotreadfile', 'qformat_examview_bbsixplus'));
            }
        }
        else {
            $this->error(get_string('notempdir', 'qformat_examview_bbsixplus'));
        }
    }

    public function save_question_options($question) {
        return true;
    }


    public function readquestions ($lines) {
        /// Parses an array of lines into an array of questions,
        /// where each item is a question object as defined by
        /// readquestion().

        $text = implode($lines, " ");
        $text = $this->cleaninput($text); // translate strange html enitites

        // This converts xml to big nasty data structure
        // the 0 means keep white space as it is (important for markdown format)
        try {
            $xml = xmlize($text, 0, 'UTF-8', true);
        } catch (xml_format_exception $e) {
            $this->error($e->getMessage(), '');
            return false;
        }

        $questions = array();

        $this->process_tf($xml, $questions);
        $this->process_mc($xml, $questions);
        $this->process_ma($xml, $questions);
        $this->process_fib($xml, $questions);
        $this->process_matching($xml, $questions);
        $this->process_essay($xml, $questions);

        return $questions;
    }

    // do common question import processing here to every qtype
    public function import_headers($thisquestion) {
        global $CFG;

        // this routine initialises the question object
        $question = $this->defaultquestion();
        $this->currentquestion = $question;

        // determine if the question is already escaped html
        $this->ishtml = $this->getpath($thisquestion, array('#', 'BODY', 0, '#', 'FLAGS', 0, '#', 'ISHTML', 0, '@', 'value'), false, false);

        // put questiontext in question object
        $text = $this->getpath($thisquestion, array('#', 'BODY', 0, '#', 'TEXT', 0, '#'), '', true, get_string('importnotext', 'qformat_examview_bbsixplus'));
        if ($this->ishtml) {
            $question->questiontext = $this->recode_urls($text);
            $question->questiontextformat = FORMAT_HTML;
            $question->questiontextfiles = $this->process_img_tags($text);

        } else {
            $question->questiontext = $text;
        }
        // put name in question object we must ensure it is not empty and it is less than 250 chars
        $question->name = shorten_text(strip_tags($question->questiontext), 200);
        $question->name = substr($question->name, 0, 250);
        if (!$question->name) $question->name = get_string('defaultname', 'qformat_examview_bbsixplus') . $thisquestion["@"]["id"];

        $text = $this->getpath($thisquestion, array('#', 'GRADABLE', 0, '#', 'FEEDBACK_WHEN_CORRECT', 0, '#'), $question->generalfeedback, true);
        if ($this->ishtml) {
            $question->generalfeedback = $this->recode_urls($text);
            $question->generalfeedbackformat = FORMAT_HTML;
            $question->generalfeedbackfiles = $this->process_img_tags($text);

        } else {
            $question->generalfeedback = $text;
        }
        $question->defaultmark = 1;
        return $question;
    }

//----------------------------------------
// Process True / False Questions
//----------------------------------------
public function process_tf($xml, &$questions) {

    if (isset($xml["POOL"]["#"]["QUESTION_TRUEFALSE"])) {
        $tfquestions = $xml["POOL"]["#"]["QUESTION_TRUEFALSE"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($tfquestions); $i++) {

        $thisquestion = $tfquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question->qtype = TRUEFALSE;
        $question->single = 1; // Only one answer is allowed

        $choices = $thisquestion["#"]["ANSWER"];

        $correct_answer =
            $thisquestion["#"]["GRADABLE"][0]["#"]["CORRECTANSWER"][0]["@"]["answer_id"];

        // first choice is true, second is false.
        $id = $choices[0]["@"]["id"];
        $feedback = $this->getpath($thisquestion, array('#', 'GRADABLE', 0, '#', 'FEEDBACK_WHEN_CORRECT', 0, '#'), '', true);
        if (strcmp($id, $correct_answer) == 0) {  // true is correct
            $question->correctanswer = $question->answer = true;
        } else {  // false is correct
            $question->correctanswer = $question->answer = false;
        }

        $question->feedbacktrue = $this->process_text($feedback);
        $question->feedbackfalse = $this->process_text($feedback);

        $questions[] = $question;
    }
}

//----------------------------------------
// Process Multiple Choice Questions
//----------------------------------------
public function process_mc($xml, &$questions) {

    if (isset($xml["POOL"]["#"]["QUESTION_MULTIPLECHOICE"])) {
        $mcquestions = $xml["POOL"]["#"]["QUESTION_MULTIPLECHOICE"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($mcquestions); $i++) {

        $thisquestion = $mcquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question = $this->add_blank_combined_feedback($question);

        $question->qtype = MULTICHOICE;
        $question->single = 1; // Only one answer is allowed

        $choices = $thisquestion["#"]["ANSWER"];
        for ($j = 0; $j < sizeof ($choices); $j++) {

            $choice = trim($choices[$j]["#"]["TEXT"][0]["#"]);
            // put this choice in the question object.
            $ans = new stdClass();

            $ans->answer = $this->process_text($choice);

            $id = $choices[$j]["@"]["id"];
            $correct_answer_id =
                $thisquestion["#"]["GRADABLE"][0]["#"]["CORRECTANSWER"][0]["@"]["answer_id"];
            // if choice is the answer, give 100%, otherwise give 0%
            if (strcmp ($id, $correct_answer_id) == 0) {
                $ans->fraction = 1;
                $ans->feedback = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', $j, '#', 'FEEDBACK_WHEN_CORRECT', 0, '#'), '', true));
            } else {
                $ans->fraction = 0;
                $ans->feedback = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', $j, '#', 'FEEDBACK_WHEN_INCORRECT', 0, '#'), '', true));
            }

            $question->answer[$j] = $ans->answer;
            $question->fraction[$j] = $ans->fraction;
            $question->feedback[$j] = $ans->feedback;
        }

        $questions[] = $question;
    }
}

//----------------------------------------
// Process Multiple Choice Questions With Multiple Answers
//----------------------------------------
public function process_ma($xml, &$questions) {

    if (isset($xml["POOL"]["#"]["QUESTION_MULTIPLEANSWER"])) {
        $maquestions = $xml["POOL"]["#"]["QUESTION_MULTIPLEANSWER"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($maquestions); $i++) {

        $thisquestion = $maquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question->qtype = MULTICHOICE;
        $question->defaultmark = 1;
        $question->single = 0; // More than one answers allowed

        $question = $this->add_blank_combined_feedback($question);

        $choices = $thisquestion["#"]["ANSWER"];
        $correctanswers = $thisquestion["#"]["GRADABLE"][0]["#"]["CORRECTANSWER"];

        for ($j = 0; $j < sizeof ($choices); $j++) {

            $choice = $this->getpath($choices[$j], array('#', 'TEXT', 0, '#'), '', true);

            // put this choice in the question object.

            $question->answer[$j] = $this->process_text($choice);

            $correctanswercount = sizeof($correctanswers);
            $id = $choices[$j]["@"]["id"];
            $iscorrect = 0;
            for ($k = 0; $k < $correctanswercount; $k++) {

                $correct_answer_id = trim($correctanswers[$k]["@"]["answer_id"]);
                if (strcmp ($id, $correct_answer_id) == 0) {
                    $iscorrect = 1;
                }

            }
            if ($iscorrect) {
                $question->fraction[$j] = floor(100000/$correctanswercount)/100000; // strange behavior if we have more than 5 decimal places
                $question->feedback[$j] = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', $j, '#', 'FEEDBACK_WHEN_CORRECT', 0, '#'), '', true));
            } else {
                $question->fraction[$j] = 0;
                $question->feedback[$j] = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', $j, '#', 'FEEDBACK_WHEN_INCORRECT', 0, '#'), '', true));
            }
        }

        $questions[] = $question;
    }
}

//----------------------------------------
// Process Fill in the Blank Questions
//----------------------------------------
public function process_fib($xml, &$questions) {

    if (isset($xml["POOL"]["#"]["QUESTION_FILLINBLANK"])) {
        $fibquestions = $xml["POOL"]["#"]["QUESTION_FILLINBLANK"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($fibquestions); $i++) {

        $thisquestion = $fibquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question->qtype = SHORTANSWER;
        $question->usecase = 0; // Ignore case

        $question->answer[] = $this->getpath($thisquestion, array('#', 'ANSWER', 0, '#', 'TEXT', 0, '#'), '', true);

        $question->fraction[] = 1;
        $question->feedback = array();

        if (is_array( $thisquestion['#']['GRADABLE'][0]['#'] )) {
            $question->feedback[0] = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', 0, '#', 'FEEDBACK_WHEN_CORRECT', 0, '#'), '', true));
        }
        else {
            $question->feedback[0] = array('text'=>'', 'format'=>FORMAT_HTML);
        }
        if (is_array( $thisquestion["#"]["GRADABLE"][0]["#"] )) {
            $question->feedback[1] = $this->process_text($this->getpath($thisquestion, array('#', 'GRADABLE', 0, '#', 'FEEDBACK_WHEN_INCORRECT', 0, '#'), '', true));
        }
        else {
            $question->feedback[1] = array('text'=>'', 'format'=>FORMAT_HTML);
        }

        $questions[] = $question;
    }
}

//----------------------------------------
// Process Matching Questions
//----------------------------------------
public function process_matching($xml, &$questions) {

    if (isset($xml["POOL"]["#"]["QUESTION_MATCH"])) {
        $matchquestions = $xml["POOL"]["#"]["QUESTION_MATCH"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($matchquestions); $i++) {

        $thisquestion = $matchquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question->qtype = MATCH;

        $question = $this->add_blank_combined_feedback($question);

        $choices = $thisquestion["#"]["CHOICE"];
        for ($j = 0; $j < sizeof ($choices); $j++) {

            $subquestion = NULL;

            $choice = $choices[$j]["#"]["TEXT"][0]["#"];
            $choice_id = $choices[$j]["@"]["id"];

            $question->subanswers[] = trim($choice);

            $correctanswers = $thisquestion["#"]["GRADABLE"][0]["#"]["CORRECTANSWER"];
            for ($k = 0; $k < sizeof ($correctanswers); $k++) {

                if (strcmp($choice_id, $correctanswers[$k]["@"]["choice_id"]) == 0) {

                    $answer_id = $correctanswers[$k]["@"]["answer_id"];

                    $answers = $thisquestion["#"]["ANSWER"];
                    for ($m = 0; $m < sizeof ($answers); $m++) {

                        $answer = $answers[$m];
                        $current_ans_id = $answer["@"]["id"];
                        if (strcmp ($current_ans_id, $answer_id) == 0) {

                            $subquestion = $this->process_text($this->getpath($answer, array('#', 'TEXT', 0, '#'), '', true));

                            $question->subquestions[] = $subquestion;
                            break;

                        }

                    }

                    break;

                }

            }

        }
        while(count($question->subquestions) < count($question->subanswers)) {
            // add a blank question until there is a blank for each subanswer,
            // this will allow you to have more answers than questions which
            // examview allows
            $subquestion = array();
            $subquestion['text'] =  '';
            $subquestion['format'] = FORMAT_HTML;
            $subquestion['files'] = array();

            $question->subquestions[] = $subquestion;
        }

        $questions[] = $question;

    }
}

//----------------------------------------
// Process Essay Questions
//----------------------------------------
public function process_essay($xml, &$questions) {
    if (isset($xml["POOL"]["#"]["QUESTION_ESSAY"])) {
        $essayquestions = $xml["POOL"]["#"]["QUESTION_ESSAY"];
    }
    else {
        return;
    }

    for ($i = 0; $i < sizeof ($essayquestions); $i++) {
        $thisquestion = $essayquestions[$i];
        $question = $this->import_headers($thisquestion);

        $question->qtype = ESSAY;
        $question->defaultmark = 1;
        $question->responseformat = 'editor';
        $question->responsefieldlines = 15;
        $question->attachments = 0;
        $question->graderinfo =  $this->process_text($this->getpath($thisquestion, array('#', 'ANSWER', 0, '#', 'TEXT', 0, '#'), '', true));
        $questions[] = $question;
    }
}

} // close object

function removeDirectory($path) {
    $dir = new DirectoryIterator($path);
        foreach ($dir as $fileinfo) {
            if ($fileinfo->isFile() || $fileinfo->isLink()) {
                unlink($fileinfo->getPathName());
            } elseif (!$fileinfo->isDot() && $fileinfo->isDir()) {
                removeDirectory($fileinfo->getPathName());
            }
        }
        rmdir($path);
}
