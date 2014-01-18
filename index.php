<?php

# PHP Off Road

define("OR_VERSION", 5);
define("OR_BASE_PATH", dirname(realpath(dirname(__FILE__)))."/");
# the name of the OR and site folders, as well as the settings file name can be customized by defining these before loading this file
if (!defined("OR_CORE_DIRECTORY")) define("OR_CORE_DIRECTORY", "off-road");
if (!defined("OR_SITE_DIRECTORY")) define("OR_SITE_DIRECTORY", OR_BASE_PATH."site/");
if (!defined("OR_SETTINGS_FILE")) define("OR_SETTINGS_FILE", "settings.php");
define("OR_PATH", OR_BASE_PATH.OR_CORE_DIRECTORY."/");
define("LAYER_FOLDER_NAME", "layers");
define("EXTENSION_FOLDER_NAME", "extensions");
define("LIBRARY_FOLDER_NAME", "libraries");

if (!defined("SITE_BASE_URL")) {
    # if this code is in a subfolder of the website, figure out what the subfolder url is so it can be added to the route urls
    $url_slash_pos = strrpos($_SERVER["SCRIPT_NAME"], "/");
    if ($url_slash_pos > 0) {
        # note: always trim the trailing slash so routes can match with or without the slash
        define("SITE_BASE_URL", substr($_SERVER["SCRIPT_NAME"], 1, $url_slash_pos - 1));
    }
    else
        define("SITE_BASE_URL", "");
}


class layer {
    static $current_layer_index = -1;

    # run all layers (should only be called from this file)
    public static function run() {
        global $request; global $settings;
        $response = self::run_next();
        if (iterable($response[1])) {
            foreach ($response[1] as $header) {
                header($header);
            }
        }
        if ($response[2] && strlen($response[2]) > 0) {
            echo $response[2];
        }
    }

    # run the next layer down the chain (called by each layer)
    public static function &run_next() {
        global $request; global $settings;
        self::$current_layer_index++;
        if (self::$current_layer_index < count($settings["layers"])) {
            $next_layer = $settings["layers"][self::$current_layer_index];
            $response = self::run_layer($next_layer);
        } else {
            throw new Exception("run_next() called, but there are no more layers.");
        }
        return $response;
    }

    # in exceptional cases (errors), run the last layer (which should be the template renderer)
    public static function &run_last() {
        global $request; global $settings;
        $last_layer = $settings["layers"][count($settings["layers"]) - 1];
        $response = self::run_layer($last_layer);
        return $response;
    }

    # run a named layer
    public static function &run_layer($layer) {
        global $request; global $settings;
        $layer_file = OR_SITE_DIRECTORY.LAYER_FOLDER_NAME."/{$layer}.php";
        if (file_exists($layer_file)) {
            require_once($layer_file);
        } else {
            $layer_file = OR_PATH.LAYER_FOLDER_NAME."/{$layer}.php";
            if (file_exists($layer_file)) {
                require_once($layer_file);
            }
        }
        $response = $layer::run();
        return $response;
    }
}

# access a value in the settings array. can provide a default for values that do not exist
function get_setting($group, $name, $default = null) {
    global $settings;
    if (null == $group) {
        return (isset($settings[$name]) ? $settings[$name] : $default);
    } else {
        return (isset($settings[$group][$name]) ? $settings[$group][$name] : $default);
    }
}

# set the default value for a setting, if it is not set
function set_setting_default($group, $name, $value) {
    global $settings;
    if (null == $group) {
        if (! isset($settings[$name])) {
            $settings[$name] = $value;
        }
    } else {
        if (! isset($settings[$group][$name])) {
            $settings[$group][$name] = $value;
        }
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

function iterable($var) {
    return is_array($var) || $var instanceof ArrayAccess;
}

# add the domain name to a url
function href($location, $replace = true) {
    static $site_url = null;
    if (0 === strpos($location, "http://") || 0 === strpos($location, "https://") || 0 === strpos($location, "//")) {
        return $replace ? str_replace(" ", "_", $location) : $location;
    } else {
        if (null == $site_url) {
            if (!isset($_SERVER["HTTP_HOST"])) {
                # use server_name if http_host is not set in the request
                $_SERVER["HTTP_HOST"] = $_SERVER["SERVER_NAME"];
            }
            $site_url = (443 == $_SERVER["SERVER_PORT"] ? "https://" : "http://").$_SERVER["HTTP_HOST"]."/";
            if (strlen(SITE_BASE_URL) > 0) {
                $site_url .= SITE_BASE_URL . "/";   # trailing slash is always trimmed from base url
            }
        }
        while (strlen($location) > 0 && "/" == $location[0]) {
            $location = substr($location, 1);
        }
        return $site_url.($replace ? str_replace(" ", "_", $location) : $location);
    }
}

# add a route
function add_route($name, $path, $controller, $function, $parameters) {
    global $settings;
    if ($name) {
        if (isset($settings["routes"][$name])) {
            throw new Exception("Creating another route with name '$name'.");
        }
        $settings["routes"][$name] = array($path, $controller, $function, $parameters);
    } else {
        $settings["routes"][] = array($path, $controller, $function, $parameters);
    }
}

# load the settings file and run all layers
global $request; global $settings;
$request = &$_SERVER;
require_once(OR_SITE_DIRECTORY.OR_SETTINGS_FILE);    # load site settings
if (isset($settings)) {
    $request['route']['controller'] = null;
    $request['route']['function'] = null;
    $request['route']['parameters'] = array();
    layer::run();
} else {
    throw new Exception("Error: settings file is missing or invalid.");
}
