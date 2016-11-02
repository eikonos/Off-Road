<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Error Layer
#
# Purpose:
# Displays an error page (with details if debug is enabled) if an exception is not caught by another exception handler.
#
# A controllor 'error' with function '500' should be provided by the site.
#

class or_error extends layer {
    public static function run() {
        global $request; global $settings;
        set_error_handler('exceptions_error_handler', E_ALL);    # raise errors as exceptions
        try {
            $response = parent::run_next();
        } catch (Exception $e) {
            try {
                error_log("Error: \"{$e->getMessage()}\" in {$e->getFile()} on line {$e->getLine()} viewing ".
                    "'{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}'");

                # render error template
                $request['route']['name']       = 'error_500';
                $request['route']['controller'] = 'error';
                $request['route']['function']   = 500;
                $request['route']['parameters'] = array_merge(array('url'=>$_SERVER["REQUEST_URI"]), $request['route']['parameters']);
                $request['route']['parameters']['debug'] = get_setting(null, "debug", false);
                $request['route']['parameters']['error_info'] = error_info_for_exception($e);

                # the error handler page itself could throw errors, so catch exceptions
                $response = parent::run_last();  # the last layer should be the renderer
                $response[0] = 500;
                array_unshift($response[1], "HTTP/1.1 500 Internal Server Error");
            } catch (Exception $e) {
                error_log("ERROR: ".$e->getTraceAsString());
                $body = "<html><head><title>Server Error</title></head><body style=\"background-color:#ccc; padding:1em;\">".
                    "<div style=\"border:1px solid red; padding:1em; background-color:#fff;\">".
                    "<h2>Server Error</h2>";
                if ($request['route']['parameters']['debug']) {
                    $body .= "<p>An error occurred while rendering the page. If this is not a testing server, set ".
                        "<code>'debug'=>false</code> in settings.php.</p>";
                    if (isset($request['route']['parameters']['error_info']))
                        $body .= $request['route']['parameters']['error_info'];
                    else
                        $body .= "<p>No error information available.</p>";
                } else {
                    $body .= "500 - Internal server error.";
                }
                $body .= "</div></body></html>";
                $response = array(500, array("HTTP/1.1 500 Internal Server Error"), $body);
            }
        }
        return $response;
    }
}

# this converts PHP errors into exceptions so they can be caught and handled
function exceptions_error_handler($errno , $message, $filepath, $errline, $errcontext) {
    throw new ErrorException($message, 0, $errno, $filepath, $errline);
}

# create a detailed stack trace
function error_info_for_exception($exception) {
    # css classes:
    #
    # div.error_page
    # pre.error_page
    # code.error_page
    # code.error_page > span.exceptionline
    # h2.error_page
    # ol.error_page
    # li.error_page
    #
    global $request; global $settings;
    $traces = $exception->getTrace();
    $error_url = (443 == $_SERVER["SERVER_PORT"] ? "https://" : "http://")."{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}";
    $message = "<div class=\"error_page\"><p>The following error occured viewing <a href=\"$error_url\">{$_SERVER['SERVER_NAME']}".
        "{$_SERVER['REQUEST_URI']}</a> at ".date("Y-m-d H:i:s", time())." for client {$_SERVER['REMOTE_ADDR']}.</p>".
        "<p>\"<b>{$exception->getMessage()}</b>\"<br/>".
        "in <b>{$exception->getFile()} : {$exception->getLine()}</b></p></div>\n";

    # attempt to display the relevant line in the file where the error occurred
    if (file_exists($exception->getFile()) && ($file_contents = file_get_contents($exception->getFile()))) {
        $lines = explode("\n", str_replace("\r\n", "\n", $file_contents));
        $linenum = $exception->getLine() - 1;
        $start = max(0, $linenum - 7);
        $end = $start + 15;
        $line_count = count($lines);
        if ($end > $line_count) {
            # if error at the end of the file, shift the window up
            $diff = $end - $line_count;
            $start -= $diff;
            $end -= $diff;
        }
        $message .= "<pre class=\"error_page\"><code class=\"error_page\">";
        for ($i = $start; $i < $end; $i++) {
            if ($linenum == $i) {
                $message .= "<span class=\"exceptionline\">";
            }
            $message .= ($i+1)."  ".htmlentities($lines[$i]);
            if ($linenum == $i) {
                $message .= "</span>";
            }
            $message .= "<br />";
        }
        $message .= "</code></pre><br/>";
    }

    $message .= "<h2 class=\"error_page\">Trace</h2>\n".
        "<ol class=\"error_page\">";
    foreach ($traces as $trace) {
        $message .= "<li class=\"error_page\">";
        if (isset($trace['file'])) {
            $message .= $trace['file'];
            if (isset($trace['line'])) {
                $message .= " : ".$trace['line'];
            }
            $message .= "<br />";
        } else {
            $message .= "(no file)<br/>";
        }
        if (isset($trace['class'])) {
            $message .= $trace['class'];
            if (isset($trace['type'])) {
                $message .= $trace['type'];
            }
        } else {
            $message .= "function ";
        }
        if (isset($trace['function'])) {
            $message .= $trace['function']."()";
        }
        if (isset($trace['args']) && count($trace['args']) > 0) {
            $message.= "<br/>Parameters:";
            $message.= "<pre class=\"error_page\"><code class=\"error_page\">";
            $message.= htmlentities(var_to_string($trace['args'], 2));
            $message.= "</code></pre>";
        } else {
            $message.= "<br/>No parameters.<br/>";
        }
        $message .= "</li>";
    }
    $message .= "</ol>";

    $message .= "<h2 class=\"error_page\">State</h2>";
    $message .= "<pre class=\"error_page\"><code class=\"error_page\">";
    $message .= "Request => ".htmlentities(var_to_string($request, 2));
    $message .= "\n\nSettings => ".htmlentities(var_to_string($settings, 2));
    $message .= "</code></pre>";

    # provide an option to scrub specific values from the report
    $message = scrub_secrets($message);

    if (get_setting(null, "debug", false)) {
        return $message;
    } else {
        $error_email_addresses = get_setting(null, "error_email_addresses", null);
        if (is_string($error_email_addresses))  # support error_email_addresses as an array or a string of addresses separated by commas
            $error_email_addresses = explode(",", $error_email_addresses);
        if (iterable($error_email_addresses)) {
            foreach ($error_email_addresses as $email) {
                if (!mail($email, "{$_SERVER['SERVER_NAME']} Website Error", $message, "Content-Type: text/html; charset=us-ascii\r\n")) {
                    error_log("Error log email could not be sent to '$email'.");
                }
            }
        } else {
            error_log("No email addresses set for 'error_email_addresses' in settings.php.");
        }
    }
    return null;
}

# similar to print_r(), but recursion is limited
function var_to_string($var, $depth = 5) {
    static $indent = 1;
    $return = "";
    try {
        switch (gettype($var)) {
            case 'string':
                $string = $var;
                if (strlen($var) > 150) {
                    $string = substr($var, 0, 150)."{...}";
                }
                $return .= "\"".(str_replace(array("\r", "\n", "\t"), array("\\r", "\\n", "\\t"), $string))."\"";
                break;
            case 'object':
                $depth--;
                if ($depth < 0) {
                    $return .= "[".get_class($var)." {variable depth exceeded}]";
                } else {
                    $vars = get_object_attributes($var);
                    $methods = get_object_functions($var);
                    if (0 == count($vars) && 0 == count($methods)) {
                        $return .= "[".get_class($var)." {empty}]";
                    } else {
                        $indent++;
                        $return .= get_class($var)."\n";
                        for ($i = 2; $i < $indent; $i++) {
                            $return .= "    ";
                        }
                        $return .= "[\n";
                        foreach ($methods as $vark => $varv) {
                            for ($i = 1; $i < $indent; $i++) {
                                $return .= "    ";
                            }
                            $return .= "function {$vark}($varv)\n";
                        }
                        foreach ($vars as $vark => $varv) {
                            for ($i = 1; $i < $indent; $i++) {
                                $return .= "    ";
                            }
                            $return .= "$vark => ".var_to_string($varv, $depth).",\n";
                        }
                        for ($i = 2; $i < $indent; $i++) {
                            $return .= "    ";
                        }
                        $indent--;
                        $return .= "]";
                    }
                }
            break;
            case 'boolean':    $return .= (true === $var) ? "True" : "False"; break;
            case 'NULL':    $return .= "NULL"; break;
            case 'array':
                $depth--;
                if ($depth < 0) {
                    $return .= "Array({variable depth exceeded})";
                } else {
                    if (0 == count($var)) {
                        $return .= "Array()";
                    } else {
                        $indent++;
                        $return .= "Array\n";
                        for ($i = 2; $i < $indent; $i++) {
                            $return .= "    ";
                        }
                        $return .= "(\n";
                        foreach ($var as $vark => $varv) {
                            # when dumping the $GLOBALS array, avoid recursing into it.
                            # note that this also excludes ANY array element named GLOBALS.
                            if (0 !== strcmp("GLOBALS", $vark)) {
                                for ($i = 1; $i < $indent; $i++) {
                                    $return .= "    ";
                                }
                                $return .= "$vark => ".var_to_string($varv, $depth).",\n";
                            }
                        }
                        for ($i = 2; $i < $indent; $i++) {
                            $return .= "    ";
                        }
                        $indent--;
                        $return .= ")";
                    }
                }
                break;
            case 'resource':$return .= "[".get_resource_type($var)." resource]";break;
            default:        $return .= $var; break;
        }
    } catch (Exception $e) {
        if (is_object($var)) {
            error_log("Object threw an exception while being converted to string: ".get_class($var));
            error_log($e->getMessage());
            $return .= "[".get_class($var)." object]";
        } else {
            $result = print_r($var, true);
            error_log("Variable threw an exception while being converted to string: ".$result);
            $return .= $result;
        }
    }

    return scrub_secrets($return);
}

function get_object_attributes($object)  # sorts by attribute name; gets private attributes
{
    $attributes = get_object_vars($object);
    if (!is_a($object, "stdClass")) {
        $reflection = new ReflectionClass($object);
        $properties = $reflection->getProperties(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PUBLIC
            | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $attributes[$property->getName()] = $property->getValue($object);
        }
    }
    ksort($attributes);
    return $attributes;
}

function &get_object_functions($object)  # like get_class_methods but retuns private methods and also parameter names
{
    $functions = array();
    $reflection = new ReflectionClass($object);
    $methods = $reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC
        | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_ABSTRACT | ReflectionMethod::IS_FINAL);
    foreach ($methods as $method) {
        $method->setAccessible(true);
        $parameters = $method->getParameters();
        $x = array();
        foreach ($parameters as $parameter) {
            $x[] = $parameter->getName();
        }
        $functions[$method->getName()] = implode(", ", $x);
    }
    ksort($functions);
    return $functions;
}

function scrub_secrets($message) {
    $secrets = get_setting(null, "secrets");
    if (is_array($secrets)) {
        foreach ($secrets as $secret_info) {
            if (is_array($secret_info) and 2 == count($secret_info)) {
                $secret_info = get_setting($secret_info[0], $secret_info[1]);
            }
            $message = str_replace($secret_info, "{secret}", $message);
        }
    } else {
        # since environment variables are probably secrets, scrub them from the report
        $required_environment_variables = get_setting(null, "environment");
        if ($required_environment_variables) {
            # loop through and load all the environment variables
            foreach ($required_environment_variables as $variable_name => $variable_options) {
                $environment_value = getenv($variable_name);
                # skip replacing empty and numeric values
                if (strlen($environment_value) > 0 and !is_numeric($environment_value)) {
                    # replace the environment value with the name of the variable
                    $message = str_replace($environment_value, "{".$variable_name."}", $message);
                }
            }
        }
    }
    return $message;
}

# fairly similar to error_log(print_r($var, true)), except that recursing is limited and log_var is easier to type
function log_var($var, $depth = 5) {
    error_log(var_to_string($var, $depth));
}
