# PHP Off Road

A simple, flexible PHP website framework.

PHP Off Road is a set of simple components that can be used as the flexible basis of a web framework.

There are two distinct types of components, 'layers' and 'extensions'. Layers are object classes extending the 'layer' object, and providing at least one function. Extensions are files that contain arbitrary PHP code.

## Layers

Layers form the heart of the Off Road framework, and provide the flexibility. All of the included layers are optional, and you can easily write your own layers to extend or replace the exsiting ones. The only requirement is that the final layer 'render' and return the page content.

Layers make it easy to add authentication to require users to log in to view private pages, or to switch the render layer to any templating system, including pure PHP.

## Extensions

The included extensions provide database access, regular expression url routing, html forms and so on.

## Using the Framework

The core part of the framework provides only basic site-agnostic functionality. To use the site, create a settings.php with your site configuration options, and include the index.php file.

## More Information

for more information, read the docs folder, or see the PHP Off Road documentation website as an example of how to build a simple site.
