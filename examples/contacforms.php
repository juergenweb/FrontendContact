<?php
namespace ProcessWire;

/*
 * Examples of using the FrontendContact.module in your site
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: contacforms.php
 * Created: 11.10.2022 
 */


/**
 * The most simple example without customization
 * Put this little piece of code inside your template
 * This will output the contact form according to your settings in the module config
 */

echo $modules->get('FrontendContact');


/**
 * Advanced output with customization
 * This piece of code overwrites the global module configuration
 * This will output the contact form according to your settings from below
 */

$form1 = $modules->get('FrontendContact')->getForm();
$form1->setMinTime(10); // set the min time to 10 seconds -> creates an error if the form was submitted under 10 seconds
$form1->setSuccessMessage('Thank you for sending me this message'); // set a custom success message
$form1->to('webdesign@linznet.at'); // send the message to this email addy
$form1->disableCaptcha(); // remove the CAPTCHA
$form1->showName(false); // do not show the name field -> you can also write empty parenthesis like $form1->showName() - it is the same
$form1->showSurname(); // do not show the surname field
$form1->getSubject()->setAttribute('class', 'myclass'); // add a custom class to the subject form field
echo $form1->render(); // output the form