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
use function ProcessWire\wirePopulateStringTags;

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

    protected array $frontendcontact_config = [];

    /**
     * array of all method names that will be called via __call() method
     * No need to create each method manually
     */
    private array $methodList = [
        'showGender',
        'requiredGender',
        'getGender',
        'showName',
        'requiredName',
        'getName',
        'showSurname',
        'requiredSurname',
        'getSurname',
        'showSubject',
        'requiredSubject',
        'getSubject',
        'showPrivacy',
        'getPrivacy',
        'showSendCopy',
        'getSendCopy',
        'getEmail',
        'getMessage',
        'getButton'
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
            $param = (isset($params[0])) ?? 0;
            switch ($startsWith) {
                case('sho'):
                    $className = str_replace('show', '', $func);
                    $fieldName = $this->generateConfigFieldname($className, 'show');
                    $this->{$fieldName} = (int)$param;
                    break;
                case('req'):
                    $className = str_replace('required', '', $func);
                    $fieldName = $this->generateConfigFieldname($className, 'required');
                    $this->{$fieldName} = (int)$params[0];
                    break;
                case('get'):
                    $propertyName = lcfirst(str_replace('get', '', $func));
                    return $this->{$propertyName};
            }
        }
        return null;
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
        $fields = array_filter($this->frontendcontact_config, function ($key) {
            return strpos($key, '_mapped');
        }, ARRAY_FILTER_USE_KEY);
        return $fields;
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
                    $method = 'get' . ucfirst($name);
                    $stored_name = 'stored_' . $name;
                    // get the type of the input field (SelectOptions, InputText,...)
                    $type = $field->getFieldtype();

                    switch ($type) {
                        case('FieldtypeText'):
                            $this->{$stored_name} = $this->user->{$field->name};
                            if($this->{$stored_name}){ // only if a value is stored inside the database
                                $this->$method()->setDefaultValue($this->{$stored_name})->setAttribute('disabled');
                            }
                            break;
                        case('FieldtypeOptions'):
                            $userField = $this->user->{$field->name};
                            $this->{$stored_name} = $userField->title;
                            if($this->{$stored_name}) { // only if a value is stored inside the database
                                $this->$method()->setDefaultValue($userField->title)->setAttribute('disabled');
                            }
                            break;
                    }
                }
            }

            //Email will always be set from the database
            $this->getEmail()->setAttribute('value', $this->user->email)->setAttribute('disabled');
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
        if ($this->frontendcontact_config['input_gender_userfield'] != 'none') {
            // remove all options first
            $this->getGender()->removeAllOptions();
            //grab all options in the page language
            $fieldtypeGender = $this->wire('fields')->get($this->frontendcontact_config['input_gender_userfield']);
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
                        $this->getGender()->addOption($value->{$title}, $value->{$title});
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
        $permanentFields = FrontendContact::$permanentFields;
        $tempFields = array_diff($formFields, $permanentFields);

        foreach ($tempFields as $temp) {
            $configFieldNameShow = $this->generateConfigFieldname($temp, 'show');
            $configFieldNameRequired = $this->generateConfigFieldname($temp, 'required');
            $fieldObjectName = lcfirst($temp);
            $field = $this->{$fieldObjectName};

            // remove the field if set to hide
            if (!$this->frontendcontact_config[$configFieldNameShow]) {
                $this->remove($field);
            } else {
                //set required status to the field
                if ($configFieldNameRequired != 'input_privacy_required') { // exclude privacy field
                    if ((array_key_exists($configFieldNameRequired, $this->frontendcontact_config)) && ($this->frontendcontact_config[$configFieldNameRequired])) {
                        if (!$field->hasRule('required')) {
                            $field->setRule('required');
                        }
                    } else {
                        if ($temp != 'Privacy') // never remove required from privacy field !!
                        {
                            $field->removeRule('required');
                        }
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
     * @return string
     */
    protected function createDataPlaceholder():string
    {
        return 'Mail Data';
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

        if (!$this->receiverAddress) {
            throw new Exception("Email address for the recipient is missing, so email could not be sent.", 1);
        }

        $excludedFieldValues = [
            'Privacy',
            'SendCopy',
            'Button'
        ]; // remove the values of these fields from the POST values

        $data_placeholder = [];
        foreach ($this->formElements as $element) {
            $formfield = $element->className();
            $propName = lcfirst($formfield);

            $inputName = 'input_' . $propName . '_show';
            bd($element);
            if (($this->$inputName) && (!in_array($formfield, ['Privacy', 'SendCopy', 'Button']))) {
                $this->setMailPlaceholder($propName . 'VALUE',
                    $this->getValue($propName)); // set the placeholder and its value
                //$data_placeholder[]['label'] = $this->getLabel($propName);
                $data_placeholder[$propName]['value'] = $this->getValue($propName);
            }
            // email
            if ($this->wire('user')->isLoggedin()) {
                $email = $this->wire('user')->email;
            } else {
                $email = $this->getValue('email') ?? $this->_('No email');
            }
            $this->setMailPlaceholder('emailvalue', $email);
            // message
            $message = $this->getValue('message') ?? $this->_('No message');
            $this->setMailPlaceholder('messagevalue', $message);
        }

        $senderData = [$this->getValue('gender'), $this->getValue('name'), $this->getValue('surname')];

        bd($data_placeholder);
        // send the form data to the sender too
        if ($this->input_sendCopy_show) {
            $sendCopyFieldName = $this->getID() . '-' . $this->sendCopy->getID();
            if (isset($this->wire('input')->post->{$sendCopyFieldName})) {
                $this->mail->to($email);
            }
        }

        // send the mail via WireMail
        $this->mail->to($this->receiverAddress);
        $this->mail->from($email, $this->getValue('name') . ' ' . $this->getValue('surname'));
        $this->mail->subject($this->getValue('subject'));
        $this->mail->bodyHTML($this->getMailData());

        if (!$this->mail->send()) // output an error message that the mail could not be sent
        {
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
        $this->useSettingsOfFormFields();
        if ($this->___isValid()) {


            // TODO runs twice
            $this->sendEmail();
        }
        return parent::render();
    }

}
