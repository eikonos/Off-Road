<?php
if (!defined("OR_VERSION")) {header("Location: /");exit(0);}

#
# Twig Template Rendering Extension
#
# Uses the Twig Template Engine: http://twig.sensiolabs.org/doc/templates.html
# Note: Twig is a third party library which *must be downloaded separately*.
#
# Purpose:
# Returns rendered template using the Twig template system.
#

function render_twig_template($template_file, $template_vars) {
    # load the Twig library
    $twig_file = OR_SITE_DIRECTORY.LIBRARY_FOLDER_NAME."/Twig/lib/Twig/Autoloader.php";

    if (!file_exists($twig_file)) {
        error_log("Error: Twig template library is not installed when calling render_twig_template(). ".
            "Twig file should be located at '$twig_file'.");
        return false;
    } else {
        require_once($twig_file);
        Twig_Autoloader::register();

        #
        # helper classes that extend Twig can only be declared after including Twig, but we want to ensure Twig is loaded
        #   and provide a helpful error message
        #

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
        }

        if (!class_exists("Debug_Node", false)) {
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

        #
        # prepare Twig, then render the template
        #

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
        $twig->addFilter('currency', new Twig_Filter_Function('or_currency'));
        $twig->addFilter('truncate', new Twig_Filter_Function('or_truncate'));
        $twig->addFilter('numberformat', new Twig_Filter_Function('number_format'));

        # load and render the template
        $template = $twig->loadTemplate($template_file.get_setting("twig", "template_extension", ".html"));
        return $template->render($template_vars);
    }
}

function or_currency($value, $show_decimals = true) {
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

function or_truncate($text, $length = 20) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length)."...";
    }
    return $text;
}
