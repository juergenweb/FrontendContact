<?php
declare(strict_types=1);

/*
 * Example on how to extend the contact form with a new input field
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: addingnewfield.php
 * Created: 11.04.2023 
 */


namespace ProcessWire;

$cf = $modules->get('FrontendContact')->getForm(); // grab the contact form object first

// create the new field for adding telephone number
// Please take a look at the docs of the FrontendForms module on how to create a new element
$phone = new \FrontendForms\InputText('telephone');
$phone->setLabel('Phone number');
$phone->setRule('numeric');
$phone->setRule('required');

/*
instead of using the default add() method, it is recommended to use the addBefore() or addAfter() method to insert
the new input field on a specific position inside the form.
If you use the default add() method, then the new input field will be added to the last position inside the form
but this would probably not make a sense in most cases.

In this case the telephone input field will be added after the email field.
*/

// the first parameter is the newly created input field - in this case the phone field
// the second parameter is the form element object, which is the reference object - in this case the email field
$cf->addAfter($phone, $cf->getFormElementByName('email'));

/*
 * If you want to add this field before or after another field, you have to use another field object instead
 * of the email.
 *
 * So replace $cf->getFormElementByName('email') with another form object from the list to insert the new input field on another position.
 * fe $cf->addAfter($phone, $cf->getFormElementByName('subject')); or
 * $cf->addBefore($phone, $cf->getFormElementByName('subject'));
*/

// at the last step, output the form
echo $cf->render();
