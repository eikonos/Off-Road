<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Redirector Layer
#
# Purpose:
# Redirects if a RedirectException exception is thrown.
#
# Requires routing layer to be loaded first.
#

class redirector extends layer
{
    public static function run() {
        global $request; global $settings;
        try {
            $response = parent::run_next();
        } catch (RedirectException $r) {
            $response = array(307, array("Location: ".$r->getMessage()), "");
        }
        return $response;
    }
}

class RedirectException extends Exception{};

function redirect_to_url() {
    throw new RedirectException(get_url(func_get_args()));
}
