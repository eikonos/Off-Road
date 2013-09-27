
# Introduction

For each page, there's a url route entry, a controller function, probably a model or two, and a template file. Here's what the code looks like to display a simple blog page.

### Step 1: Create Route Entry

    # name, url regular expression, controller, function, default parameters
    add_route("blog_entry", "^blog/(?P<blog_id>[\d]+)\$", "blog", "entry", null);

The requested url is checked against the route regular expressions until a match is found. The controller and function for the matching url are called. Route names can be used within page templates to generate the correct url rather than hard-coding urls.

If no matching url is found the 'error' controller, function '404' is called.

### Step 2: Create Blog Model

    class blog extends rowobj
    {
        static $fields = array(
            "id"                => array("type"=>"id"),
            "title"             => array("type"=>"text", "null"=>false),
            "body"              => array("type"=>"text", "null"=>false),
            "publish_date"      => array("type"=>"datetime"),
            "create_date"       => array("type"=>"createdate")
            );
        ...
        static function &get_id($id = null){return blog::get()->id($id);}
        ...
    }

### Step 3: Create Blog Controller

    switch ($route->function)
    {
        case "entry":
        # load the blog model object for the blog_id in the url
        if (null == ($blog = blog::get_id($blog_id)))
        {
            # if the blog_id is invalid, go to the home page
            redirect_to_url("home");
        }
        break;
    }

The controller function loads all model objects required by the page template.

### Step 4: Create Blog Entry Template

    {% extends 'base.html' %}
    {% block title %}{{ blog.title }}{% endblock %}
    
    {% block content %}
    <h1>{{ blog.title }}</h1>
    {{ blog.body | raw }}
    <p>posted by: {{ blog.author }} on {{ blog.publish_date }}</p>
    {% endblock %}

The page template uses the model objects to render the page.

That's it!
