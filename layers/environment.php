<?php

#
# Environment Variables Layer
#
# Purpose:
# Load settings from environment variables.
#
# The environment variables settings look something like this:
#
# $settings["environment"] = array(
#     "database_user"=>array("database", "username", "optional"=>false),
#     "database_pass"=>array("database", "password", "optional"=>true)      # optional is an optional parameter which defaults to false
# );
#

class environment extends layer
{
    public static function run() {
        global $request; global $settings;
        if (isset($settings["environment"])) {
            $missing_environment_variables = array();
            $required_environment_variables = get_setting(null, "environment");
            # loop through and load all the environment variables
            foreach ($required_environment_variables as $variable_name => $variable_options) {
                $environment_value = getenv($variable_name);
                if (strlen($environment_value) > 0) {
                    # load the value
                    if (isset($variable_options[0])) {
                        if (isset($variable_options[1])) {
                            $settings[$variable_options[0]][$variable_options[1]] = $environment_value;
                        } else {
                            $settings[$variable_options[0]] = $environment_value;
                        }
                    }
                } else {
                    # no value was found in the environment, so check if it was optional
                    if (!isset($variable_options["optional"]) or !$variable_options["optional"]) {
                        $missing_environment_variables[] = $variable_name;
                    }
                }
            }
            # report if any required environment variables were missing
            if (count($missing_environment_variables) > 0) {
                try {
                    $err_msg = "Error: missing environment variable".(count($missing_environment_variables) > 1 ? "s" : "").
                        " ".join(", ", $missing_environment_variables).
                        " while viewing '{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}'.";
                    error_log($err_msg);

                    # render error template
                    $request['route']['name']       = 'error_500';
                    $request['route']['controller'] = 'error';
                    $request['route']['function']   = 500;
                    $request['route']['parameters'] = array_merge(array('url'=>$_SERVER["REQUEST_URI"]), $request['route']['parameters']);
                    $request['route']['parameters']['debug'] = get_setting(null, "debug", false);
                    $request['route']['parameters']['error_info'] = $err_msg;

                    # the error handler page itself could throw errors, so catch exceptions
                    $response = parent::run_last(); # the last layer should be the renderer
                    $body = $response[2];
                } catch (Exception $e) {
                    $body  = "<html><head><title>Required Environment Varables Missing</title></head><body>";
                    $body .= "<h1>Required Environment Varables Missing</h1>";
                    $body .= "<p>The variable(s) are missing. Set them in the virtual host settings.</p>";
                    $body .= "<ul>";
                    foreach ($missing_environment_variables as $k => $variable_name) {
                        $body .= "<li>$variable_name</li>\n";
                    }
                    $body .= "</ul>";
                    $body .= "</body></html>";
                }
                return array(500, array("HTTP/1.1 500 Internal Server Error"), $body);
            }
        }
        return parent::run_next();
    }
}
