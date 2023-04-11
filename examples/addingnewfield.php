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
$tel->setRule('number');
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
 * of the email. You will get each pre-defined form element with the following syntax: get + name of the field()
 * You will find a list of all methods afterwards:
 *
 * $cf->getGender(); // Returns the Gender object.
 * $cf->getName(); // Returns the Name object.
 * $cf->getSurname(); // Returns the Surname object.
 * $cf->getEmail(); // Returns the Email object.
 * $cf->getSubject(); // Returns the Subject object.
 * $cf->getMessage(); // Returns the Message object.
 * $cf->getPrivacy(); // Returns the Privacy checkbox object.
 * $cf->getSendCopy(); // Returns the Send a copy to me checkbox object.
 * $cf->getButton(); // Returns the submit button object.
 *
 * So replace $cf->getEmail() with another form object from the list to insert the new input field on an other position.
 * fe $cf->addAfter($tel, $cf->getSubject()); or
 * $cf->addBefore($tel, $cf->getSubject());
*/

// at the last step, output the form
$content .= $cf->render();