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
         * Alias function for the WireMail function subject() to set the subject for the mail on per-form base
         * @param string $string
         * @return ContactForm
         */
        public function subject(string $string): self
        {
            $m = $this->getMail();
            $m->subject($string);
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
        protected function createAllFormFields(): void
        {

            foreach (FrontendContact::$formFields as $className) {
                $propName = lcfirst($className);
                $class = 'FrontendForms\\' . $className;

                $field = new $class(strtolower($className));
                $this->{$propName} = $field;

                if ($className === 'Subject') {
                    $field->hideIf([
                        'name' => $this->getID() . '-gender', // name of the select field
                        'operator' => 'is', // this is the operator
                        'value' => 'Frau' // the value must not be empty to show the firstname field
                    ]);
                }

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
                        $desc->setAttribute('data-conditional-rules', htmlspecialchars($conditions));

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
                        // add rule for max upload file size
                        $field->setRule('allowedFileSize', $this->frontendcontact_config['input_filemaxuploadsize']);
                    }
                    //check if message should be save as a page
                    if ($this->frontendcontact_config['input_sub_action'] > 0) {
                        // add extension restrictions to it
                        $fileupload_field = $this->wire('fields')->get('fcontact_files');
                        if ($fileupload_field->extensions) {
                            $allowed_extensions = explode(' ', $fileupload_field->extensions);
                            $field->setRule('allowedFileExt', $allowed_extensions);
                        }
                    }

                }

                $this->add($field); // add every form field independent of settings to the form

            }
        }

        /**
         * Add the mapped user data to the values array if set
         * @param array $values
         * @param array $fields
         * @param string $mapped_field_name
         * @param string $field_name
         * @param string $form_field_name
         * @return array
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function addMappedValueToPlaceholders(array $values, array $fields, string $mapped_field_name, string $field_name, string $form_field_name): array
        {
            // add the firstname from the user profile if mapped to a field
            $mappedNameField = $this->frontendcontact_config[$mapped_field_name];
            if ($mappedNameField != 'none') {
                $nameField = $this->wire('fields')->get($mappedNameField)->name;
                if ($this->wire('user')->$nameField) {
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
            $values = $this->getValues();
            $fields = FrontendContact::$formFields;

            // get the position of the email field

            if ($this->wire('user')->isLoggedin()) {

                // add email if user is logged in
                $key = array_search('Email', $fields);
                $values = array_merge(array_slice($values, 0, $key), [$this->getID() . '-email' => $this->wire('user')->email], array_slice($values, $key));

                // add the gender from the user profile if mapped to a field
                $values = $this->addMappedValueToPlaceholders($values, $fields, 'input_gender_userfield_mapped', 'Gender', 'gender');

                // add the firstname from the user profile if mapped to a field
                $values = $this->addMappedValueToPlaceholders($values, $fields, 'input_name_userfield_mapped', 'Name', 'name');

                // add the lastname from the user profile if mapped to a field
                $values = $this->addMappedValueToPlaceholders($values, $fields, 'input_surname_userfield_mapped', 'Surname', 'surname');

            }

            // remove privacy and send copy values from post-array
            unset($values [$this->getID() . '-privacy']);
            unset($values [$this->getID() . '-sendcopy']);

            // create an extra placeholder containing all post-values called all placeholders
            $placeholder = '';
            foreach ($values as $key => $value) {
                $name = str_replace($this->getID() . '-', '', $key);
                // do not allow array values ($_FILES)
                if (is_string($value)) {
                    $valueTag = ($name === 'message') ? 'div' : 'span';
                    $placeholder .= '<div id="' . $key . '" class="bodypart"><span class="label">' . $this->getMailPlaceholders()[strtoupper($name . 'label')] . '</span>: <' . $valueTag . ' class="value">' . $value . '</' . $valueTag . '></div>';
                }
            }
            // add IP address as last value
            $placeholder .= '<div id="ip" class="bodypart"><span class="label">' . $this->_('IP') . '</label>: <span class="value">' . $this->wire('session')->getIP() . '</span></div>';

            $this->setMailPlaceholder('allvalues', $placeholder);

            return $values;
        }

        /**
         * Method to send the email
         * This method set a placeholder variable to each form value, and it includes email (body and mail) templates
         * before sending
         * @return void
         * @throws WireException
         * @throws Exception
         */
        public function sendEmail(int $pageID): void
        {
            // set the upload path to the newly created page id
            $new_upload_path = $this->wire('config')->paths->assets . 'files/' . $pageID . '/';

            $this->setUploadPath($new_upload_path);

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

            // Set from value depending on settings
            if (!array_key_exists('input_mailmodule', $this->frontendcontact_config)) {
                $this->frontendcontact_config['input_mailmodule'] = 'none';
            }

            switch ($this->frontendcontact_config['input_mailmodule']) {
                case('WireMailSmtp'):
                    $senderName = $sender ?? $data[$this->getID() . '-email'];
                    $this->mail->fromName($senderName);
                    break;
                default:
                    if ($this->senderAddress === null) {
                        $this->mail->from($data[$this->getID() . '-email'], $sender);
                    } else {
                        $this->mail->from($this->senderAddress);
                    }
            }

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
            if ($this->get('input_emailTemplate') != 'none') {
                // Add the HTML body property to the Mail object
                Form::setBody($this->mail, $this->getMailPlaceholder('allvalues'), $this->frontendcontact_config['input_mailmodule']);
            } else {
                $this->mail->body($this->getMailPlaceholder('allvalues'));
            }

            $keep = false;
            if (($this->frontendcontact_config['input_sub_action'] == 1 || $this->frontendcontact_config['input_sub_action'] == 2)) {
                $keep = true;
            }

            $this->mail->sendAttachments($this, $keep); // for sending attachments

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
         * @return void
         */
        protected function saveEmail(): int
        {
            // for safety... check first if the parent page exists
            $frontendcontact_page = $this->wire('pages')->get('template=admin, name=frontend-contact');

            if ($frontendcontact_page->id != 0) {
                // safe the email as a new page under the parent

                // get the form data that has been submitted
                $data = $this->createDataPlaceholder();

                // grab all uploaded files if there are one
                $uploaded_filenames = [];
                if (array_key_exists($this->getID() . '-fileuploadmultiple', $data)) {
                    $uploaded_filenames = $data[$this->getID() . '-fileuploadmultiple']; // array of all uploaded filenames
                }

                // create a new Page instance
                $p = new \ProcessWire\Page();

                // set the template and parent (required)
                $p->template = $this->wire('templates')->get('frontend-contact-message');
                $p->parent = $this->wire('pages')->get($frontendcontact_page->id);

                // populate the page's fields with sanitized data
                // the page will sanitize it's own data, but this way no assumptions are made
                $p->fcontact_email = $this->wire('sanitizer')->email($data[$this->getID() . '-email']);
                if (array_key_exists($this->getID() . '-subject', $data)) {
                    $title_value = $this->wire('sanitizer')->text($data[$this->getID() . '-subject']);
                }
                $title_value = $title_value ? $title_value : sprintf($this->_('Contact message from %s'), $p->fcontact_email);;

                $p->title = $title_value;
                $p->name = $this->wire('sanitizer')->pageName($p->title . '-' . time());
                if (array_key_exists($this->getID() . '-gender', $data))
                    $p->fcontact_gender = $this->wire('sanitizer')->text($data[$this->getID() . '-gender']);
                if (array_key_exists($this->getID() . '-name', $data))
                    $p->fcontact_firstname = $this->wire('sanitizer')->text($data[$this->getID() . '-name']);
                if (array_key_exists($this->getID() . '-surname', $data))
                    $p->fcontact_lastname = $this->wire('sanitizer')->text($data[$this->getID() . '-surname']);
                if (array_key_exists($this->getID() . '-phone', $data))
                    $p->fcontact_phone = $this->wire('sanitizer')->text($data[$this->getID() . '-phone']);
                if (array_key_exists($this->getID() . '-subject', $data))
                    $p->fcontact_subject = $this->wire('sanitizer')->text($data[$this->getID() . '-subject']);
                if (array_key_exists($this->getID() . '-message', $data))
                    $p->fcontact_message = $this->wire('sanitizer')->textarea($data[$this->getID() . '-message']);

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
                    if ($uploaded_filenames) {
                        foreach ($uploaded_filenames as $name) {
                            $path = $this->getUploadPath() . $name;
                            $p->fcontact_files->add($path);
                            $p->save('fcontact_files');
                        }
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
                // add an id to the description of the callback checkbox for Javascript manipulation (hiding)
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
                return $alert->___render();
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
