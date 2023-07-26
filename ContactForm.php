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
use FrontendForms\Alert;
use FrontendForms\Button;
use FrontendForms\Email;
use FrontendForms\FileUploadMultiple;
use FrontendForms\Form;
use FrontendForms\Gender;
use FrontendForms\Inputfields;
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
    protected FileUploadMultiple $fileUploadMultiple;  // the file-upload field
    protected SendCopy $sendCopy; // send copy field object
    protected Button $button; // the button object
    protected WireMail $mail; // the WireMail object for sending mails

    // stored user values from the db for usage as form values if user is logged in
    protected string $stored_gender = '';
    protected string $stored_name = '';
    protected string $stored_surname = '';
    protected string $stored_email = '';
    protected bool $custom_receiver = false;
    protected string $receiverAddress = '';

    protected array $frontendcontact_config = [];

    /**
     * array of all method names that will be called via __call() method
     * No need to create each method manually ;-)
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
     * @throws Exception
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
                $field = $this->wire('fields')->get($this->frontendcontact_config['input_defaultPWField_to']);
                $database = $this->wire('database');
                if(FrontendContact::getPWEmail($field, $database)){
                    $this->receiverAddress = FrontendContact::getPWEmail($field, $database);
                }
                break;
        }

        // Create an instance of each form field depending on the module config
        $this->createAllFormFields();

        // create gender select options depending on a user field if a user field was mapped to it
        $this->adaptGenderSelect();

        // set mandatory fields to show and to required
        $this->frontendcontact_config['input_email_show'] = true;
        $this->frontendcontact_config['input_message_show'] = true;
        $this->frontendcontact_config['input_button_show'] = true;
        $this->frontendcontact_config['input_email_required'] = true;
        $this->frontendcontact_config['input_message_required'] = true;
        $this->frontendcontact_config['input_privacy_required'] = true;

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
     * @return ContactForm
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
     * @throws Exception
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
    protected function setMappedDataToField():void
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
                                    $formfield->setAttribute('value', $this->{$stored_name});
                                }
                            }
                            break;
                        case('FieldtypeOptions'):
                            $userField = $this->user->{$field->name};
                            if ($userField->title) { // only if a value is stored inside the database
                                $formfield = $this->getFormElementByName($name);
                                if ($formfield) {
                                    $formfield->setDefaultValue($userField->title);
                                    $formfield->setAttribute('disabled');
                                }
                            }
                            break;
                    }
                }
            }
            //Email will always be set from the database
            $this->getFormElementByName('email')->setAttribute('value', $this->user->email)->setAttribute('disabled');
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
     * Set a field to required or not, depending on the settings in the backend or on per form base
     * @return void
     */
    protected function setShowRequiredFields():void
    {

        foreach ($this->getFormElements() as $field) {

            $configFieldNameRequired = $this->generateConfigFieldname($field->className(), 'required');
            $configFieldNameShow = $this->generateConfigFieldname($field->className(), 'show');

            // add field to form if config is set to show and field is not part of the formElements array at the moment
            if ((array_key_exists($configFieldNameShow,
                        $this->frontendcontact_config) && ($this->frontendcontact_config[$configFieldNameShow])) || (!array_key_exists($configFieldNameShow,
                    $this->frontendcontact_config))) {
                if ($field instanceof Inputfields) {

                    // run only on pre-defined fields
                    if (array_key_exists($configFieldNameShow, $this->frontendcontact_config)) {
                        if ((isset($this->frontendcontact_config[$configFieldNameRequired])) && ($this->frontendcontact_config[$configFieldNameRequired])) {
                            $field->setRule('required');
                        } else {
                            $field->removeRule('required');
                        }
                    }
                }
            } else {
                // remove the field from the form
                $this->remove($field);
            }
        }
    }

    /**
     * Create an instance of each default form field and add this instance depending on config settings
     * to the formElements array
     * @return void
     * @throws Exception
     */
    protected function createAllFormFields():void
    {
        foreach (FrontendContact::$formFields as $className) {
            $propName = lcfirst($className);
            $class = 'FrontendForms\\' . $className;
            $this->{$propName} = new $class(strtolower($className));
            $this->add($this->{$propName}); // add every form field independent of settings to the form
        }
    }

    /**
     * Grab all POST values and put them into one string for sending it with the mail
     * @return array
     * @throws WireException
     */
    protected function createDataPlaceholder():array
    {
        $values = $this->getValues();
        // remove privacy and send copy values from post array
        unset($values [$this->getID() . '-privacy']);
        unset($values [$this->getID() . '-sendcopy']);

        // create an extra placeholder containing all post values called all placeholders
        $placeholder = '';
        foreach ($values as $key => $value) {
            $name = str_replace($this->getID() . '-', '', $key);
            // do not allow array values ($_FILES)
            if (is_string($value)) {
                $placeholder .= '<div id="' . $key . '"><span class="label">' . $this->getMailPlaceholders()[strtoupper($name . 'label')] . '</label>: <span class="value">' . $value . '</span></div>';
            }
        }
        // add IP address as last value
        $placeholder .= '<div id="ip"><span class="label">' . $this->_('IP') . '</label>: <span class="value">' . $this->wire('session')->getIP() . '</span></div>';

        $this->setMailPlaceholder('allvalues', $placeholder);
        return $values;
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
        if ($this->getValue('sendcopy')) {
            $this->mail->to($this->getValue('email'));
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
        // check if a receiver address is set, otherwise display a warning
        if (!$this->receiverAddress) {
            $alert = new Alert();
            $alert->setText($this->_('Email address for the recipient is missing, so the form will not be displayed.'));
            $alert->setCSSClass('alert_warningClass');
            return $alert->render();
        }

        // set required status do fields
        $this->setShowRequiredFields();

        // map user data as value to the form fields if user is logged in
        $this->setMappedDataToField();

        // send attachments
        $this->mail->sendAttachments($this);

        if ($this->___isValid()) {
            $this->sendEmail();
        }

        return parent::render();
    }

}
