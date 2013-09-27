
# Layers

Layers are the heart of the Off Road system and what makes it so flexible. The main script loads the [settings file](settings). Then the first layer is loaded, which is expected to call the next layer, and so on, until the page is rendered. The return value is then passed back down each layer and output by the main index file.

    $settings["layers"] = array("error", "extension_loader", "regex_routes", "csrf_filter", "redirector", "twig_renderer");

Each layer can modify the global *$request* and *$settings* variables before they are accessed by the next layer in the sequence, and they can also modify the resulting page body that is passed back down the chain.

The layer order is important. For example, the error layer should be loaded first because it cannot catch errors until it is loaded.

Off Road includes several layers, and you can [make your own](new_layers).

## CSRF Filter Layer
For post requests, ensure the submitted csrf token matches the stored session csrf token. The csrf token is added to the route parameters, so it is available in templates.

Site renderer should provide a handler for controller 'error' with function 'csrf'.

Functions:

* get_csrf()

    Access the stored session csrf token.

## Error Layer
Catch exceptions (and PHP errors), send an email to the developer, and display exception and trace log on the page when in debug mode (set 'debug' to true in [Settings](settings) file).

The template layer should provide a handler for controller 'error' with function '500'.

    $settings["debug"] = false;                      # in debug mode, error information is displayed on the website
    $settings["error_email_addresses"] = array();    # send email error reports when not in debug mode

Functions:

* var_to_string($variable, $depth = 5)

    Display information about a variable, and contained variables up to $depth levels.

* log_var($var, $depth = 5)

    Log information about a variable using error_log().

<a name="extension_loader"></a>
## Extension Loader Layer
Load [Extensions](extensions) files. Searches first in the /site/extensions folder, then in /off_road/extensions.

    # extension files are loaded at startup
    $settings["extensions"] = array("psql", "model_autoloader", "dbsession", "rowobj");

## Redirector Layer
Enable redirecting to another url by throwing RedirectException with new url, or by calling redirect_to_url() with the parameters to look up the new url. Requires regex_routes layer to be loaded already, or an alternate layer or extension that provides a *get_url()* function to look up URLs by name.

Functions:

* redirect_to_url($url_name, $etc, ...)

    Stops running through the layers and returns an HTTP 307 response back down the chain.


## Regex Routes Layer
Set routing variables by matching the requested url with regular expressions.

Site renderer should provide a handler for controller 'error' with function '404'.

Functions:

* get_url()

    Looks up a url from the *$routes* array by name. If the url includes parameters, pass additional parameters to fill them in the url.  


Example:  

    get_url('blog_entry', $blog->id);

## Twig Renderer Layer
Loads the controller, then renders the template using [Twig^](http://twig.sensiolabs.org/documentation).

    $settings["twig"] = array(
        # if true, template file will be site/templates/<controller>/<function>.php
        # if false, template file will be site/templates/<controller>_<function>.php
        "use_folders"=>true,
        # the other settings are passed to Twig
        # http://twig.sensiolabs.org/doc/api.html#environment-options
        "template_folder"=>OR_SITE_DIRECTORY."templates/",
        "autoescape"=>true,
        "cache_folder"=>OR_SITE_DIRECTORY."TEMP/twig_cache/",
        "auto_reload"=>false,
        "debug"=>false
    );
