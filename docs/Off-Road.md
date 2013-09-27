# PHP Off Road

Welcome to the documentation for PHP Off Road. This framework is designed to allow developers write concise, simple, readable code.

## [Introduction](introduction)

See what the code looks like to display a [simple blog page](introduction) -- the 'hello world' of web frameworks.

## [Settings](settings)

The [settings file](settings) controls which layers and extensions are loaded -- it defines how the site works.

## [Layers](layers)

Off Road is very flexible because the main index file loads the settings file, then loads the [layers](layers) specified there. You choose which of the provided layers you want to use, and [create your own](new_layers) too.

## [Extensions](extensions)

[Extensions](extensions) are normal PHP files (loaded by the optional extension_loader layer) with helper functions or classes. You can choose to use any of the provided extensions, and also write your own.

## [Models](models)

Models provide [database access](database_access). Use the provided ORM, or plug in your own.

## [Routing](routing)

The provided [routing layer](routing) matches urls using regular expressions. Use the provided routing, or plug in your own.

## Templates

There are many good template systems for PHP, including PHP itself, so there's no point reinventing another template system. By default, [Twig^](http://twig.sensiolabs.org/documentation) is used to render templates, but you can plug in another template system, or use straight PHP if that's what you prefer.

## Forms

Writing html forms is complex and repetitive, so Off Road provides an optional [object-oriented form](forms) with a simple syntax.

## Static Content

Using apache and mod_rewrite is a good way to [serve static content](static_content) such as css, javascript and images.

## Additional Libraries

There are no hard rules about using other PHP modules, but for consistency and maintainability, a good way is to put the module into the site/libraries folder and create an extension or layer that includes or requires the necessary files.
