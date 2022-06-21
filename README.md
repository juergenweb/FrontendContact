# FrontendContact
A module for ProcessWire for outputting a simple contact form using the FrontendForms module.
You can use as much forms as you want on the same page, but you have to set a different ids for each form.
Please note: You have to install the FrontendForms module first, because this module relies on it
To prevent problems with other modules or classes this module runs in its own namespace "FrontendContact".

## Configurations
After you have installed the module (you need to install the FrontendForms module first), you can do some configuration settings in the backend.

* Show a "accept data privacy" checkbox on the form
* Show a "send a copy to of my message to me" checkbox on the form
* Textfield to enter the recipient email address
* Multiple checkboxes to select which fields should be required
* Select field to use the email of a ProcessWire field
* Select field to use the options of a ProcessWire field as options of the gender field
* Choose a mail template for your email

## Default settings of the form
By default the form has the following settings:

* Number of MaxAttempts: 5
* Minimal time for filling out the form: 3 seconds
* Maximal time for filling out the form: 3600 seconds

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
$form->to('office@myemail.com'); // set or owerwrite the recipient email address
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
$form->to('office@myemail.com'); // set or owerwrite the recipient email address
```
#### showDataPrivacyCheckbox() method
This method let you enable/disable the displaying of the "I accept the data privacy" checkbox on the form. As value inside the parenthesis you have to enter a boolean value (true or false).
This will overwrite the module configuration in the backend.
Default value is true.

```php
$form->showDataPrivacyCheckbox(false); // false = disable, true = enable
```

#### showCopyCheckbox() method
This method let you enable/disable the displaying of the "Send a copy of my message to me" checkbox on the form. As value inside the parenthesis you have to enter a boolean value (true or false).
This will overwrite the module configuration in the backend.
Default value is false

```php
$form->showCopyCheckbox(false); // false = disable, true = enable
```

## Overwriting default settings
As mentioned above you can overwrite the default settings. The code below demonstrates this:

```php
$form = $modules->get('FrontendContact')->getForm();
$form->setAttribute('id', 'contactform-2'); // add a new id to the form -> necessary if you have 2 forms on the same page
$form->to('juergen.kern@linznet.at'); // set or owerwrite the recipient email address
$form->setMaxAttempts(10); // overwrite max attempts
$form->setMinTime(10); // overwrite min time
$form->setMaxTime(1000); // overwrite max time
$form->showCopyCheckbox(true); // enable the displaying of the copy checkbox
$form->showDataPrivacyCheckbox(false); // disable the displaying of the data privacy checkbox
$form->setSuccessMsg('Thank you so much'); // show an alternative success message.
echo $form->render();
```


## Multilanguage
The module will be shipped with the German translations (default is English).
