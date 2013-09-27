
# Forms

In the controller, load the forms_loader extension and create a form object using the load_forms() helper function. The first parameter is the name of the form file. Any other parameters may be passed, as required by the form object.

    load_extension("forms_loader");
    $form_do_something = load_forms("<form file name>", $var1, ...);

The form object must have the same name as the file.

    class <form file name> extends forms
    {
        function __construct($var1, ...){parent::__construct(get_defined_vars());}
        function create_inputs()
        {
            $this->_meta->submit_button->label = "Submit";
            $this->title = textbox()->size(30)->value(<initial value>)
                ->validate(array("string_minmax", "validate_item"), array("min"=>3, "max"=>50),
                    "Please enter a title.");
        }
        function validate()
        {
            # do extra validation here, or return true
            return true;
        }
        function validate_item(&$item)
        {
            # custom validation for a form element
            if (<item is not valid>)
            {
                $item->err_msg(<something is wrong>);
                return false;
            }
            return true;
        }
        function done($results)
        {
            # do something here
            redirect_to_url(<target url name>);
        }
    }

## Form Functions

* __construct() -- optional

    This function can be used to set extra variables the form can use -- for example, the object being edited.

* create_inputs()

    This function is where all form inputs and attributes are set.

* validate()

    This function is required, and it returns a boolean indicating if extra form validation succeeded. It can be used for complex validation that acts on more than one form input. For example, if one or more of a set of checkboxes must be checked, that should be tested in *validate()* rather than once in each of the checkboxes.

* extra validation functions

    Forms can have custom validation functions for any of the input items. The input item will be passed by reference so the value can be checked and an error message set if necessary.

* done()

    This function is called once all validations have been passed. The results from all the form inputs are passed as a stdClass object with an attribute for each form input value. The form in the example will pass a stdClass object with $result->title.


## Validation

To use form validation on an input object, call the validation function. The first parameter is a string or an array of strings with the name of validation functions. These can be the built-in functions, or a function on the particular form object. If an array of functions is provided, then they are called in turn until one fails. The second parameter is a set of options, which depend on the validation functions. The third parameter is a default error message to be displayed if the input object is not valid.

    ->validate(array("<validation function 1>", "<validate function 2>"), array(<validation options>),
                    "<error message>");

The built-in validation functions are:

* string_minmax

    Validate that a string has a minimum and maximum length. The validation options are *array("min"=>0, "max"=>50)*.

* validate_selected

    This function should be used on select and radio inputs to ensure that one of the available values is selected. The validation options is an array of arrays.

    $options = array();
    $options[] = array("value"=>1, "label"=>"One");
    $options[] = array("value"=>2, "label"=>"Two");
    $options[] = array("value"=>3, "label"=>"Three");

* validate_checked

    This function is used for checkbox form inputs that must be checked -- for example, agreeing to terms of use.

* validate_phone or validate_phone_or_blank

    This function validates that a phone number has been entered in a text box. The second will allow a blank value.

* validate_reserverd_characters

    This function ensures that only characters specified by a regular expression can be entered in the box. The validation option is a regular expression listing valid characters -- *array("reserved_characters"=>"a-zA-Z")*.

* validate_reserved_words

    This function ensures that the value in the form input is not in a list of reserved keywords. The validation option is a list of reserved words -- *array("reserved_words"=>array("bad", "words"))*.

* validate_file_size

    This function ensures that an uploaded file is smaller than a specified limit. The validation option is a file size in KB -- *array("size"=>1024)*.

## Custom Validation

A form can provide its own validation function. Custom validation functions can use the validation option array, or access variables from the form itself. A custom error message can be set. Return true if the item is validated.

    function validate_item(&$item)
    {
        $value = $item->get_value();
        $v = $item->get_validate_data();
        if ($value != $v["good_value"])
        {
            $item->err_msg("This value is not good.");
            return false;
        }
        return true;
    }


## Inputs

The built-in form input controls are:

* button

    A button input calls a function if it is pressed.

* checkbox

    A checkbox returns true or false.
    `$this->agree = checkbox()->value(true);`

* hidden

    A hidden input returns a string.

* password

    A password input is just like a textbox input, except the password cannot be read on the screen.

* textbox

    A textbox input returns a string.

* submit_button

    A submit button is automatically created for a form. Set the button label by assigning a value to *$this->_meta->submit_button->label* in *create_inputs()*.

## Custom Inputs

Custom input controls can be created. The should derive from forminput so the form class knows to initialize them and return their values.

## Displaying Forms

Forms can be rendered as HTML in many different ways. The form.submitting values is a required hidden value that allows the form to determine it is being submitted -- this allows multiple forms on one page. The hidden csrf tag will be created if the csrf layer is used and it has set a value in the *$request* variable. A simple form might look like this example.

    {{ form_x.form_begin | raw }}
    {% if form_x.show_errors %}
        {% for input in form_x.inputs %}
            {% if not input.get_valid %}<p class="formerror">{{ input.get_err_msg }}</p>{% endif %}
        {% endfor %}
    {% endif %}
    {{ form_x.title.print_label | raw }} {{ form_x.title | raw }} {{ form_x.submit_button | raw }}
    {{ form_x.csrf | raw }}
    {{ form_x.submitting | raw }}
    {{ form_x.form_end | raw }}
