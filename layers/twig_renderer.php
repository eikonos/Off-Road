<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Twig Template Rendering Layer
#
# Uses the Twig Template Engine: http://twig.sensiolabs.org/doc/templates.html
# Note: Twig is a third party library which *must be downloaded separately*.
#
# Purpose:
# Loads controller file and returns rendered template using the Twig template system.
#
# Requires routing variables to be set.
#

class twig_renderer extends layer {
    public static function run() {
        global $request; global $settings;
        if (!array_key_exists('route', $request)
            || !array_key_exists('controller', $request['route'])
            || !array_key_exists('function', $request['route'])
            || !array_key_exists('parameters', $request['route'])) {
            throw new Exception("Template rendering layer requires routing variable to be set in \$request.");
        }
        $controller_file = OR_SITE_DIRECTORY."controllers/{$request['route']['controller']}.php";
        if (file_exists($controller_file)) {
            $template_file = "{$request['route']['controller']}".
                (get_setting("twig", "use_folders", true) ? "/" : "_")."{$request['route']['function']}";
            extract($request['route']['parameters'], EXTR_REFS);
            $route = (object)$request['route'];
            $headers = array("Content-Type: text/html;charset=utf-8");

            # the controller can declare variables and modify the headers and template_file variables
            require_once($controller_file);
            $template_vars = get_defined_vars();

            # some variables are not needed inside templates
            unset($template_vars["controller_file"]);
            unset($template_vars["headers"]);
            unset($template_vars["loader"]);
            unset($template_vars["template_file"]);
            unset($template_vars["twig"]);
            unset($template_vars["twig_options"]);

            # load and render the template
            load_extension("twig_renderer");
            $body = render_twig_template($template_file, $template_vars);
            if (false === $body) {
                # if the render function returns false, then Twig is not installed
                $body = "<html><head><title>Server Error</title></head><body style=\"background-color:#ccc; padding:1em;\">".
                "<div style=\"border:1px solid red; padding:1em; background-color:#fff;\">".
                "<h1>Server Error</h1>".
                "<p>The <a target=\"_blank\" href=\"http://twig.sensiolabs.org/doc/intro.html#installation\">".
                "Twig Template Engine</a> was not found. See server error log for details.</p>".
                "</body></html>";
            }

            # remove these variables before merging all the currently-defined variables back into the route parameters
            unset($template_vars["route"]);
            unset($template_vars["request"]);
            unset($template_vars["settings"]);

            # merge any variables created in the controller so they are available when the page content is passed back down the layers
            $request['route']['parameters'] = array_merge($template_vars, $request['route']['parameters']);

            # return the rendered template
            return array(200, $headers, $body);
        }
        throw new Exception("Controller file $controller_file is missing.");
    }
}
