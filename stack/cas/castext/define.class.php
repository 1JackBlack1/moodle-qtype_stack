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
 * Define blocks allow one to (re)define variables in the middle of castext.
 * They are meant for writing out for-blocks but no one says that you could not use them.
 *
 * @copyright  2013 Aalto University
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../conditionalcasstring.class.php');
require_once(dirname(__FILE__) . '/../casstring.class.php');

class stack_cas_castext_define extends stack_cas_castext_block {

    public function extract_attributes(&$tobeevaluatedcassession, $conditionstack = null) {
        foreach ($this->get_node()->get_parameters() as $key => $value) {
            $cs = null;
            if ($conditionstack === null || count($conditionstack) === 0) {
                $cs = new stack_cas_casstring($value);
            } else {
                $cs = new stack_cas_conditionalcasstring($value, $conditionstack);
            }

            $cs->validate($this->security, $this->syntax, $this->insertstars);
            $cs->set_key($key, true);
            $tobeevaluatedcassession->add_vars(array($cs));
        }
    }

    public function content_evaluation_context($conditionstack = array()) {
        return $conditionstack;
    }

    public function process_content($evaluatedcassession, $conditionstack = null) {
        return false;
    }

    public function clear() {
        $this->get_node()->destroy_node();
    }

    public function validate_extract_attributes() {
        $r = array();
        foreach ($this->get_node()->get_parameters() as $key => $value) {
            $cs = new stack_cas_casstring($value);
            $cs->validate($this->security, $this->syntax, $this->insertstars);
            $cs->set_key($key, true);
            $r[] = $cs;
        }
        return $r;
    }


}