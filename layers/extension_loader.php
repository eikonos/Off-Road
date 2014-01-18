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
