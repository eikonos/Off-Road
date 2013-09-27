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

# load the Twig library
global $twig_file;
$twig_file = OR_SITE_DIRECTORY.LIBRARY_FOLDER_NAME."/Twig/lib/Twig/Autoloader.php";

if (!file_exists($twig_file)) {
    class twig_renderer extends layer {
        public static function run() {
            global $twig_file;
            $headers = array("Content-Type: text/html;charset=utf-8");
            return array(200, $headers, "<html><head><title>Server Error</title></head><body style=\"background-color:#ccc; padding:1em;\">".
                "<div style=\"border:1px solid red; padding:1em; background-color:#fff;\">".
                "<h1>Server Error</h1>".
                "<p>The <a target=\"_blank\" href=\"http://twig.sensiolabs.org/doc/intro.html#installation\">".
                "Twig Template Engine</a> was not found at {$twig_file}.</p>".
                "</body></html>");
        }
    }
} else {
    require_once($twig_file);
    Twig_Autoloader::register();

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
                $loader = new Twig_Loader_Filesystem(get_setting("twig", "template_folder", OR_SITE_DIRECTORY."templates/"));
                # load the twig template environment
                $twig = new Twig_Environment($loader, array(
                    "cache" => get_setting("twig", "cache_folder", OR_SITE_DIRECTORY."TEMP/cache_twig"),
                    "debug"=>get_setting("twig", "debug", true),
                    "autoescape"=>get_setting("twig", "autoescape", false),
                    "auto_reload"=>get_setting("twig", "auto_reload", false),
                ));
                $twig->addTokenParser(new Url_TokenParser());
                if (class_exists("Debug_TokenParser", false)) {
                    $twig->addTokenParser(new Debug_TokenParser());
                }
                $twig->addFilter('currency', new Twig_Filter_Function('currency'));
                $twig->addFilter('truncate', new Twig_Filter_Function('truncate'));
                $twig->addFilter('numberformat', new Twig_Filter_Function('number_format'));
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
                $template = $twig->loadTemplate($template_file.get_setting("twig", "template_extension", ".html"));
                $body = $template->render($template_vars);
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

    # render might throw an exception, then be called again by the error layer, so only declare the url_tag clas the first time
    if (!class_exists("Url_TokenParser", false)) {
        # this enables calling the get_url() routing function within templates so URLs are not hard-coded
        # usage: {% url 'route_name', optional, parameters %}
        class Url_TokenParser extends Twig_TokenParser
        {
            public function parse(Twig_Token $token) {
                $lineno = $token->getLine();
                $params = $this->parser->getExpressionParser()->parseMultiTargetExpression();
                $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
                return new Url_Node(array('params'=>$params), array(), $lineno, $this->getTag());
            }
            public function getTag() {return 'url';}
        }
        class Url_Node extends Twig_Node
        {
            public function compile(Twig_Compiler $compiler) {
                $params = $this->getNode('params')->getIterator();
                $compiler->write('echo get_url(');
                foreach ($params as $i => $row) {
                    $compiler->subcompile($row);
                    if (($params->count() - 1) !== $i) {
                        $compiler->raw(', ');
                    }
                }
                $compiler->raw(");\n");
            }
        }
    }

    if (!class_exists("Debug_TokenParser", false) && function_exists("var_to_string")) {
        # display details for a particular variable within a template. much better than error_log()
        # usage: {% debug %} -or- {% debug variable_name %}
        class Debug_TokenParser extends Twig_TokenParser
        {
            public function parse(Twig_Token $token) {
                $lineno = $token->getLine();
                if ($this->parser->getStream()->test(Twig_Token::BLOCK_END_TYPE)) {
                    $params = new Twig_Node();  # empty node
                } else {
                    $params = $this->parser->getExpressionParser()->parseMultiTargetExpression();
                }
                $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
                return new Debug_Node(array('params'=>$params), array(), $lineno, $this->getTag());
            }
            public function getTag() {return 'debug';}
        }
        class Debug_Node extends Twig_Node
        {
            public function compile(Twig_Compiler $compiler) {
                $params = $this->getNode('params')->getIterator();
                $compiler->write("echo \"<pre class=\\\"twig_debug\\\"><code class=\\\"twig_debug\\\">\".htmlentities(var_to_string(");
                if (count($params) == 0) {
                    $compiler->raw("\$context, 1");
                } else {
                    foreach ($params as $i => $row) {
                        $compiler->subcompile($row);
                        if (($params->count() - 1) !== $i) {
                            $compiler->raw(', ');
                        }
                    }
                }
                $compiler->raw(")).\"</code></pre>\";\n");
            }
        }
    }

    function currency($value, $show_decimals = true) {
        if (is_numeric($value)) {
            if ($value < 1 && $value > -1 && 0 != $value) {
                $money = ($value * 100) . '&cent;';
            } else {
                $money = money_format(($show_decimals || (intval($value) != $value) ? "%.2n" : "%.0n"), $value);
            }
        } else {
            $money = $value;
        }
        return $money;
    }

    function truncate($text, $length = 20) {
        if (strlen($text) > $length) {
            return substr($text, 0, $length)."...";
        }
        return $text;
    }
}
