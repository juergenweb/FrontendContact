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
$tel = new \FrontendForms\InputText('telephone');
$tel->setLabel('Telephone number');
$tel->setRule('numeric');
$tel->setRule('required');

/*
instead of using the default add() method, it is recommended to use the addBefore() or addAfter() method to insert
the new input field on a specific position inside the form.
If you use the default add() method, then the new input field will be added to the last position inside the form
but this would probably not make a sense in most cases.

In this case the telephone input field will be added after the email field.
*/

// the first parameter is the newly created input field
// the second parameter is the form element object, after which the new field should be inserted.
$cf->addAfter($tel, $cf->getEmail());

/*
 * If you want to add this field before or after another field, you have to use another field object instead
 * of the email. To grab a certain field object, you have to use the name attribute of this field with the getElementByName
 method from the FrontendForms module.
 * 
 * $genderfield = $cf->getElementsByName('contact-form-gender); // Returns the Gender object.
 *
 * To find out the name of the field please take a look inside the source code
 *
 * fe $cf->addAfter($tel, $genderfield); or
 * $cf->addBefore($tel, $genderfield);
*/

// at the last step, output the form
echo $cf->render();
