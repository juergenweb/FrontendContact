<?php
declare(strict_types=1);

namespace FrontendContact;

/*
 * ContactForm class for ProcessWire to create and validate a simple contact form on the frontend
 * This class is a child of the Form class from FrontendForms
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: ContactForm.php
 * Created: 04.10.2022
 */

use Exception;
use FrontendForms\Button;
use FrontendForms\Email;
use FrontendForms\Form;
use FrontendForms\Gender;
use FrontendForms\Message;
use FrontendForms\Name;
use FrontendForms\Privacy;
use FrontendForms\SendCopy;
use FrontendForms\Subject;
use FrontendForms\Surname;
use ProcessWire\WireMail;
use ProcessWire\FrontendContact;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

class ContactForm extends Form
{

    // Default form fields
    protected Gender $gender; //the gender field object
    protected Name $name; // the name field object
    protected Surname $surname; // the surname field object
    protected Email $email; // the email field object
    protected Subject $subject; // the subject field object
    protected Message $message; // the message field object
    protected Privacy $privacy; // the privacy field object
    protected SendCopy $sendCopy; // send copy field object
    protected Button $button; // the button object
    protected WireMail $mail; // the WireMail object for sending mails

    // stored user values from the db for usage as form values if user is logged in
    protected string $stored_gender = '';
    protected string $stored_name = '';
    protected string $stored_surname = '';
    protected string $stored_email = '';
    protected bool $custom_receiver = false;

    protected array $frontendcontact_config = [];

    /**
     * array of all method names that will be called via __call() method
     * No need to create each method manually
     */
    private array $methodList = [
        'showGender',
        'requiredGender',
        'showName',
        'requiredName',
        'showSurname',
        'requiredSurname',
        'showSubject',
        'requiredSubject',
        'showFileUploadMultiple',
        'showPrivacy',
        'showSendCopy'
    ];

    /**
     * @throws WireException
     */
    public function __construct(string $id = 'contact-form')
    {

        parent::__construct($id);


        // get module configuration data from FrontendContact module and create properties of each setting
        foreach ($this->wire('modules')->getConfig('FrontendContact') as $key => $value) {
            $this->frontendcontact_config[$key] = $value;
        }

        // instantiate the WireMail class object for sending the mails
        $this->mail = new WireMail();

        // set the title
        $this->mail->title($this->_('A new message via contact form'));
        // set the email template to the WireMail object
        $this->mail->mailTemplate($this->frontendcontact_config['input_emailTemplate']);
        // add default settings from this module to the form
        $this->setMinTime($this->frontendcontact_config['input_minTime']); // min time

        // set default receiver address
        switch ($this->frontendcontact_config['input_emailtype']) {
            case('text'):
                $this->receiverAddress = $this->frontendcontact_config['input_default_to']; // manually entered mail address
                break;
            case('pwfield'):
                $this->receiverAddress = $this->frontendcontact_config['input_defaultPWField_to']; // value of a PW field
                break;
        }

        // Create an instance of each form field depending on the module config
        $this->createAllFormFields();

        // create gender select options depending on a user field if a user field was mapped to it
        $this->adaptGenderSelect();

        // map user data as value to the form fields if user is logged in
        $this->setUserDataToField();

        $this->useSettingsOfFormFields();

    }

    /**
     * Magic method used to set required status, add or remove a field or to get the field object on the fly
     * The $methodList array will be used for allowed method calls
     * @param $func
     * @param $params
     * @return bool|mixed|object
     */

    public function __call($func, $params)
    {
        if (in_array($func, $this->methodList)) {
            $startsWith = substr($func, 0, 3);

            $param = (!isset($params[0])) ? false : $params[0];

            switch ($startsWith) {
                case('sho'):
                    $className = str_replace('show', '', $func);
                    $fieldName = $this->generateConfigFieldname($className, 'show');
                    $this->frontendcontact_config[$fieldName] = $param;
                    break;
                case('req'):
                    $className = str_replace('required', '', $func);
                    $fieldName = $this->generateConfigFieldname($className, 'required');
                    $this->frontendcontact_config[$fieldName] = $param;
                    break;
            }

        }
        return null;

    }

    /**
     * Alias function for the WireMail function subject() to set the subject for the mail on per form base
     * @param string $string
     * @return void
     */
    public function subject(string $string):self
    {
        $m = $this->getMail();
        $m->subject($string);
        return $this;
    }

    /**
     * Alias function for the WireMail function to() to set the receiver address for the mail on per form base
     * @param string $email
     * @return $this
     * @throws WireException
     */
    public function to(string $email):self
    {
        $m = $this->getMail();
        if ($this->wire('sanitizer')->email($email)) {
            $m->to($email);
            $this->custom_receiver = true;
        } else {
            throw new Exception("Email address for the recipient is not a valid email address.", 1);
        }
        return $this;
    }

    /**
     * Get the WireMail object
     * This is needed to change email templates on per WireMail base
     * @return WireMail
     */
    public function getMail():WireMail
    {
        return $this->mail;
    }

    /**
     * Get an array of all input fields inside the module configuration which were used to map contact
     * form fields to user template fields
     * The appendix "_mapped" will be used to identify those fields
     * @return array
     */
    protected function getMappedFields():array
    {
        return array_filter($this->frontendcontact_config, function ($key) {
            return strpos($key, '_mapped');
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * If the user is logged in and a user field is mapped to a contact form field, then use the value
     * as stored inside the database and add the disabled attribute to the input field
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function setUserDataToField():void
    {

        if ($this->user->isLoggedin()) {


            foreach ($this->getMappedFields() as $k => $v) {

                if ($v != 'none') {
                    // get the user field
                    $field = $this->wire('fields')->get($this->frontendcontact_config[$k]);

                    // extract the name of the field
                    $name = explode('_', $k)[1];
                    $stored_name = 'stored_' . $name;
                    // get the type of the input field (SelectOptions, InputText,...)
                    $type = $field->getFieldtype();

                    switch ($type) {
                        case('FieldtypeText'):
                            $this->{$stored_name} = $this->user->{$field->name};
                            if ($this->{$stored_name}) { // only if a value is stored inside the database
                                $formfield = $this->getFormElementByName($name);
                                if ($formfield) {
                                    $formfield->setAttribute('disabled');
                                    $formfield->removeRule('required'); // not necessary anymore
                                    $formfield->setAttribute('value', $this->{$stored_name});
                                }
                            }
                            break;
                        case('FieldtypeOptions'):
                            $userField = $this->user->{$field->name};
                            $this->{$stored_name} = $userField->title;
                            if ($this->{$stored_name}) { // only if a value is stored inside the database
                                $formfield = $this->getFormElementByName($name);
                                if ($formfield) {
                                    $formfield->setAttribute('value', $userField->title);
                                    $formfield->setAttribute('disabled');
                                }
                            }
                            break;
                    }

                }

            }

            //Email will always be set from the database
            $this->getFormElementByName('email')->setAttribute('value', $this->user->email)->setAttribute('disabled');
            $this->stored_email = $this->user->email;

        }
    }

    /**
     * If a user field was mapped to the gender field - add the options from the user field to the gender field
     * @return void
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function adaptGenderSelect():void
    {

        if (($this->frontendcontact_config['input_gender_show']) && ($this->frontendcontact_config['input_gender_userfield_mapped'] != 'none')) {
            // remove all options first
            $this->getFormelementByName('gender')->removeAllOptions();
            //grab all options in the page language
            $fieldtypeGender = $this->wire('fields')->get($this->frontendcontact_config['input_gender_userfield_mapped']);
            if ($fieldtypeGender) {
                $type = $fieldtypeGender->type;
                // check if field is type of FieldtypeOptions
                if ($type->className() == 'FieldtypeOptions') {
                    $fieldtype = $type->getOptions($fieldtypeGender);
                    $lang = $this->wire('user')->language->id; // grab current lang id
                    $defaultLang = $this->wire('languages')->getDefault()->id; // get default lang id
                    $id = ($lang == $defaultLang) ? '' : $lang;
                    $title = 'title' . $id;
                    foreach ($fieldtype as $value) {
                        $this->getFormelementByName('gender')->addOption($value->{$title}, $value->{$title});
                    }
                }
            }
        }
    }

    /**
     * Generates the field name out of the class name
     * @param string $className
     * @param string $suffix
     * @return string
     */
    protected function generateConfigFieldname(string $className, string $suffix):string
    {
        $className = lcfirst($className);
        $createName = ['input', $className, $suffix];
        $createName = array_filter($createName);
        return implode('_', $createName);
    }

    /**
     * Remove a field depending on settings and set a field to required or not
     * @return void
     */
    protected function useSettingsOfFormFields():void
    {
        // remove permanent fields from the fields array
        $formFields = FrontendContact::$formFields;

        $this->frontendcontact_config['input_email_show'] = true;
        $this->frontendcontact_config['input_message_show'] = true;
        $this->frontendcontact_config['input_button_show'] = true;

        // create new formElements array
        $this->formElements = [];
        foreach ($formFields as $position => $temp) {
            $fieldObjectName = lcfirst($temp);
            $configFieldNameShow = $this->generateConfigFieldname($fieldObjectName, 'show');
            $configFieldNameRequired = $this->generateConfigFieldname($fieldObjectName, 'required');

            $field = $this->{lcfirst($temp)};

            // remove the field if set to hide
            if (!$this->frontendcontact_config[$configFieldNameShow]) {
                $this->remove($field);
            } else {
                $this->formElements[] = $field;
                //set required status to the field

                if ($configFieldNameRequired != 'input_privacy_required') { // exclude privacy field
                    if ((array_key_exists($configFieldNameRequired,
                            $this->frontendcontact_config)) && ($this->frontendcontact_config[$configFieldNameRequired])) {
                        if (!$field->hasRule('required')) {
                            $field->setRule('required');
                        }
                    } else {
                        if ($temp != 'Privacy') // never remove required from privacy field !!
                        {
                            if (!$field instanceof Button) {
                                $field->removeRule('required');
                            }
                        }
                    }
                }
                // add the field to the form elements array if it is not there
                if (!$this->getFormElementByName($field->getAttribute('name'))) {

                    if ($position == 0) {
                        $ref_name = strtolower($formFields[1]);
                        $reference_field = $this->getFormElementByName($ref_name);
                        $this->addBefore($field, $reference_field);
                    } else {
                        $ref_name = strtolower($temp);
                        $reference_field = $this->getFormElementByName($ref_name);
                        $this->addAfter($field, $reference_field);
                    }
                }

            }
        }

    }

    /**
     * Method to create and add all field classes to the form object
     * In a second step, placeholders for all form field labels will be created
     * The placeholders can be used in templates
     * @return void
     */
    protected function createAllFormFields():void
    {

        foreach (FrontendContact::$formFields as $className) {
            $propName = lcfirst($className);
            $class = 'FrontendForms\\' . $className;
            $this->{$propName} = new $class(strtolower($className));

            $this->add($this->{$propName}); // add every form field independent of settings
            // create label placeholder for all fields of the form by default if label property exists
            if (property_exists($this->{$propName}, 'label')) {
                $fieldName = 'input_' . $propName . '_show';
                if ((array_key_exists($fieldName,
                        $this->frontendcontact_config)) && (!$this->frontendcontact_config[$fieldName])) {
                    if (!in_array($className, ['Email', 'Message'])) {
                        $this->remove($this->{$propName});// remove the field form the form
                    }
                }
            }
        }
    }

    /**
     * Grab all POST values and put them into a string for sending it with the mail
     * @return array
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createDataPlaceholder():array
    {

        if ($this->user->isLoggedin()) {

            $stored_data = [];

            foreach ($this->getMappedFields() as $name => $id) {
                if ($id != 'none') {

                    $field = $this->wire('fields')->get($id); // get the field object
                    $database_field_name = $field->name; // get name of mapped field inside the database

                    if ($field->type == 'FieldtypeOptions') {
                        $stored_value = $this->user->$database_field_name->title;
                    } else {
                        $stored_value = $this->user->$database_field_name;
                    }

                    if ($stored_value) {
                        $name = explode('_', $name)[1];

                        // set the placeholder value
                        $this->setMailPlaceholder($name . 'value', $stored_value);
                        $form_field_name = $this->getID() . '-' . $name;

                        if ($this->getFormelementByName($form_field_name)) {
                            $stored_data[$form_field_name] = $stored_value;
                        }
                    }
                }
            }
        }

        // merge form data with stored data
        $form_data = array_merge($stored_data, $this->getValues());

        // remove privacy and send copy values
        unset($form_data[$this->getID() . '-privacy']);
        unset($form_data[$this->getID() . '-sendcopy']);

        // create an extra placeholder containing all post values called all placeholders
        $placeholder = '';
        foreach ($form_data as $key => $value) {
            $name = str_replace($this->getID() . '-', '', $key);
            // do not allow array values ($_FILES)
            if (is_string($value)) {
                $placeholder .= '<div id="' . $key . '"><span class="label">' . $this->getMailPlaceholders()[strtoupper($name . 'label')] . '</label>: <span class="value">' . $value . '</span></div>';
            }
        }
        $this->setMailPlaceholder('allvalues', $placeholder);
        return $form_data;
    }

    /**
     * Method to send the email
     * This method set a placeholder variable to each form value, and it includes email (body and mail) templates before sending
     * @return void
     * @throws WireException
     * @throws Exception
     */
    public function sendEmail():void
    {

        // create all placeholders including values stored inside the database
        $data = $this->createDataPlaceholder();

        // send the form data to the sender too
        if ($this->getValue($this->getID() . '-sendcopy')) {
            $this->mail->to($data[$this->getID() . '-email']);
        }

        // set the default receiver address if no custom receiver address is set
        if (!$this->custom_receiver) {
            $this->mail->to($this->receiverAddress);
        }


        //create sender string
        $sender_values = [];
        $name_values = [];
        if (array_key_exists($this->getID() . '-gender', $data)) {
            $sender_values['gender'] = $data[$this->getID() . '-gender'];
        }
        if (array_key_exists($this->getID() . '-name', $data)) {
            $name_values[] = $sender_values['name'] = $data[$this->getID() . '-name'];
        }
        if (array_key_exists($this->getID() . '-surname', $data)) {
            $name_values[] = $sender_values['surname'] = $data[$this->getID() . '-surname'];
        }

        if ($name_values) {
            // at least name or surname is present, so use the name as sender
            $sender = implode(' ', $sender_values);
        } else {
            // use email address instead of name as sender
            $sender = $data[$this->getID() . '-email'];
        }

        $this->mail->from($data[$this->getID() . '-email'], $sender);

        // create subject string
        if (!$this->mail->subject) {
            $this->mail->subject($this->_('A new message via contact form')); // set default subject string
            if (array_key_exists($this->getID() . '-subject', $data)) {
                $mail_subject = $data[$this->getID() . '-subject'];
                if ($mail_subject) {
                    $this->mail->subject($mail_subject);
                }
            }
        }


        // use HTML mail template or not
        if ($this->input_emailTemplate != 'none') {
            $this->mail->bodyHTML($this->getMailPlaceholder('allvalues'));
        } else {
            $this->mail->body($this->getMailPlaceholder('allvalues'));
        }

        $this->mail->sendAttachments($this); // for sending attachments

        if (!$this->mail->send()) {
            // output an error message that the mail could not be sent
            $this->generateEmailSentErrorAlert();
        }
    }

    /**
     * Render the form markup
     * @return string
     * @throws WireException
     * @throws Exception
     */
    public function render():string
    {
        // check if a receiver address is set
        if (!$this->receiverAddress) {
            throw new Exception("Email address for the recipient is missing, so email could not be sent.", 1);
        }

        $this->mail->sendAttachments($this);

        if ($this->___isValid()) {
            $this->sendEmail();
        }

        return parent::render();
    }

}
