<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script lets the user create or edit question tests for a question.
 *
 * @copyright  2012 the Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/stack/utils.class.php');


// Get the parameters from the URL.
$contextid = required_param('contextid', PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

// Login and check permissions.
$context = context::instance_by_id($contextid);
require_login();
require_capability('moodle/site:config', $context);
$PAGE->set_url('/question/type/stack/replacedollars.php', array('contextid' => $context->id));
$PAGE->set_context($context);
$title = stack_string('replacedollarstitle', $context->get_context_name());
$PAGE->set_title($title);

// Load the necessary data.
$categories = question_category_options(array($context));
$categories = reset($categories);

$fixer = new qtype_stack_dollar_fixer();
$questionfields = array('questiontext', 'generalfeedback');
$qtypestackfields = array('specificfeedback', 'prtcorrect', 'prtpartiallycorrect', 'prtincorrect');
$prtnodefields = array('truefeedback', 'falsefeedback');
$anychanges = false;

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);

foreach ($categories as $key => $category) {
    list($categoryid) = explode(',', $key);
    echo $OUTPUT->heading($category, 3);

    $questions = $DB->get_records('question',
            array('category' => $categoryid, 'qtype' => 'stack'), 'id');
    echo html_writer::tag('p', stack_string('replacedollarscount', count($questions)));

    foreach ($questions as $question) {
        echo $OUTPUT->heading(format_string($question->name), 4);

        $changes = false;
        foreach ($questionfields as $field) {
            $changes = $fixer->fix_question_field($question, $field) || $changes;
        }
        if ($changes && $confirm) {
            $DB->update_record('question', $question);
        }
        $anychanges = $anychanges || $changes;

        $stackoptions = $DB->get_record('qtype_stack', array('questionid' => $question->id), '*', MUST_EXIST);
        $changes = false;
        foreach ($qtypestackfields as $field) {
            $changes = $fixer->fix_question_field($stackoptions, $field) || $changes;
        }
        if ($changes && $confirm) {
            $DB->update_record('qtype_stack', $stackoptions);
        }
        $anychanges = $anychanges || $changes;

        $prtnodes = $DB->get_records('qtype_stack_prt_nodes', array('questionid' => $question->id), 'prtname,nodename');
        foreach ($prtnodes as $node) {
            $changes = false;
            foreach ($prtnodefields as $field) {
                $changes = $fixer->fix_question_field($node, $field) || $changes;
            }
            if ($changes && $confirm) {
                $DB->update_record('qtype_stack_prt_nodes', $node);
            }
            $anychanges = $anychanges || $changes;
        }

        $attemptdata = $DB->get_records_sql("
                    SELECT qasd.*
                      FROM {question_attempt_step_data} qasd
                      JOIN {question_attempt_steps} qas ON qasd.attemptstepid = qas.id
                      JOIN {question_attempts} qa ON qa.id = qas.questionattemptid
                     WHERE qa.questionid = :questionid
                       AND qasd.name IN ('_questiontext', '_feedback')
                ", array('questionid' => $question->id));
        foreach ($attemptdata as $data) {
            $changes = $fixer->fix_question_field($data, 'value');
            if ($changes && $confirm) {
                $DB->update_record('question_attempt_step_data', $data);
            }
            $anychanges = $anychanges || $changes;
        }
    }
}

if (!$anychanges) {
    echo html_writer::tag('p', stack_string('replacedollarsnoproblems'));

} else if ($confirm) {
    echo html_writer::tag('p', get_string('changessaved'));

} else {
    echo $OUTPUT->single_button(new moodle_url('/question/type/stack/replacedollars.php',
            array('contextid' => $context->id, 'confirm' => 1)), get_string('savechanges'));
}
echo html_writer::tag('p', html_writer::link(new moodle_url('/question/type/stack/replacedollarsindex.php'),
        get_string('back')));

echo $OUTPUT->footer();


/**
 * This helper class fixes old-style maths delimiters in HTML.
 */
class qtype_stack_dollar_fixer {
    /** @var array bit of HTML that we want to un-escape when displaying the updated HTML. */
    protected $newsearch;
    /** @var array what we want to replace $newsearch with. */
    protected $newreplace;
    /** @var array bit of HTML that we want to un-escape and modify when displaying the original HTML. */
    protected $oldsearch;
    /** @var array what we want to replace $oldsearch with. */
    protected $oldreplace;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->search = array(s('<ins>\[</ins>'), s('<ins>\]</ins>'),
                s('<ins>\(</ins>'), s('<ins>\)</ins>'));
        $this->replace = array('<del>$$</del><ins>\[</ins>', '<del>$$</del><ins>\]</ins>',
                '<del>$</del><ins>\(</ins>', '<del>$</del><ins>\)</ins>');
    }

    /**
     * Update maths delimiters in one field of an object, outputting the before
     * and after HTML for review.
     * @param stdClass $question an object.
     * @param field $field the name of one of its fields.
     * @return boolean whether any change was made to the field.
     */
    public function fix_question_field($question, $field) {
        $newtext = stack_maths::replace_dollars($question->{$field});
        if ($newtext == $question->{$field}) {
            return false;
        }

        $markedup = stack_maths::replace_dollars($question->{$field}, '<ins>', '</ins>');
        echo html_writer::tag('p', stack_string('replacedollarsin', $field));
        echo html_writer::tag('pre', str_replace($this->search, $this->replace,
                s($markedup)), array('class' => 'questiontext'));
        echo html_writer::tag('div', stack_ouput_castext($newtext), array('class' => 'questiontext'));

        $question->{$field} = $newtext;
        return true;
    }
}
