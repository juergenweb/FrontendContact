# FrontendContact
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

> ⚠ This module is very Alpha stage - so be aware of using it on live sites!

A configurable module for ProcessWire for outputting a simple contact form on your site based on the FrontendForms module.

Please note: You have to install the FrontendForms module first, because this module relies on it.
So go to https://github.com/juergenweb/FrontendForms first and install the FrontendForms module on your site.

This module is not necessary to create a contact form - you can do it all on your own by using the FrontendForms module
only, but it will be a useful addition in order to keep your templates clean from a lot of code and BTW it saves you a lot of work.

Just to mention: To prevent problems with other modules or classes this module runs in its own namespace "FrontendContact".

## Requirements
- ProcessWire 3.0.195 or newer
- PHP 8.0 or newer
- FrontendForms 2.1.25 or newer: Please download and install it from https://github.com/juergenweb/FrontendForms first.

## Highlights
- Fast and easy integration of a contact form to at template by using only one line of code
- Select the fields that should be displayed
- Extend the default form with additional fields, if needed
- Run as many forms on a page as you want
- Usage of all the benefits of [FrontendForms](https://github.com/juergenweb/FrontendForms#highlights) (fe. CAPTCHA, various security settings,...)
- Mutli-language

## Configurations
After you have installed the module, you have to enter a default email address where the mails should be sent to. All other configuration options are optional.

* Show or hide the following fields: gender, name, surname, subject, privacy, send copy (email and message fields are
mandatory and therefore permanent).
* Set the status of the following fields to required or not: gender, name, surname, subject (send copy field is always
optional and privacy field is always required. Therefore, for both fields the status cannot be changed).
* Set a global email address or not. You can enter an email by text, or you can choose a PW field, which holds the value.
* Choose a mail template for your email or send it as plain text (none, template_1, template_2,...).
* Set a global minimum time before a form is allowed to be submitted (spam protection):

## Default settings of the form that cannot be changed inside the module config
By default, the form has the following settings:

* Number of MaxAttempts: This value will be taken from the FrontendForms module global configuration settings
* Maximal time for filling out the form: This value will be taken from the FrontendForms module global configuration settings

Each of the settings can be overwritten if necessary.

### Usage on the frontend

Create or select a page (fe. contact page) where you want to include your contact form and add the following code to the template.

```php

// render the form
echo $modules->get('FrontendContact');

```

This is all you have to do, if you do not want to modify some values.
Please note: The code above works only if you have entered a default email address inside the module configuration settings in the backend.
If not, you have to enter the recipient email address manually (see the code below)

```php

// render the form
$form = $modules->get('FrontendContact')->getForm(); // this loads the form object for further manipulation
$form->to('office@myemail.com'); // set or overwrite the recipient email address
echo $form->render();
```

If you want to change parameters or values of the form (fe success message, recipient, time measurement settings,....), you have to call the getForm() method first to get the form object.
This object can be manipulated as described in the FrontendForms docs.
At the end you have to use the render() method to render the form markup.

### Special contact form methods

#### getForm() method
This method returns the form object and is needed to manipulate values of the form.

#### to() method
This method is the same method as the WireMail to() method. You can enter a recipient for your contact form.
If you have entered a default recipient on the configuration, this method will overwrite this recipient.

```php
$form->to('office@myemail.com'); // set or overwrite the recipient email address
```
#### Show or hide fields methods
With these methods you can overwrite the global settings and show or hide a form field on the form.
The name of the method is always prefix show with the name of the form field class.
As the parameter you have to set true or false.
TRUE: The form field will be displayed on the form
FALSE: The form field will not be included in the form

BTW: You do not have to enter the value false inside the parenthesis - you can leave them empty

```php
$form->showGender(true); // gender field will be included
$form->showName(true); // name field will be included
$form->showSurname(true); // surname field will be included
$form->showSubject(false); // subject field will not be included
$form->showPrivacy(); // privacy field will not be included
$form->showSendCopy(false); // send copy field will not be included
```

#### Set fields to required or not methods
You can change the required status of each of the following fields on per form base.

```php
$form->requiredGender(true); // gender field will be required
$form->requiredName(false); // name field will not be required
$form->requiredSurname(); // surname field will not be required
$form->requiredSubject(true); // subject field will be required
```

#### Get fields for further customization methods
Each field can be customized. You have to use the methods from the FrontendForms module. You will find more information
inside the readme file of the FrontendForms module - so take a look there.
To grab each form field object you have to use the following methods.

```php
$form->getGender(); // returns the gender field object
$form->getName(); // returns the name field object
$form->getSurname(); // returns the surname field object
$form->getEmail(); // returns the email field object
$form->getSubject(); // returns the subject field object
$form->getMessage(); // returns the message field object
$form->getPrivacy(); // returns the privacy field object
$form->getSendCopy(); // returns the copy sending field object
$form->getButton(); // returns the button field object
```

## Extend the form with additional input fields
The default form contains pre-defined input fields, which should be enough in most cases. But sometimes you will need to add an additional input field or you want to add a fieldset, at text or whatever to the form.
For this scenario, you will be able to extend the form with new elements and you can set the position of these elements inside the form via 2 methods: addBefore() 
and addAfter(). 
Both methods are from the FrontendForms module and will be used to add a new element at a new position inside the form.  You will find a detailed information about these 2 methods in the docs of the FrontendForms module. 
To demonstrate how it works, I have included an example on how to add a new input field inside the examples folder: So please take a look at the addingnewfield.php and study the example on how to extend the form with new elements.


## Rendering and overwriting forms on your site
As mentioned above you can overwrite the default settings. 
To get examples on how to overwrite or output the form on your site, please take a look at the examples folder.
There you will find some real lives examples.

## Multi-language
The module will be shipped with the German translation file (default is English).
If you want to provide a language file for another language, please send it to me over GitHub and I will include it
in the module for other users.
