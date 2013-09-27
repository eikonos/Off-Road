<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Database Model Autoloader extension
#
# Purpose:
# Automatically load database model files when model object is used.
#  ie: For a model 'user', calling user::some_function() will load ./site/models/user.php
#
# Requires (psql or mysqli) and rowobj extensions.
#

function model_autoloader($class) {
    $filename = OR_SITE_DIRECTORY."/models/{$class}.php";
    if (file_exists($filename))
        require_once($filename);
}
spl_autoload_register("model_autoloader");
