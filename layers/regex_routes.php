<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Regex Routing Layer
#
# Purpose:
# Sets routing information based on url. Provides get_url() for other code to generate urls for named routes.
#
# A controllor 'error' with function '404' should be provided by the site.
#

class regex_routes extends layer {
    public static function run() {
        global $request; global $settings;
        $request["route"]["name"] = "error_404";
        $request["route"]["controller"] = "error";
        $request["route"]["function"] = 404;
        $request["route"]["parameters"] = array_merge(array("url"=>$_SERVER["REQUEST_URI"]), $request["route"]["parameters"]);

        if (iterable($settings["routes"])) {
            $matches = array();
            # remove leading and trailing / characters from uri so we always match without them
            $uri = $_SERVER["REQUEST_URI"];
            if (strlen(SITE_BASE_URL) > 0) {
                if ("/" == $uri[0])
                    $uri = substr($uri, 1);
                $uri = substr($uri, strlen(SITE_BASE_URL));
            }
            if ("/" == $uri[0])
                $uri = substr($uri, 1);
            $len = strlen($uri);
            while ($len > 1 && "/" == $uri[$len - 1]) {
                $len -= 1;    # the last character is a /, so drop it
            }
            $uri = substr($uri, 0, $len);
            foreach ($settings["routes"] as $route_name => $route) {
                $count = preg_match("|{$route[0]}|", $uri, $matches);
                if ($count > 0) {
                    $request["route"]["name"] = $route_name;
                    $request["route"]["controller"] = $route[1];
                    $request["route"]["function"] = $route[2];
                    # in addition to the found named groups, matches contains the whole matched string plus
                    #    matches with numeric indexes, so remove the extra variables by unsetting numeric indexes.
                    $index = 0;
                    while (isset($matches[$index])) {
                        unset($matches[$index]);
                        $index++;
                    }
                    $matches = array_map("urldecode", $matches);
                    if (iterable($route[3])) {
                        $matches = array_merge($matches, $route[3]);
                    }
                    $request["route"]["parameters"] = array_merge($matches, $request['route']['parameters']);
                }
            }
        } else {
            throw new Exception("To use url_routes, add a \$settings[\"routes\"] array variable to settings.php in your site's directory.");
        }
        return parent::run_next();
    }
}

# build a URL for named route and optional parameters
function get_url() {
    $args = func_get_args();
    if (iterable($args[0])) {
        # a little hack that allows calling redirect_to_url() which then calls this function with a variable number of arguments
        $args = $args[0];
    }
    $url_name = array_shift($args);
    $url_name = str_replace("'", "", $url_name);    # if a template tag uses 'quotes' around url name, strip them now

    global $settings;
    if (!isset($settings["routes"][$url_name])) {
        throw new Exception("Named url '{$url_name}' not found when calling get_url().");
    }
    $target_route = &$settings["routes"][$url_name];

    # if we haven't parsed this url for locations of replacement variables, do that now
    if (!isset($target_route['get_url'])) {
        $url = ltrim(rtrim($target_route[0], "\$"), "^");

        $open = strpos($url, "(");
        if (false === $open) {
            $target_route["get_url"] = array($url);
        } else {
            $target_route["get_url"] = array();
            while (strlen($url) > 0) {
                $open = strpos($url, "(");
                if (false === $open) {
                    $target_route["get_url"][] = $url;
                    $url = "";
                } else {
                    if ($open > 0) {
                        $target_route["get_url"][] = substr($url, 0, $open);
                    }
                    $target_route["get_url"][] = true;    # a variable goes here
                    $close = strpos($url, ")");
                    $url = substr($url, $close + 1);
                }
            }
        }
    }

    # build url
    $url = "/";
    foreach ($target_route["get_url"] as $part) {
        if (true === $part) {
            if (count($args) == 0) {
                throw new Exception("Not enough variable arguments supplied for get_url('$url_name').");
            }
            $url .= urlencode(array_shift($args));
        } else {
            $url .= $part;
        }
    }
    if (count($args) > 0) {
        throw new Exception("Too many variable arguments supplied for get_url('$url_name').");
    }
    return href($url);
}
