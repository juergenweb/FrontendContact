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
use FrontendForms\Phone;
use FrontendForms\Inputfields;
use FrontendForms\Message;
use FrontendForms\Name;
use FrontendForms\Privacy;
use FrontendForms\PrivacyText;
use FrontendForms\SendCopy;
use FrontendForms\Subject;
use FrontendForms\Surname;
use FrontendForms\InputCheckbox;
use ProcessWire\FrontendForms;
use ProcessWire\Page;
use ProcessWire\WireMail;
use ProcessWire\FrontendContact;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use function ProcessWire\wirePopulateStringTags;
use function ProcessWire\wireBytesStr;

class ContactForm extends Form
{

    // Default form fields
    protected Gender $gender; //the gender field object
    protected Name $name; // the name field object
    protected Surname $surname; // the surname field object
    protected Email $email; // the email field object
    protected Phone $phone; // the phone field object
    protected Subject $subject; // the subject field object
    protected Message $message; // the message field object
    protected InputCheckbox $callback; // the call-back checkbox above the phone field
    protected FileUploadMultiple $fileUploadMultiple;  // the file-upload field
    protected SendCopy $sendCopy; // send the copy field object
    protected Privacy $privacy; // the privacy field object with the checkbox
    protected PrivacyText $privacyText; // the privacy hint text only
    protected Button $button; // the button object
    protected WireMail $mail; // the WireMail object for sending mails

    // stored user values from the db for usage as form values if user is logged in
    protected string $stored_gender = '';
    protected string $stored_name = '';
    protected string $stored_surname = '';
    protected string $stored_email = '';
    protected string $stored_phone = '';
    protected bool $custom_receiver = false;
    protected string $receiverAddress = '';
    protected string|null $senderAddress = null;

    protected array $frontendcontact_config = [];

    protected FrontendForms $frontendForms;

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
        'showPhone',
        'requiredPhone',
        'showCallback',
        'showSubject',
        'requiredSubject',
        'showFileUploadMultiple',
        'showSendCopy',
        'showPrivacy'
    ];

    protected array $custom_fields = []; // contains all custom fields objects

    // map FrontendForms fields to PW fields for storage inside the database
    // define allowed field types and sanitizers
    protected array $fieldsmapping = [
        'InputWeek' => ['fieldtypes' => ['FieldtypeText' => 'text']], // week input type
        'InputTel' => ['fieldtypes' => ['FieldtypeText' => 'text']], // telephone input type
        'InputRange' => ['fieldtypes' => ['FieldtypeText' => 'text']], // range input type
        'InputUrl' => ['fieldtypes' => ['FieldtypeURL' => 'httpUrl', 'FieldtypeText' => 'text']], // url input type
        'InputColor' => ['fieldtypes' => ['FieldtypeText' => 'text']], // color input type
        'InputNumber' => ['fieldtypes' => ['FieldtypeInteger' => 'int', 'FieldtypeText' => 'text']], // integer input type
        'InputTime' => ['fieldtypes' => ['FieldtypeDatetime' => 'date', 'FieldtypeText' => 'text']], // time input type
        'InputText' => ['fieldtypes' => ['FieldtypeText' => 'text']], // text input type
        'Username' => ['fieldtypes' => ['FieldtypeText' => 'pageName']], // pre-defined input type for username
        'Phone' => ['fieldtypes' => ['FieldtypeText' => 'text']], // pre-defined input type for phone number
        'InputEmail' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypeEmail' => 'email']], // email input type
        'Textarea' => ['fieldtypes' => ['FieldtypeTextarea' => 'textarea']], // textarea input type
        'InputDate' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypeDatetime' => 'date']], // date input type
        'InputDateTime' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypeDatetime' => 'date']], // time input type
        'SelectMultiple' => ['fieldtypes' => ['FieldtypeOptions' => '']], // input type multiple select
        'Select' => ['fieldtypes' => ['FieldtypeOptions' => '']], // input type single select
        'InputCheckbox' => ['fieldtypes' => ['FieldtypeCheckbox' => 'checkbox']], // input type single checkbox
        'InputCheckboxMultiple' => ['fieldtypes' => ['FieldtypeOptions' => '']], // input type checkbox multiple
        'InputRadio' => ['fieldtypes' => ['FieldtypeOptions' => '']], // input type single radio
        'InputRadioMultiple' => ['fieldtypes' => ['FieldtypeOptions' => '']], // input type multiple radio
        'InputPassword' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypePassword' => 'text']], // input type password
        'Password' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypePassword' => 'text']], // input type password
        'PasswordConfirmation' => ['fieldtypes' => ['FieldtypeText' => 'text', 'FieldtypePassword' => 'text']], // input type password
        'InputMonth' => ['fieldtypes' => ['FieldtypeText' => 'text']], // input type month
    ];

    /**
     * @throws WireException
     * @throws Exception
     */
    public function __construct(string $id = 'contact-form')
    {

        parent::__construct($id);

        // default values for older versions, which do not contain this setting fields
        $this->frontendcontact_config['input_phone_callback'] = 0;
        $this->frontendcontact_config['input_phone_show'] = 0;
        $this->frontendcontact_config['input_phone_required'] = 0;
        $this->frontendcontact_config['input_log_submission'] = 0;
        $this->frontendcontact_config['input_sub_action'] = 0;
        $this->frontendcontact_config['input_sender_email'] = '';

        // get module configuration data from FrontendContact module and create properties of each setting
        foreach ($this->wire('modules')->getConfig('FrontendContact') as $key => $value) {
            $this->frontendcontact_config[$key] = $value;
        }

        // grab FrontendForms module to be able to use some methods of it
        $this->frontendForms = $this->wire('modules')->get('FrontendForms');

        // instantiate the WireMail class object for sending the mails
        $mailInstance = (isset($this->frontendcontact_config['input_mailmodule'])) ? $this->frontendcontact_config['input_mailmodule'] : 'none'; // fallback

        $this->mail = $this->newMailInstance($mailInstance);

        // set the title
        $this->mail->title($this->_('A new message via contact form'));

        // set the email template to the WireMail object
        $this->mail->mailTemplate($this->frontendcontact_config['input_emailTemplate']);

        // add default settings from this module to the form
        $this->setMinTime($this->frontendcontact_config['input_minTime']); // min time

        $this->receiverAddress = FrontendContact::getEmailValue($this->frontendcontact_config) ?? '';

        $this->callback = $this->callbackCheckbox(); // instantiate the callback checkbox

        // Create an instance of each form field depending on the module config
        $this->createAllFormFields();

        // create gender select options depending on a user field if a user field was mapped to it
        $this->adaptGenderSelect();

        // set mandatory fields to show and to require
        $this->frontendcontact_config['input_email_show'] = true;
        $this->frontendcontact_config['input_message_show'] = true;
        $this->frontendcontact_config['input_button_show'] = true;
        $this->frontendcontact_config['input_email_required'] = true;
        $this->frontendcontact_config['input_message_required'] = true;
        $this->frontendcontact_config['input_privacy_required'] = true;

    }

    /**
     * Remove characters that could be used for e-mail header injection (CRLF injection)
     * from a value before it is used as (part of) a mail header such as Subject,
     * From-Name or Reply-To. User-submitted form values (name, surname, subject, e-mail)
     * are otherwise passed to those headers without going through a dedicated header
     * sanitizer, so a value containing a carriage return / line feed could be used to
     * inject additional headers (e.g. an extra Bcc) into the outgoing mail.
     * @param string $value
     * @return string
     */
    protected function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    /**
     * Resolve the sender (From) address for outgoing contact form mails.
     *
     * Prefers the fixed, module-configured sender address; if that is empty, falls
     * back to noreply@<first entry of $config->httpHosts>, and only if that list is
     * also empty falls back further to the given (potentially client-supplied and
     * therefore untrusted) request host. This method contains only the decision logic
     * and takes all inputs as plain arguments (no wire() calls of its own), so it can
     * be unit tested in isolation without a running ProcessWire instance.
     * @param string $configuredSender - value of the input_sender_email module config field
     * @param string[] $httpHosts - value of $config->httpHosts (the site's own hostname whitelist)
     * @param string $requestHost - value of $config->httpHost (derived from the request, only used as a last resort)
     * @return string
     */
    protected function resolveSenderAddress(string $configuredSender, array $httpHosts, string $requestHost): string
    {
        $senderAddress = $this->sanitizeHeaderValue($configuredSender);
        if ($senderAddress !== '') {
            return $senderAddress;
        }

        $host = $httpHosts[0] ?? $requestHost;
        if ($host === 'localhost') {
            $host = 'localhost.com';
        }

        return 'noreply@' . $this->sanitizeHeaderValue($host);
    }

    /**
     * Sanitize a single field value (a custom field mapped via $fieldsmapping, or one
     * of the built-in fields - email, subject, gender, name, surname, phone, message)
     * before it is saved to the PW page in saveEmail().
     *
     * If no sanitizer name is given (empty string, as configured for FieldtypeOptions
     * fields backing select/checkbox/radio "multiple" inputs), the raw value is passed
     * through unchanged - PW's own Fieldtype::sanitizeValue() takes care of it when the
     * page is saved.
     *
     * If a (non-empty) scalar sanitizer name is given, it is meant for a single-value PW
     * fieldtype (FieldtypeText, FieldtypeEmail, FieldtypeInteger, FieldtypeDatetime,
     * FieldtypeURL, FieldtypePassword, FieldtypeCheckbox - see $fieldsmapping). An array
     * value reaching this point therefore means the submitted request supplied an array
     * for what is supposed to be a single scalar field (e.g. a manipulated
     * "fieldname[]=a&fieldname[]=b" request against a normal text input): calling a
     * scalar sanitizer (text(), email(), int(), ...) directly on an array would throw a
     * TypeError, since those sanitizers only accept a string. This is treated as invalid
     * input - an empty string is returned so the caller skips saving a value for this
     * field, rather than guessing how to collapse the array.
     * @param string $sanitizer - name of a ProcessWire sanitizer method, or '' for none
     * @param mixed $rawValue - the raw submitted value for this field
     * @return mixed
     */
    protected function sanitizeCustomFieldValue(string $sanitizer, mixed $rawValue): mixed
    {
        if (!$sanitizer) {
            return $rawValue;
        }

        if (is_array($rawValue)) {
            return '';
        }

        return $this->wire('sanitizer')->$sanitizer($rawValue);
    }

    /**
     * Create the callback checkbox object
     * @return \FrontendForms\InputCheckbox
     */
    protected function callbackCheckbox(): InputCheckbox
    {
        $callback = new InputCheckbox('callback');
        $callback->setLabel($this->_('Request a callback'));
        $callback->setDescription($this->_('If you would like us to call you back, please check this box and enter your phone number in the appearing field below.'));
        $callback->setAttribute('value', $this->_('Yes'));
        $callback->setAttribute('class', 'fc-callback');
        return $callback;
    }

    /**
     * Magic method used to set the required status, add or remove a field or to get the field object on the fly
     * The $methodList array will be used for allowed method calls
     * @param $method
     * @param $arguments
     * @return bool|mixed|object
     */
    public function __call($method, $arguments)
    {

        if (in_array($method, $this->methodList)) {
            $startsWith = substr($method, 0, 3);

            $param = (!isset($arguments[0])) ? false : $arguments[0];

            switch ($startsWith) {
                case('sho'):

                    $className = str_replace('show', '', $method);
                    $fieldName = $this->generateConfigFieldname($className, 'show');

                    $this->frontendcontact_config[$fieldName] = $param;
                    break;
                case('req'):
                    $className = str_replace('required', '', $method);
                    $fieldName = $this->generateConfigFieldname($className, 'required');
                    $this->frontendcontact_config[$fieldName] = $param;
                    break;
            }

        }
        return null;

    }

    /**
     * Alias function for the WireMail function subject() to set the subject for the mail on per-form base
     * @param string $subject
     * @return ContactForm
     */
    public function subject(string $subject): self
    {
        $m = $this->getMail();
        $m->subject($subject);
        return $this;
    }

    /**
     * Alias function for the WireMail function to() to set the receiver address for the mail on per-form base
     * @param string $email
     * @return $this
     * @throws WireException
     * @throws Exception
     */
    public function to(string $email): self
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
     * Alias function for the WireMail function from() to overwrite the sender address for the mail on per form base
     * @param string $from
     * @return $this
     */
    public function from(string $from): self
    {
        $this->senderAddress = trim($from);
        return $this;
    }

    /**
     * Get the WireMail object
     * This is needed to change email templates on per WireMail base
     * @return WireMail
     */
    public function getMail(): WireMail
    {
        return $this->mail;
    }

    /**
     * Get an array of all input fields inside the module configuration which were used to map contact
     * form fields to user template fields
     * The appendix "_mapped" will be used to identify those fields
     * @return array
     */
    protected function getMappedFields(): array
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
    protected function setMappedDataToField(): void
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
    protected function adaptGenderSelect(): void
    {

        if (($this->frontendcontact_config['input_gender_show']) && ($this->frontendcontact_config['input_gender_userfield_mapped'] != 'none')) {
            // remove all options first
            $this->getFormelementByName('gender')->removeAllOptions();
            //grab all options in the page language
            $fieldtypeGender = $this->wire('fields')->get($this->frontendcontact_config['input_gender_userfield_mapped']);
            if ($fieldtypeGender) {
                $type = $fieldtypeGender->type;
                // check if field is a type of FieldtypeOptions
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
    protected function generateConfigFieldname(string $className, string $suffix): string
    {
        $className = lcfirst($className);
        $createName = ['input', $className, $suffix];
        $createName = array_filter($createName);
        return implode('_', $createName);
    }

    /**
     * Set a field to require or not, depending on the settings in the backend or on per-form base
     * @return void
     */
    protected function setShowRequiredFields(): void
    {
        $formID = $this->getAttribute('id');
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
                    // add requiredIfNotEmpty validator to phone field if checkbox is enabled
                    if($field->getAttribute('name') === $formID.'-phone'){
                        // check if checkbox for callback is enabled
                        if($this->getFormelementByName($formID.'-callback')) {
                            $field->removeRule('required'); // if required is enabled
                            $field->setRule('requiredIfEqual', 'callback', $this->_('Yes'));
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
    protected function createAllFormFields(): void
    {

        foreach (FrontendContact::$formFields as $className) {
            $propName = lcfirst($className);
            $class = 'FrontendForms\\' . $className;

            $field = new $class(strtolower($className));
            $this->{$propName} = $field;

            if ($className === 'Phone') {

                // add the checkbox first if set
                if (array_key_exists('input_phone_callback', $this->frontendcontact_config) && ($this->frontendcontact_config['input_phone_callback'])) {

                    $this->add($this->callback);

                    // grab the description of the checkbox
                    $desc = $this->callback->getDescription();

                    $desc->hideIf([
                            'name' => $this->getID() . '-callback',
                            'operator' => 'isnotempty',
                            'value' => ''
                        ]
                    );

                    // add the data-conditional-rules attr manually
                    $conditions = json_encode($desc->getConditions());
                    $desc->setAttribute('data-conditional-rules', $conditions);

                    // add the condition to show the phone field
                    $field->showIf([
                            'name' => $this->getID() . '-callback',
                            'operator' => 'isnotempty',
                            'value' => ''
                        ]
                    );

                }

            }

            if ($className === 'FileUploadMultiple') {

                // check if a file upload max size limit is set
                if (array_key_exists('input_filemaxuploadsize', $this->frontendcontact_config) && ($this->frontendcontact_config['input_filemaxuploadsize'])) {
                    $field->setRule('allowedFileSize', $this->frontendcontact_config['input_filemaxuploadsize']);
                }

                // Allowed file types are intentionally NOT set here anymore. Restricting
                // uploads is now left to FrontendForms itself:
                // - FrontendForms' own global "Measure 9" setting (input_allowedmimetypes)
                //   automatically applies an "allowedMimeTypes" rule to every upload field.
                // - A site developer can additionally set
                //   $field->setRule('allowedFileExt', ['jpg', 'png', ...]) manually right
                //   after getForm() - this is no longer overridden here.
            }

            $this->add($field); // add every form field independent of settings to the form

        }
    }

    /**
     * Add the mapped user data to the "values-array" if set
     * @param array $values
     * @param string $mapped_field_name
     * @param string $form_field_name
     * @return array
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function addMappedValueToPlaceholders(array $values, string $mapped_field_name, string $form_field_name): array
    {
        // add the firstname from the user profile if mapped to a field
        $mappedNameField = $this->frontendcontact_config[$mapped_field_name];
        if ($mappedNameField != 'none') {
            $nameField = $this->wire('fields')->get($mappedNameField)->name;
            if ($this->wire('user')->$nameField) {
                $value = '';
                if (!is_string($this->wire('user')->$nameField)) {
                    if ($this->wire('user')->$nameField->className == 'SelectableOptionArray') {
                        $value = $this->wire('user')->$nameField->title;
                    }
                } else {
                    $value = $this->wire('user')->$nameField;
                }
                $values[$this->getID() . '-' . $form_field_name] = $value;
            }
        }

        return $values;
    }

    /**
     * Grab all POST values and put them into one string for sending it with the mail
     * @return array
     * @throws WireException
     */
    protected function createDataPlaceholder(): array
    {
        $values = $this->getValues(); // all POST values

        // check if datetime elements are inside the form
        $datetimeElements = $this->getFormElementsByClass('InputDateTime');
        if ($datetimeElements) {
            foreach ($datetimeElements as $datetimeElement) {
                $name_attribute = $datetimeElement->getAttribute('name');
                $value = $values[$name_attribute];
                // replace all letters with whitespace
                $value = str_replace(['T', 'Z'], ' ', $value);
                $values[$name_attribute] = $value;
            }
        }

        $radioElements = $this->getFormElementsByClass('InputRadio');
        if ($radioElements) {
            foreach ($radioElements as $radioElement) {
                $name_attribute = $radioElement->getAttribute('name');
                $value = $values[$name_attribute];
                // replace all letters with whitespace
                if ($value == 'on') {
                    $values[$name_attribute] = 1;
                }
            }
        }

        $fields = FrontendContact::$formFields;

        // get the position of the email field

        if ($this->wire('user')->isLoggedin()) {

            // add email if a user is logged in
            $key = array_search('Email', $fields);
            $values = array_merge(array_slice($values, 0, $key), [$this->getID() . '-email' => $this->wire('user')->email], array_slice($values, $key));

            // add the gender from the user profile if mapped to a field
            $values = $this->addMappedValueToPlaceholders($values, 'input_gender_userfield_mapped', 'gender');

            // add the firstname from the user profile if mapped to a field
            $values = $this->addMappedValueToPlaceholders($values, 'input_name_userfield_mapped', 'name');

            // add the lastname from the user profile if mapped to a field
            $values = $this->addMappedValueToPlaceholders($values, 'input_surname_userfield_mapped', 'surname');

        }

        // remove privacy and send copy values from post-array
        unset($values [$this->getID() . '-privacy']);
        unset($values [$this->getID() . '-sendcopy']);

        // When file upload is enabled in the module configuration, the "Uploaded files"
        // row must always appear in the mail body - even when no file was actually
        // submitted. Unlike the other, text-based fields, FrontendForms' own value
        // collection only adds the fileuploadmultiple key to $values when $_FILES
        // actually contains an uploaded file for it; it is simply absent (not merely
        // empty) from $values on a submission where the visitor left this optional
        // field untouched. Without this, the foreach loop below would never see the
        // key at all and would silently skip the row entirely instead of showing the
        // dash placeholder it is supposed to show for "no file uploaded".
        $fileUploadKey = $this->getID() . '-fileuploadmultiple';
        if (!empty($this->frontendcontact_config['input_fileUploadMultiple_show']) && !array_key_exists($fileUploadKey, $values)) {
            $values[$fileUploadKey] = '';
        }

        // Build the "all values" placeholder. Every submitted value is registered as its
        // own named placeholder first and only referenced inside the markup via a [[TAG]]
        // token - the token gets replaced afterwards through allSanitized(), which runs
        // each value through MarkupHTMLPurifier (or htmlspecialchars() as a fallback if
        // that module is not installed) before it is inserted. This keeps user-submitted
        // form values (name, subject, message, ...) from being embedded as raw, unescaped
        // HTML in the outgoing e-mail.
        $template = '';
        foreach ($values as $key => $value) {
            $name = str_replace($this->getID() . '-', '', $key);
            $valueTag = 'span';

            // The uploaded-files field gets special handling below: its row must only
            // appear in the mail body when file upload is actually enabled in the
            // module configuration (input_fileUploadMultiple_show) - normally this
            // field simply is not part of $values at all when the option is off, since
            // it is then never added to the form in the first place, but this check
            // keeps that an explicit, guaranteed rule rather than an implicit side
            // effect of what happens to be in $values.
            $isFileUploadField = ($name === 'fileuploadmultiple');
            if ($isFileUploadField && empty($this->frontendcontact_config['input_fileUploadMultiple_show'])) {
                continue;
            }

            if (is_array($value)) {

                // do not allow multidimensional arrays like $_FILES
                if (count($value) == count($value, COUNT_RECURSIVE)) {
                    // de-duplicate before joining - e.g. two identically-named
                    // uploaded files should be listed once in the mail body, not
                    // once per occurrence
                    $uniqueValues = array_unique($value);

                    if ($isFileUploadField) {
                        // append each uploaded file's size, e.g. "photo.jpg (2 MB)", so
                        // the recipient can see how large each attachment is without
                        // opening it.
                        //
                        // getUploadPath() is NOT where the file currently sits at this
                        // point - it is only the destination FrontendForms' own
                        // WireMail::sendAttachments() hook copies the file to
                        // afterwards, and only when the file is meant to be kept
                        // (input_sub_action requests saving as a page); in mail-only
                        // mode the original is deleted right after sending and never
                        // reaches getUploadPath() at all. createDataPlaceholder() runs
                        // before sendAttachments() though, so the file still exists at
                        // its original location - which getUploadedFiles() (the same
                        // method saveEmail() already uses elsewhere in this class)
                        // returns as full, real paths. Build a filename => real path
                        // lookup from that instead of guessing the path ourselves.
                        $uploadedFilePaths = [];
                        foreach ($this->getUploadedFiles() as $uploadedPath) {
                            $uploadedFilePaths[pathinfo($uploadedPath, PATHINFO_BASENAME)] = $uploadedPath;
                        }

                        $uniqueValues = array_map(function (string $filename) use ($uploadedFilePaths): string {
                            $filePath = $uploadedFilePaths[$filename] ?? null;
                            // filesize() can return false (e.g. permission issues) even
                            // when is_file() is true - guard against passing that false
                            // into wireBytesStr(), which expects an int (this file has
                            // strict_types=1 set).
                            $fileSize = ($filePath !== null && is_file($filePath)) ? @filesize($filePath) : false;
                            if ($fileSize !== false) {
                                $filename .= ' (' . wireBytesStr($fileSize) . ')';
                            }
                            return $filename;
                        }, $uniqueValues);
                    }

                    $value = implode(', ', $uniqueValues);
                } else {
                    $value = '';
                }

            } else {
                if ($name === 'message') $valueTag = 'div';
            }

            if ((string)($value ?? '') === '') {
                // show a dash instead of leaving the row blank when a value is empty -
                // makes an intentionally empty optional field (not just the
                // uploaded-files row) read clearly as "nothing submitted" rather than
                // looking like a rendering glitch in the mail body.
                $value = '-';
            }

            // register the value under its own placeholder name - sanitized further below
            $this->setMailPlaceholder($key, (string)($value ?? ''));

            if ($isFileUploadField) {
                // Always use this dedicated, translatable label instead of the form
                // field's own configured label, so the mail body reliably reads
                // "Uploaded files" regardless of whatever label an editor may have set
                // on the underlying FrontendForms field itself.
                $label = $this->_('Uploaded files');
            } else {
                $label = $this->getMailPlaceholders()[strtoupper($name . 'label')] ?? $name;
            }
            $template .= '<div id="' . $key . '" class="bodypart"><span class="label">' . $label . '</span>: <' . $valueTag . ' class="value">[[' . strtoupper($key) . ']]</' . $valueTag . '></div>';
        }
        // add IP address as last value
        $ipPlaceholderName = $this->getID() . '-ip';
        $this->setMailPlaceholder($ipPlaceholderName, $this->wire('session')->getIP());
        $template .= '<div id="ip" class="bodypart"><span class="label">' . $this->_('IP') . '</span>: <span class="value">[[' . strtoupper($ipPlaceholderName) . ']]</span></div>';

        // replace all [[..]] tokens with their sanitized values
        $placeholder = wirePopulateStringTags(
            $template,
            $this->mailPlaceholders->allSanitized(),
            ['tagOpen' => '[[', 'tagClose' => ']]']
        );

        $this->setMailPlaceholder('allvalues', $placeholder);

        return $values;
    }

    /**
     * Save a custom form field value to a pw_field
     * @param string $form_field_name
     * @param string $pw_field_name
     * @return self
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     * @throws \Exception
     */
    public function saveField(string $form_field_name, string $pw_field_name): self
    {
        $form_field_name = trim($form_field_name);
        $pw_field_name = $this->wire('sanitizer')->fieldName($pw_field_name);

        $pw_field = $this->wire('fields')->get($pw_field_name);

        if ($pw_field) {

            $form_field = $this->getFormelementByName($form_field_name);
            if ($form_field) {

                $pw_field_type = substr(strrchr(get_class($pw_field->type), '\\'), 1);
                $form_field_type = substr(strrchr(get_class($form_field), '\\'), 1);

                // check if this FrontendForms input type is supported for storage in the database
                if (!array_key_exists($form_field_type, $this->fieldsmapping)) {
                    // throw an exception message
                    throw new Exception(sprintf($this->_('The input field type of the class %s is not supported to save the value inside a custom field. Please take a look at the docs of FrontendContact which input types are supported for storage in the database.'), '"' . $form_field_type . '"'));
                }

                // check if this FrontendForms input can be linked to the given PW fieldtype
                if (!array_key_exists($pw_field_type, $this->fieldsmapping[$form_field_type]['fieldtypes'])) {
                    $allowedTypes = implode(', ', array_keys($this->fieldsmapping[$form_field_type]['fieldtypes']));
                    // throw an exception message
                    throw new Exception(sprintf($this->_('The input field type of the class %s cannot be stored in a PW field of the type %s. Allowed types are: %s'), '"' . $form_field_type . '"', '"' . $pw_field_type . '"', $allowedTypes));
                }

                // check if this field is part of the contact form page template
                $template = $this->wire('templates')->get('frontend-contact-message');
                if ($template && $template->hasField($pw_field_name)) {
                    // add the field to the custom fields array
                    $this->custom_fields[$form_field_name] = $pw_field;
                }
            }
        }
        return $this;
    }

    /**
     * Method to send the email
     * This method set a placeholder variable to each form value, and it includes email (body and mail) templates
     * before sending
     * @param int $pageID
     * @return void
     * @throws \ProcessWire\WireException
     */
    public function sendEmail(int $pageID): void
    {
        // set the upload path to the newly created page id
        $new_upload_path = $this->wire('config')->paths->assets . 'files/' . $pageID . '/';

        $this->setUploadPath($new_upload_path);

        // create all placeholders including values stored inside the database
        $data = $this->createDataPlaceholder();

        // send the form data to the sender too
        if ($this->getValue('sendcopy') && $this->getValue('email')) {
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

        // Set from value depending on settings
        if (!array_key_exists('input_mailmodule', $this->frontendcontact_config)) {
            $this->frontendcontact_config['input_mailmodule'] = 'none';
        }

        $this->mail->replyTo($this->sanitizeHeaderValue((string) $data[$this->getID() . '-email']));
        $this->mail->fromName($this->sanitizeHeaderValue((string) $sender));

        // create subject string
        if (!$this->mail->subject) {
            $this->mail->subject($this->_('A new message via contact form')); // set default subject string
            if (array_key_exists($this->getID() . '-subject', $data)) {
                $mail_subject = $this->sanitizeHeaderValue((string) $data[$this->getID() . '-subject']);
                if ($mail_subject) {
                    $this->mail->subject($mail_subject);
                }
            }
        }

        // Add the HTML body property to the Mail object.
        //
        // NOTE: this used to branch on $this->frontendcontact_config['input_emailtype'],
        // which looks like it was meant to switch between an HTML body and a plain-text
        // body. That field, however, does not control HTML vs. plain text at all - it only
        // selects where the recipient address comes from ('text' = entered manually,
        // 'pwfield' = taken from a PW field), so its value is never 'none' and the
        // condition was always true. The else branch (setting a plain $this->mail->body())
        // was therefore dead code - and it would have been wrong regardless, because
        // FrontendForms' template/placeholder pipeline (Form::renderTemplate() /
        // MailTemplateRenderer) only ever reads from and rewrites $mail->bodyHTML, never
        // $mail->body. Setting the plain body property instead of using Form::setBody()
        // would have bypassed that pipeline entirely, leaving the mail with an empty
        // bodyHTML and a "plain text" body that still contained raw, un-stripped HTML
        // markup (the <div>/<span> wrapper markup built in createDataPlaceholder()).
        // Form::setBody() is therefore always the correct call here.
        Form::setBody($this->mail, $this->getMailPlaceholder('allvalues'), $this->frontendcontact_config['input_mailmodule']);

        $keep = false;
        if (($this->frontendcontact_config['input_sub_action'] == 1 || $this->frontendcontact_config['input_sub_action'] == 2)) {
            $keep = true;
        }

        $this->mail->sendAttachments($this, $keep); // for sending attachments

        // Set the sender (From) address: prefer the fixed, module-configured sender
        // address (input_sender_email); if none is set, fall back to
        // noreply@<first configured httpHost>. $config->httpHosts is the site's own
        // whitelist of allowed hostnames (set in site/config.php) - unlike the raw,
        // client-supplied $config->httpHost value used previously, httpHosts[0] is
        // always a value the site owner controls, never something a spoofed Host
        // header can influence. A fixed sender address configured in the module is
        // still the recommended option, since it also decouples the From-address
        // from how the site happens to be accessed and keeps it aligned with SPF/DKIM.
        // The actual decision logic lives in resolveSenderAddress() (no wire() calls of
        // its own), so it can be unit tested without a running ProcessWire instance.
        $senderAddress = $this->resolveSenderAddress(
            (string) $this->frontendcontact_config['input_sender_email'],
            (array) $this->wire('config')->httpHosts,
            (string) $this->wire('config')->httpHost
        );
        $this->mail->from($senderAddress);

        if (!$this->mail->send()) {
            // output an error message that the mail could not be sent
            $this->generateEmailSentErrorAlert();
        } else {

            if ($this->frontendcontact_config['input_log_submission']) {
                // write a log entry for the successful submission
                $ip = $this->wire('session')->getIP();
                $email = $data[$this->getID() . '-email'];
                $log_entry = $email . ' [IP: ' . $ip . ']';
                $this->wire('log')->log($log_entry, ['name' => 'successful-submissions-frontendcontact']);
            }
        }
    }


    /**
     * Function to save the email as a page inside the admin tree
     * @return int
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    protected function saveEmail(): int
    {
        // for safety... check first if the parent page exists
        $frontendcontact_page = $this->wire('pages')->get('template=admin, name=frontend-contact');

        if ($frontendcontact_page->id != 0) {
            // safe the email as a new page under the parent

            // get the form data that has been submitted
            $data = $this->createDataPlaceholder();

            // grab all uploaded files if there are some
            //
            // Same array-type-confusion class of bug as the fields handled via
            // sanitizeCustomFieldValue() above: FrontendForms' FormValueStore::getValues()
            // is documented/expected to return an array of filenames for a "multiple file
            // upload" field, but when the field is present on the form and no file was
            // actually submitted for it, it falls through to its single-file branch
            // instead and returns a plain string (typically an empty string) - not an
            // array. Without this guard, array_filter($uploaded_filenames) further below
            // throws a TypeError (uncaught, page never gets saved) on every ordinary
            // submission that simply leaves an optional multi-file upload field empty.
            $uploaded_filenames = [];
            if (array_key_exists($this->getID() . '-fileuploadmultiple', $data)) {
                $uploaded_filenames = $data[$this->getID() . '-fileuploadmultiple']; // array of all uploaded filenames
                if (!is_array($uploaded_filenames)) {
                    $uploaded_filenames = [];
                }
            }

            // create a new Page instance
            $p = new Page();

            // set the template and parent (required)
            $p->template = $this->wire('templates')->get('frontend-contact-message');
            $p->parent = $this->wire('pages')->get($frontendcontact_page->id);

            // populate the page's fields with sanitized data
            // the page will sanitize its own data, but this way no assumptions are made
            //
            // Every sanitizer call below goes through sanitizeCustomFieldValue() - the
            // same array-guard used for the custom_fields loop further down - instead of
            // calling the PW sanitizer directly. These built-in fields are just as
            // reachable with a manipulated array value (e.g. a "contact-form-name[]=a&
            // contact-form-name[]=b" request against a normal text input) as the mapped
            // custom fields are, and PW's scalar sanitizers (text(), email(), textarea())
            // throw a TypeError on an array argument just the same.
            $p->fcontact_email = $this->sanitizeCustomFieldValue('email', $data[$this->getID() . '-email']);
            if (array_key_exists($this->getID() . '-subject', $data)) {
                $title_value = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-subject']);
            }
            $title_value = $title_value ?? sprintf($this->_('Contact message from %s'), $p->fcontact_email);

            $p->title = $title_value;
            $p->name = $this->wire('sanitizer')->pageName($p->title . '-' . time());
            if (array_key_exists($this->getID() . '-gender', $data))
                $p->fcontact_gender = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-gender']);
            if (array_key_exists($this->getID() . '-name', $data))
                $p->fcontact_firstname = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-name']);
            if (array_key_exists($this->getID() . '-surname', $data))
                $p->fcontact_lastname = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-surname']);
            if (array_key_exists($this->getID() . '-phone', $data))
                $p->fcontact_phone = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-phone']);
            if (array_key_exists($this->getID() . '-subject', $data))
                $p->fcontact_subject = $this->sanitizeCustomFieldValue('text', $data[$this->getID() . '-subject']);
            if (array_key_exists($this->getID() . '-message', $data))
                $p->fcontact_message = $this->sanitizeCustomFieldValue('textarea', $data[$this->getID() . '-message']);

            // check for extra fields
            if ($this->custom_fields) {

                foreach ($this->custom_fields as $form_field_name => $pw_custom_field) {

                    // get type of FrontendForms field
                    $form_field = $this->getFormelementByName($form_field_name);
                    if ($form_field) {

                        $form_field_type = substr(strrchr(get_class($form_field), '\\'), 1);
                        $pw_field_type = substr(strrchr(get_class($pw_custom_field->type), '\\'), 1);
                        $fields_mapping = $this->fieldsmapping;

                        // check if the field type exists in array keys
                        if (array_key_exists($form_field_type, $fields_mapping)) {

                            // check if this form field can be mapped to the given PW field
                            if (array_key_exists($pw_field_type, $fields_mapping[$form_field_type]['fieldtypes'])) {

                                // grab the sanitizer for the given fieldtype
                                $sanitizer = $fields_mapping[$form_field_type]['fieldtypes'][$pw_field_type];

                                // normalize form field name by adding form ID as prefix if not present
                                if (!str_starts_with($form_field_name, $this->getID())) {
                                    $form_field_name = $this->getID() . '-' . $form_field_name;
                                }

                                if (array_key_exists($form_field_name, $data)) {

                                    // decision logic (array-guard) lives in sanitizeCustomFieldValue() so it
                                    // can be unit tested in isolation - see its docblock for the rationale
                                    $field_value = $this->sanitizeCustomFieldValue($sanitizer, $data[$form_field_name]);

                                    if ($field_value) {

                                        // finally, save the value to the database
                                        $pw_field_name = $pw_custom_field->name;
                                        $p->$pw_field_name = $field_value;

                                    }

                                }

                            }

                        }

                    }

                }

            }
            if ($p->save()) {

                // run only if "save as page" or is selected
                if ($this->frontendcontact_config['input_sub_action'] == 1) {

                    // get all uploaded files including their path inside the site/assets/files dir
                    $fileNames = $this->getUploadedFiles();

                    if ($fileNames) {

                        // store all uploaded files in the database and copy them to newly created page folder
                        $p->of(false);
                        $new_upload_path = $this->wire('config')->paths->assets . 'files/' . $p->id . '/';
                        $this->setUploadPath($new_upload_path);

                        // first copy all files to the new page folder
                        foreach ($fileNames as $path) {
                            // copy each file to the new page folder
                            $this->wire('files')->copy($path, $this->getUploadPath());
                            // remove it from the old folder
                            $this->wire('files')->unlink($path);
                        }

                    }
                }

                // after that save them in the database
                //
                // save() is called once after the loop, not once per file - add() only
                // adds each file to the in-memory Pagefiles collection, so persisting
                // after every single iteration meant N uploaded files triggered N
                // redundant page saves instead of one.
                if (array_filter($uploaded_filenames)) {
                    foreach ($uploaded_filenames as $name) {
                        $path = $this->getUploadPath() . $name;
                        $p->fcontact_files->add($path);
                    }
                    $p->save('fcontact_files');
                }

                return $p->id;
            }
        }
        return 0;
    }

    /**
     * Render the form markup
     * @return string
     * @throws WireException
     * @throws Exception
     */

    public function render(): string
    {

        $phoneField = $this->getFormelementByName('phone');
        // remove the callback checkbox depending on the settings
        if ((!$this->frontendcontact_config['input_phone_callback']) || (!$this->frontendcontact_config['input_phone_show'])) {
            $this->remove($this->callback);
            // remove the custom wrapper from the phone field
            $phoneField->useCustomWrapper(false);
        } else {

            // add special data-attributes to the callback checkbox
            $this->callback->setAttribute('data-phone-id', $this->getID() . '-' . $phoneField->getID());
            $this->callback->setAttribute('data-callbackdesc-id', $this->getID() . '-' . $this->callback->getID() . '-desc');
            // add an id to the description of the callback checkbox for JavaScript manipulation (hiding)
            $this->callback->getDescription()->setAttribute('id', $this->getID() . '-' . $this->callback->getID() . '-desc');

        }
        // remove the privacy field object depending on the configuration settings
        switch ($this->frontendcontact_config['input_privacy_show']) {
            case(1): // checkbox has been selected
                // remove PrivacyText element
                $this->remove($this->privacyText);
                break;
            case(2): // text only has been selected
                // remove Privacy element
                $this->remove($this->privacy);
                break;
            default: // show none of them has been selected
                // remove both
                $this->remove($this->privacyText);
                $this->remove($this->privacy);
        }

        // check if a receiver address is set, otherwise display a warning
        if (!$this->receiverAddress) {
            $alert = new Alert();
            $alert->setText($this->_('Email address for the recipient is missing, so the form will not be displayed.'));
            $alert->setCSSClass('alert_warningClass');
            return $alert->render();
        }

        // set required status do fields
        $this->setShowRequiredFields();

        // map user data as value to the form fields if a user is logged in
        $this->setMappedDataToField();

        if ($this->___isValid()) {

            switch ($this->frontendcontact_config['input_sub_action']) {
                case(0):
                    $this->sendEmail(0);
                    break;
                case(1):
                    $this->saveEmail();
                    break;
                case(2):
                    $newID = $this->saveEmail();
                    if ($newID != 0) {
                        $this->sendEmail($newID);
                    }

                    break;
            }

        }


        return parent::render();

    }


}
