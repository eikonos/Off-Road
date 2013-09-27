
# Settings

The settings file is a normal PHP file which must contain an array named *$settings*.

## Required Variables

The *$settings* array must contain a *layers* array to specify the order in which [Layers](layers) are loaded. All other settings are optional, depending which [layers](layers) and [extensions](extensions) are loaded.

    $settings["layers"] = array("error", "extension_loader", "regex_routes", "csrf_filter", "redirector", "twig_renderer");

## Custom Settings

You are free to add arbitrary values to *$settings*.

    $settings["basket"] = array("fruit"=>"apple");

To get the value of your setting, you can use the global variable, or the *get_setting()* helper function, which takes about the same amount of code, however the *get_setting()* function **returns a default value** if your setting variable does not exist.

    global $settings;
    $fruit = $settings["basket"]["fruit"];
    # or
    $fruit = get_setting("basket", "fruit", "orange");

## Security

If you consider the name and location of the settings file to be a security risk, it can be moved by editing the defines at the top of the Off Road index.php file. The Off Road sources can also be moved into another folder.

    define("OR_VERSION", 5);
    define("OR_BASE_PATH", realpath(dirname(__FILE__))."/");
    define("OR_PATH", OR_BASE_PATH."off_road/");
    define("OR_SITE_DIRECTORY", OR_BASE_PATH."site/");
    define("OR_SETTINGS_FILE", "settings.php");
    
    define("LAYER_FOLDER_NAME", "layers");
    define("EXTENSION_FOLDER_NAME", "extensions");
