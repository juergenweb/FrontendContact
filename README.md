# FrontendContact
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

> ⚠ This module is very Alpha stage - so be aware of using it on live sites!

A configurable module for ProcessWire for outputting a simple contact form on your site based on the FrontendForms module.

## Intentions for creating this module
A contact form is something you will need on almost every website. Sometimes you will need more than one (fe if you have a staff member page, and you will offer a contact form for each staff member).
With the FrontendForms module, which is the base for this module, you will be able to create contact forms in an easy way by your own - there is nothing wrong with it.

My idea was to create a one-line code to implement a contact form easily. I do not want to write all the code every time, but the module should be flexible, so that the form could be adapted and enhanced to my needs. 
So this module should save a lot of time, it keeps templates clean from a lot of code and it offers a lot of customization possibilities. 

Please note: You have to install the FrontendForms module first, because this module relies on it.
So go to https://github.com/juergenweb/FrontendForms first and install the FrontendForms module on your site.

Just to mention: To prevent problems with other modules or classes this module runs in its own namespace "FrontendContact".

## Requirements
- ProcessWire 3.0.195 or newer
- PHP 8.0 or newer
- FrontendForms 2.1.25 or newer: Please download and install it from https://github.com/juergenweb/FrontendForms first.

## Highlights
- Fast and easy integration of a contact form inside a template by using only one line of code
- Select the fields which should be displayed inside the form 
- Beside the default fields you will be able to extend the form with additional fields
- Highly customizable (change order of fields, add custom CSS classes,...)
- Run as many forms on one page as you want
- Possibility to offer file upload to upload multiple files, which can be sent as attachments
- Usage of all the benefits of [FrontendForms](https://github.com/juergenweb/FrontendForms#highlights) (fe. CAPTCHA, various security settings,...)
- Mutli-language

## Table of contents
* [Configuration](#configurations)
* [Integrations of forms on the frontend](#integrations-of-forms-on-the-frontend)
* [Special contact form methods](#special-contact-form-methods)
* [Extending the form with additional inputfields](#extending-the-form-with-additional-input-fields)
* [Multilanguage](#multi-language)


## Configurations
After you have installed the module, you have to set the default email address where the mails should be sent to. This email address can be entered manually or you choose a ProcessWire field, which contains the email address. All other configuration options are optional.

* **`Show or hide the following fields`** gender, name, surname, subject, file upload, privacy and send copy (email and message field are
mandatory and therefore permanent and not selectable whether to be shown or not)
* **`Map the following fields to fields inside the user template`** email, gender, name, surname. If a user is logged in, than the value of the appropriate mapped field of the user template will be set as value on the frontend
* **`Change required status of following fields`** gender, name, surname, subject (send copy field is always
optional and privacy field is always required. Therefore, for both fields the status cannot be changed)
* **`Set a global receiver email address`** You can enter an email by text, or you can choose a PW field, which holds the value
* Choose a mail template for your email or send it as plain text (none, template_1, template_2,...)
* **`Set a global minimum form submission time`** Set a global minimum time before a form is allowed to be submitted (spam protection)
* **`Select email template`** You can select if you want to send an HTML or a plain text email.

Each global configuration setting can be overwritten on per form base.

### Integrations of forms on the frontend

If you want to use the global settings of the module configuration, enter the following code inside a template, where you want to include your contact form.

```php

// render the form
echo $modules->get('FrontendContact')->render();

```

If you want to change some parameters of the global settings (fe changing of the receiver address, hide a certain field,...), you have to grab the form object first, manipulate all the parameters or elements and render it at the end.
Just take a look of the following example to change the receiver address. You will find much more examples inside the examples folder or inside the module configuration page of this module.


```php
// render the form
$form = $modules->get('FrontendContact')->getForm(); // this loads the form object for further manipulation
$form->to('office@myemail.com'); // set or overwrite the recipient email address
echo $form->render();
```

### Special contact form methods
Beside the methods of the FrontendForms module, I have added some extra methods for this module to make it much more confortable to manipulate the form.

#### getForm() method
This method returns the form object and this method is needed to manipulate values of the form.

#### to() method
This method is the same method as the WireMail to() method. You can enter a recipient for your contact form.
If you have entered a default recipient inside the configuration, this method will overwrite this recipient.

```php
$form->to('office@myemail.com'); // set or overwrite the recipient email address
```
#### Show or hide fields methods
With these methods you can overwrite the global settings to show or hide a form field on the form.
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
$form->showFileUploadMultiple(false); // file upload field will not be included
```

#### Set fields to required or not
You can change the required status of each of the following fields on per form base.

```php
$form->requiredGender(true); // gender field will be required
$form->requiredName(false); // name field will not be required
$form->requiredSurname(); // surname field will not be required
$form->requiredSubject(true); // subject field will be required
```

#### Get fields for further customization methods
Each field can be customized further individually. You have to use the methods from the FrontendForms module. You will find more information inside the readme file of the FrontendForms module - so take a look there.
To grab each form field object you have to use the FrontendForms method getElementByName(). Take a look at the source code to get the name of the field you want to change.

```php
$genderfield = $form->getElementByName('contact-form-gender'); // returns the gender field object
```
Now you can add fe some custom classes for styling purposes:

```php
$genderfield->setAttribute('class', 'mynewclass'); 
```
The manipulation of form fields can be done with methods from the FrontendForms module, so I do not want to get to much into detail - please study the docs of the FrontendForms module.

## Extending the form with additional input fields
The default form contains pre-defined input fields, which should be enough in most cases. But sometimes you will need to add an additional input field or you want to add a fieldset, a text or whatever to the form.
For this scenario, you will be able to extend the form with new elements and you can set the position of these elements inside the form via 2 methods: addBefore() 
and addAfter(). 
Both methods are from the FrontendForms module and will be used to add a new element at a new position inside the form or to move an existing form element to a new position. You will find a detailed information about these 2 methods in the docs of the FrontendForms module. 
To demonstrate how it works, I have included an example on how to add a new input field inside the examples folder: So please take a look at the [addingnewfield.php](https://github.com/juergenweb/FrontendContact/blob/main/examples/addingnewfield.php) and study the example on how to extend the form with new elements.

## Multi-language
This module is ready for usage in multi-language site.
The module will be shipped with the German translation file (default is English).
If you want to provide a language file for another language, please send it to me over GitHub and I will include it
in the module for other users.
