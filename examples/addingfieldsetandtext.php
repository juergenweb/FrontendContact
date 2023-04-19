<?php
declare(strict_types=1);

/*
 * Example how to add a fieldset over the name fields an to add a little bit of extra text
 * How to create fieldset an text is not part of this example - please take a look at the FrontendForms docs
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: addingfieldsetandtext.php
 * Created: 19.04.2023 
 */


$cf = $modules->get('FrontendContact')->getForm(); // grab the contact form object first


// add fieldset start before the name
$fieldset_start = new \FrontendForms\FieldsetOpen();
$fieldset_start->setLegend('Names');
$cf->addBefore($fieldset_start, $cf->getFormElementByName('name'));

// add fieldset end after the surname
$fieldset_end = new \FrontendForms\FieldsetClose();
$cf->addAfter($fieldset_end, $cf->getFormElementByName('surname'));

// adding a little bit of text before the name field
$text = new \FrontendForms\TextElements();
$text->setTag('p');
$text->setContent('I am a little text.');
$cf->addBefore($text, $cf->getFormElementByName('name'));

// add fieldset start before the name field
$fieldset_start2 = new \FrontendForms\FieldsetOpen();
$fieldset_start2->setLegend('Others');
$cf->addBefore($fieldset_start2, $cf->getFormElementByName('email'));

// add fieldset end after the surname field
$fieldset_end2 = new \FrontendForms\FieldsetClose();
$cf->addAfter($fieldset_end2, $cf->getFormElementByName('message'));

echo $cf->render();
