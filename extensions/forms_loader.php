<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# HTML Forms Helper extension
#
# Purpose:
# Loads the HTML forms extension and creates a form object based on form/file name.
#
# Does not require the forms extension to be loaded.
#
# Usage: $form_edit = load_forms("edit", $optional, $parameters); // form file is ./site/forms/edit.php and form object is named 'edit'
#

function load_forms() {
    load_extension("forms");
    if (func_num_args() > 0) {
        $form_name = func_get_arg(0);
        $form_file = OR_SITE_DIRECTORY."/forms/$form_name.php";
        if (!file_exists($form_file)) {
            throw new Exception("Form file '$form_file' does not exist.");
        }
        require_once($form_file);
        for ($i = 1; $i < func_num_args(); $i++) {
            ${"arg".$i} = func_get_arg($i);
        }
        switch (func_num_args()) {
            case 1: return new $form_name(); break;
            case 2: return new $form_name($arg1); break;
            case 3: return new $form_name($arg1, $arg2); break;
            case 4: return new $form_name($arg1, $arg2, $arg3); break;
            case 5: return new $form_name($arg1, $arg2, $arg3, $arg4); break;
            default: throw new Exception("Too many form parameters; add new case."); break;
        }
    }
}
