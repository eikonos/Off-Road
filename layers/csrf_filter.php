<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# CSRF Filter Layer
#
# Purpose:
# Raise an error if csrf tag submitted by user does not match stored csrf in session for POST and PUT requests.
#
# A controllor 'error' with function 'csrf' should be provided by the site.
#

class csrf_filter extends layer {
    public static function run() {
        global $request; global $settings;
        $csrf = get_csrf();
        # ensure the correct csrf tag is set if this is a post request
        if ('POST' == $request['REQUEST_METHOD'] || 'PUT' == $request['REQUEST_METHOD']) {
            $post_csrf = isset($_POST['csrf']) ? $_POST['csrf'] : '-missing-';
            if ($csrf != $post_csrf) {
                $request['route']['name']       = 'error_csrf';
                $request['route']['controller'] = 'error';
                $request['route']['function']   = 'csrf';
                $request['route']['parameters'] = array_merge(array('url'=>$_SERVER["REQUEST_URI"]), $request['route']['parameters']);
                $request['route']['parameters']['debug'] = get_setting(null, "debug", false);

                if ($request['route']['parameters']['debug']) {
                    error_log("Expected CSRF token '$csrf', received '$post_csrf' instead.");
                }

                # allow over-riding and using a template controller
                try {
                    # let the site's error.csrf controller try to render a page
                    $response = parent::run_last();
                } catch (Exception $e) {
                    error_log("ERROR: ".$e->getTraceAsString());
                    $body = "<html><head><title>Server Error</title></head><body style=\"background-color:#ccc; padding:1em;\">".
                        "<div style=\"border:1px solid red; padding:1em; background-color:#fff;\">".
                        "<h2>Server Error</h2>";
                    # show a more descriptive error in debug mode
                    if ($request['route']['parameters']['debug']) {
                        $body .= "<p>CSRF tag in POST is incorrect.</p>";
                    } else {
                        $body .= "<p>Your session has expired.</p>";
                    }
                    $body .= "</div></body></html>";
                    $response = array(200, array(), $body);
                }
            } else {
                $csrf = null;    # cause csrf token to be regenerated to prevent double-submits
            }
        }
        if (null == $csrf) {
            # if the csrf session token was not yet set, or if it was reset after a post request, generate it now
            $_SESSION["csrf"] = substr(base64_encode(mcrypt_create_iv(20,MCRYPT_DEV_URANDOM)),0,20);
        }
        $request['route']['parameters']['csrf'] = get_csrf();
        return parent::run_next();
    }
}

function get_csrf() {
    return (isset($_SESSION["csrf"]) ? $_SESSION["csrf"] : null);
}

if (!defined("MCRYPT_DEV_URANDOM")) {
    define("MCRYPT_DEV_URANDOM", 1);
}
if (!function_exists("mcrypt_create_iv")) {
    function mcrypt_create_iv($size, $source = 1) {
        return substr(bin2hex(file_get_contents('/dev/urandom', false, null, 0, 10)), 0, $size);
    }
}
