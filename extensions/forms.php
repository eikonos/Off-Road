<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# HTML Forms extension
#
# Purpose:
# Create forms as self-contained objects which validate.
#
# See documentation for usage and customization details.
#

abstract class forminput
{
    protected $name             = null;
    protected $id               = null;
    protected $label            = null;
    protected $value            = null;
    protected $valid            = true;
    protected $err_msg          = null;
    protected $validate_func    = array();
    protected $validate_data    = null;
    protected $returns_value    = true;
    protected $do_trim          = true;  # call trim() on the value?
    protected $already_loaded   = false;
    var $is_visible             = true;

    function __construct() {
        return $this;
    }

    function init($name, &$form) {
        if (!isset($this->name)){$this->name($name);}
        # in order for css id to be more unique, use form name and input name combined
        if (!isset($this->id)){$this->id($form->_meta->attributes->id."-".$this->get_name());}
        if (!isset($this->label)){$this->label($name);}
        if ($form->_meta->is_submitting){$this->load_value();}
        # validate
        if (null == $this->validate_func) {
            $this->valid = true;    # no validation required
        } else {
            foreach ($this->validate_func as $validate_func) {
                if (method_exists($form, $validate_func)) {
                    $func = $validate_func;
                    $this->valid = $form->{$func}($this);
                } else {
                    if (function_exists($validate_func)) {
                        # note: using call_user_func fails to pass by reference, so changes are lost
                        $func = $validate_func;
                        $this->valid = $func($this, $form);
                    } else {
                        throw new Exception("Error: Validation function not found {$validate_func}()");
                        $this->valid = false;
                    }
                }
                if (! $this->valid){break;}
            }
        }
        return $this->valid;
    }

    # only call this when the form is being submitted because it clears the default value
    function load_value() {
        $this->value = null;
        if (isset($_POST[$this->name])) {
            $this->value = ($this->do_trim ? trim($_POST[$this->name]) : $_POST[$this->name]);
            if ("" == $this->value){$this->value = null;}
            $this->already_loaded = true;
        }
        return $this->value;
    }

    function name($name){$this->name = $name;return $this;}
    function id($id){$this->id = $id;return $this;}
    function get_name(){return $this->name;}
    function get_id(){return $this->id;}
    function label($label){$this->label = ucfirst(str_replace("_", " ", $label));return $this;}
    function label_raw($label){$this->label = $label;return $this;}
    function get_label(){return $this->label;}
    # don't allow setting the value if it has already been set via loading
    function value($value, $force = false){if(!$this->already_loaded || $force){$this->value = $value;}return $this;}
    function get_value(){return $this->value;}
    function valid($valid){$this->valid = $valid; return $this;}
    function get_valid(){return $this->valid;}
    function returns_value(){return $this->returns_value;}
    function get_validate_data(){return $this->validate_data;}
    function get_err_msg(){return $this->err_msg;}
    function err_msg($err_msg){$this->err_msg = $err_msg; return $this;}
    function attr($name, $value){$this->attributes[$name] = $value; return $this;}
    function validate($validate_func, $validate_data = null, $err_msg = null) {
        # validation function is always an array
        if (null !== $validate_func) {
            $this->validate_func = (iterable($validate_func) ? $validate_func : array($validate_func));
        }
        if (null != $validate_data){$this->validate_data = $validate_data;}
        if (null != $err_msg){$this->err_msg = $err_msg;}
        return $this;
    }

    protected function output_attributes() {
        ob_start();
        echo "<input";
        foreach ($this->attributes as $name => $value) {
            echo " $name=\"$value\"";
        }
        echo " />";
        return ob_get_clean();
    }

    function __toString() {
        $this->attributes["type"]   = $this->type;
        $this->attributes["name"]   = $this->name;
        $this->attributes["value"]  = htmlentities($this->value, ENT_QUOTES, "UTF-8", true);
        $this->attributes["id"]     = $this->id;
        if ($this->size > 0)
            $this->attributes["size"] = $this->size;
        if (isset($this->readonly) and $this->readonly)
            $this->attributes["readonly"] = "readonly";
        if (isset($this->autofocus) and $this->autofocus)
            $this->attributes["autofocus"] = "autofocus";
        return $this->output_attributes();
    }

    function print_label() {
        return "<label for=\"{$this->id}\">{$this->label}</label>";
    }
}

function button(){return new button();}
class button extends forminput
{
    protected $returns_value = false;
    var $btn_function = null;
    var $extra_data = null;
    function btn_function($btn_function){$this->btn_function = $btn_function;return $this;}
    function extra_data($extra_data){$this->extra_data =& $extra_data;return $this;}
    function disabled($disabled){$this->disabled = (bool)($disabled); return $this;}
    function autofocus($autofocus){$this->autofocus = (bool)($autofocus); return $this;}
    function init($name, &$form) {
        parent::init($name, $form);
        if (!method_exists($form, $this->btn_function)) {
            throw new Exception("Error: form {$form->_meta->attributes->name} is missing function '{$this->btn_function}' for {$name} button.");
        }
        if (isset($_POST[$this->name])) {
            # this button is being submitted
            $form->{$this->btn_function}($this);
        }
        return true;
    }
    function load_value(){}
    function __toString() {
        # do not set 'type'='button' because that prevents the button from submitting the form
        $this->attributes["name"]   = $this->name;
        $this->attributes["value"]  = $this->label;
        $this->attributes["id"]     = $this->id;
        if (isset($this->disabled) and $this->disabled)
            $this->attributes["disabled"] = "disabled";
        if (isset($this->autofocus) and $this->autofocus)
            $this->attributes["autofocus"] = "autofocus";
        return $this->output_attributes();
    }

    protected function output_attributes() {
        ob_start();
        echo "<button";
        foreach ($this->attributes as $name => $value) {
            echo " $name=\"$value\"";
        }
        echo ">{$this->name}</button>";
        return ob_get_clean();
    }
}

function checkbox(){return new checkbox();}
class checkbox extends forminput
{
    var $readonly = false;
    function readonly($readonly){$this->readonly = (bool)($readonly); return $this;}
    function disabled($readonly){$this->readonly = (bool)($readonly); return $this;}
    function load_value() {
        # only call this when the form is being submitted because it clears the default value
        # no POST value is submitted if the checkbox is not checked
        $this->value = (isset($_POST[$this->name]));
        $this->already_loaded = true;
        return $this->value;
    }
    function __toString() {
        $this->attributes["type"] = "checkbox";
        $this->attributes["name"] = $this->name;
        $this->attributes["id"]   = $this->id;
        if ($this->readonly){$this->attributes["disabled"] = "disabled";}
        if ($this->value){$this->attributes["checked"] = "checked";}
        return $this->output_attributes();
    }
}

function checkboxset($checkboxes){return new checkboxset($checkboxes);}
class checkboxset extends forminput
{
    var $set = array();
    var $readonly = false;

    function __construct($checkboxes) {
        foreach ($checkboxes as $name => $state) {
            $this->set[$name] = checkbox()->value($state)->label($name);
        }
        return $this;
    }

    function init($name, &$form) {
        # init the checkboxes before loading their values
        foreach ($this->set as $checkbox_name => $checkbox) {
            $checkbox->init($name."_".$checkbox_name, $form);
        }
        return parent::init($name, $form);
    }

    function load_value() {
        # only call this when the form is being submitted because it clears the default value
        # no POST value is submitted if the checkboxset is not checked
        $this->value = array();
        foreach ($this->set as $checkbox_name => $checkbox) {
            $this->value[$checkbox_name] = $checkbox->load_value();
        }
        $this->already_loaded = true;
        return $this->value;
    }

    function __toString() {
        $html = "<span id=\"checkbox-set-{$this->id}\" class=\"checkbox-set\">";
        foreach ($this->set as $checkbox_name => $checkbox) {
            $html .= "<span id=\"checkbox-set-item-{$this->id}-$checkbox_name\" class=\"checkbox-set-item checkbox-set-item-{$this->id}\">".$checkbox." ".$checkbox->print_label()."</span>";
        }
        $html .= "</span>";
        return $html;
    }
}

function databox(){return new databox();}
class databox extends forminput
{
    function load_value() {
        return $this->value;
    }
    function __toString() {
        return "";
    }
}

function fileupload(){return new fileupload();}
class fileupload extends forminput
{
    function init($name, &$form) {
        $ret = parent::init($name, $form);
        $v = $this->get_validate_data();
        if ($v && isset($v['size'])) {
            $form->set_max_file_size($v['size']);
        }
        return $ret;
    }
    function load_value() {
        $this->valid = false;
        if (isset($_FILES[$this->name]["tmp_name"])) {
            $tmp_name = $_FILES[$this->name]["tmp_name"];
            if (is_uploaded_file($tmp_name)) {
                if (!isset($this->value)) {
                    $this->value = new stdClass;
                }
                $this->value->filename = $tmp_name;
                $this->value->original_filename = $_FILES[$this->name]['name'];
                $this->value->type = $_FILES[$this->name]['type'];
                $this->value->size = $_FILES[$this->name]['size'];
                $this->valid = true;
            } else {
                $this->err_msg("Please select a file.");
            }
        }
        return $this->value;
    }
    function __toString() {
        return "<input type=\"file\" name=\"{$this->name}\" />";
    }
}

function hidden(){return new hidden();}
class hidden extends forminput
{
    var $is_visible = false; # it does output on the page, but the user doesn't see it
    function load_value(){}
    function __toString() {
        return "<input type=\"hidden\" name=\"{$this->name}\" value=\"".htmlentities($this->value, ENT_QUOTES, "UTF-8", true)."\" />";
    }
}

function password(){return new password();}
class password extends textbox
{
    var $type = "password";
}

function radio(){return new radio();}
class radio extends forminput
{
    function __toString() {
        ob_start();
        foreach ($this->validate_data as $option) {
            echo "<label for=\"{$this->name}_{$option['value']}\">";
            $this->attributes["type"] = "radio";
            $this->attributes["name"] = $this->name;
            $this->attributes["id"] = "{$this->name}_{$option['value']}";
            $this->attributes["value"] = $option['value'];
            unset($this->attributes["checked"]);
            if ($this->get_value() == $option['value']){$this->attributes["checked"] = "checked";}
            echo $this->output_attributes();
            echo "{$option['label']}</label>";
        }
        return ob_get_clean();
    }

    # this provides the option inputs as an array that can be looped through in a template to enable custom formatting
    #   between options, such as adding text elements associated with particular options
    function option_set() {
        $options = array();
        $this->attributes["type"] = "radio";
        $this->attributes["name"] = $this->name;
        foreach ($this->validate_data as $option) {
            ob_start();
            echo "<label for=\"{$this->name}_{$option['value']}\">";
            $this->attributes["id"] = "{$this->name}_{$option['value']}";
            $this->attributes["value"] = $option['value'];
            $this->attributes["checked"] = ($this->get_value() == $option['value']) ? "checked" : "";
            unset($this->attributes["checked"]);
            if ($this->get_value() == $option['value']){$this->attributes["checked"] = "checked";}
            echo $this->output_attributes();
            echo "{$option['label']}</label>";
            $options[$option['value']] = ob_get_clean();
        }
        return $options;
    }
}

function selectinput(){return new selectinput();}
class selectinput extends forminput
{
    function __toString() {
        ob_start();
        echo "<select name=\"{$this->name}\">";
        if (count($this->validate_data) > 1)    # if there is only one item, then don't add a blank default item
        {
            echo "<option></option>";
        }
        foreach ($this->validate_data as $option) {
            if ($option['value'] == $this->value) {
                echo '<option selected="yes" value="'.$option['value'].'">'.$option['label'].'</option>';
            } else {
                echo '<option value="'.$option['value'].'">'.$option['label'].'</option>';
            }
        }
        echo "</select>";
        return ob_get_clean();
    }
}

function submit_button(){return new submit_button();}
class submit_button extends forminput
{
    protected $returns_value = false;
    function load_value(){}
    function __toString() {
        return "<input type=\"submit\" name=\"{$this->name}\" id=\"{$this->name}\" value=\"{$this->label}\" />";
    }
}

function textarea(){return new textarea();}
class textarea extends forminput
{
    var $rows = null;
    var $cols = null;
    function rows($rows){$this->rows = $rows; return $this;}
    function cols($cols){$this->cols = $cols; return $this;}
    function __toString() {
        ob_start();
        echo "<textarea";
        $this->attributes["name"] = $this->name;
        $this->attributes["wrap"] = "soft";
        if ($this->rows){$this->attributes["rows"] = $this->rows;}
        if ($this->cols){$this->attributes["cols"] = $this->cols;}
        foreach ($this->attributes as $name => $value) {
            echo " $name=\"$value\"";
        }
        echo ">";
        echo htmlentities($this->value, ENT_QUOTES, "UTF-8", true);
        echo "</textarea>";
        return ob_get_clean();
    }
}

function textbox(){return new textbox();}
class textbox extends forminput
{
    var $type = "text";
    var $size = 20;
    var $readonly = "";
    var $autofocus = "";
    function size($size){$this->size = $size; return $this;}
    function readonly($readonly){$this->readonly = $readonly; return $this;}
    function autofocus($autofocus){$this->autofocus = ($autofocus ? " autofocus" : ""); return $this;}
}

abstract class forms
{
    abstract function create_inputs();
    abstract function validate();
    abstract function done($results);

    function __construct($cached_vars = null) {
        $this->_meta = new stdClass();
        if (iterable($cached_vars)) {
            foreach ($cached_vars as $k => $cached_var) {
                $this->_meta->$k = $cached_var;
            }
        }
        $charset = "accept-charset";
        $this->_meta->attributes           = new stdClass;
        $this->_meta->attributes->name     = get_class($this);
        $this->_meta->attributes->id       = get_class($this);
        $this->_meta->attributes->class    = "";
        $this->_meta->attributes->method   = "POST";
        $this->_meta->attributes->action   = $_SERVER['REQUEST_URI'];    # default to current url
        $this->_meta->attributes->$charset = "UTF-8";

        $this->_meta->submit_button        = new stdClass;
        $this->_meta->submit_button->name  = "submit_button"; # name should not be "submit", otherwise JavaScript submit() function won't work
        $this->_meta->submit_button->label = "Submit";
        $this->_meta->submit_button->show  = true;

        $this->_meta->is_submitting = (isset($_POST["submitting"]) && ($_POST["submitting"] == $this->_meta->attributes->name));
        $this->_meta->all_valid = true;                    # set to false if any items are invalid
        $this->_meta->is_complete = false;

        $this->_meta->max_file_size = 0;

        $this->submitting = hidden()->value($this->_meta->attributes->name);
        global $request;
        if (isset($request['route']['parameters']['csrf'])) {
            $this->csrf = hidden()->value($request['route']['parameters']['csrf']);
        }
        $this->create_inputs();
        if (!isset($this->{$this->_meta->submit_button->name})) {
            # set $this->submit_button = null; to prevent it being created
            $this->{$this->_meta->submit_button->name} = submit_button()->label($this->_meta->submit_button->label);
        }
        if ($this->_meta->max_file_size > 0 && !isset($this->MAX_FILE_SIZE)) {
            $this->MAX_FILE_SIZE = hidden()->value($this->_meta->max_file_size);
            $this->_meta->attributes->enctype = "multipart/form-data";
        }
        if ($this->_meta->is_submitting) {
            # if all the inputs are valid, extra validation function passes and submit button was pressed
            if ($this->_meta->all_valid && ($this->_meta->all_valid = $this->validate()) && isset($_POST[$this->_meta->submit_button->name])) {
                $this->done($this->get_results());
                $this->_meta->is_complete = true;
            }
        }
        return $this;
    }

    function show_errors(){return $this->_meta->is_submitting && !$this->_meta->all_valid;}
    function all_valid(){return $this->_meta->all_valid;}
    function is_complete(){return $this->_meta->is_complete;}
    function set_action($action){$this->_meta->attributes->action = $action;}
    function get_action(){return $this->_meta->attributes->action;}
    function submit_label($submit_label){$this->_meta->submit_button->label = $submit_label;}
    function set_max_file_size($max_file_size) {
        if ($max_file_size > $this->_meta->max_file_size) $this->_meta->max_file_size = $max_file_size;
    }

    function __get($name) {
        return $this->$name;
    }

    public function __isset($name) {
        if (property_exists($this, $name))    # note isset() won't work with variable set to NULL
        {
            return true;
        }
    }

    function &get_results() {
        $results = new stdClass();
        foreach ($this->inputs() as $name => $item) {
            if ("_" != $name[0] && $item->returns_value()) {
                $results->{$item->get_name()} = $item->get_value();
            }
        }
        return $results;
    }

    function __set($name, $value) {
        if (is_a($value, "forminput")) {
            if (!$value->init($name, $this)) {
                $this->_meta->all_valid = false;
            }
        }
        $this->$name = $value;
    }

    function &inputs() {
        $inputs = array();
        foreach (get_object_vars($this) as $name => $input) {
            if (!in_array($name, array("submitting", "csrf", "submit_button")) && is_a($input, "forminput")) {
                $inputs[$name] = $input;
            }
        }
        return $inputs;
    }

    function form_begin() {
        ob_start();
        echo "<form";
        foreach ($this->_meta->attributes as $name => $value) {
            echo " $name=\"$value\"";
        }
        echo ">";
        return ob_get_clean();
    }
    function form_end() {
        return "</form>";
    }

    public function __toString() {
        ob_start();
        echo $this->form_begin();
        foreach ($this->inputs() as $input) {
            echo "".$input;
        }
        echo $this->form_end();
        return ob_get_clean();
    }

    #
    # validation functions
    #
    static function number_minmax(&$item) {
        $v = $item->get_validate_data();
        $valid = $item->get_value() >= $v['min'] && $item->get_value() <= $v['max'];
        if (!$valid && null == $item->get_err_msg()) {
            $item->err_msg("Please enter a value between {$v['min']} and {$v['max']}.");
        }
        return $valid;
    }

    static function string_minmax(&$item) {
        $len = strlen($item->get_value());
        $v = $item->get_validate_data();
        return $valid = $len >= $v['min'] && $len <= $v['max'];
    }

    static function validate_selected(&$item) {
        if (null === $item->get_value())    # note: 0 == null, but 0 !== null
        {
            return false;
        }
        $v = $item->get_validate_data();
        foreach ($v as $option) {
            if ($item->get_value() == $option['value']) {
                return true;
            }
        }
        return false;
    }

    static function validate_checked(&$item) {
        return $item->get_value();
    }

    static function validate_phone(&$item) {
        return (1 == preg_match('/^\((\d){3}\) (\d){3}-(\d){4}$/', $item->get_value(), $matches));
    }

    static function validate_phone_or_blank(&$item) {
        $value = $item->get_value();
        return (null == $value || 1 == preg_match('/^\((\d){3}\) (\d){3}-(\d){4}$/', $value, $matches));
    }

    static function validate_reserverd_characters(&$item) {
        if (forms::string_minmax($item)) {
            $value = $item->get_value();
            $v = $item->get_validate_data();
            $found = preg_match("/[{$v['reserved_characters']}]*([^{$v['reserved_characters']}]+)/", $value, $matches);
            if ($found == 0) {
                return true;
            }
            $bad_chars = str_replace(" ", "<space>", $matches[1]);    # if space is not allowed, change it so it shows up on the page.
            $item->err_msg("{$item->get_label()} cannot contain $bad_chars");
        }
        return false;
    }

    static function validate_reserved_words(&$item) {
        $value = $item->get_value();
        $v = $item->get_validate_data();
        foreach ($v['reserved_words'] as $reserved) {
            if (0 == strcasecmp($value, $reserved)) {
                $item->err_msg("{$item->get_label()} cannot be '$reserved' because that is a reserved keyword.");
                return false;
            }
        }
        return true;
    }

    static function validate_date(&$item) {
        if (null != ($value = $item->get_value()) && date_obj($value)->is_valid()) {
            return true;
        }
        if (0 == strlen($item->get_err_msg())) {
            $item->err_msg("Please enter a valid date.");
        }
        return false;
    }

    static function validate_file_size(&$item) {
        $value = $item->get_value();
        if (null != $value) {
            $v = $item->get_validate_data();
            if ($value->size <= ($v["size"])) {
                return true;
            }
            $filesizename = array(" Bytes", " KB", " MB");
            $item->err_msg("This file is too large. Please upload a file less than ".
                ($v["size"] ? round($v["size"]/pow(1024, ($i = floor(log($v["size"], 1024)))), 2).$filesizename[$i] : "0 Bytes"));
        }
        return false;
    }
}
