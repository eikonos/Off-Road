
# Routing

The *add_route()* function can be used to build the routes array. The first parameter, name, can be null if it is not required. Named groups in the regular expression can be used to capture url variables. An array of named static variables can be set in the last parameter.

    # name, url regular expression, controller, function, default parameters
    add_route("blog_entry", "^blog/(?P<blog_id>[\d]+)\$", "blog", "entry", array("foo"=>1);

The *add_route()* function stores routes in the *$settings* variable in the following format:

    $settings["routes"] = array(
        $name => array($path, $controller, $function, $parameters);
        ...
    );

The routing layer sets the route array variables on the *$request* based on the request url. The rendering layer can then call the appropriate controller and function.

    $request['route']['controller'] = null;
    $request['route']['function'] = null;
    $request['route']['parameters'] = array();
