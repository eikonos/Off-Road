<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Extension Loader Layer
#
# Purpose:
# Loads extension code files specified in global settings.
#

class extension_loader extends layer {
    public static function run() {
        global $request; global $settings;
        foreach ($settings["extensions"] as $extension)
            load_extension($extension);
        return parent::run_next();
    }
}

# loads an extension file. checking first in the site folder, then in the off-road folder
function load_extension() {
    $args = func_get_args();
    foreach ($args as $extension) {
        $extension_filename = OR_SITE_DIRECTORY.EXTENSION_FOLDER_NAME."/$extension.php";
        if (file_exists($extension_filename)) {
            require_once($extension_filename);
        } else {
            $extension_filename = OR_PATH.EXTENSION_FOLDER_NAME."/$extension.php";
            if (file_exists($extension_filename)) {
                require_once($extension_filename);
            } else {
                throw new Exception("Error: requested extension '$extension' does not exist.");
            }
        }
    }
}
