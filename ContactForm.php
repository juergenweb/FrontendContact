<?php

namespace FrontendContact;

use Exception;
use FrontendForms\Button as Button;
use FrontendForms\Email as Email;
use FrontendForms\Form as Form;
use FrontendForms\Gender as Gender;
use FrontendForms\Message as Message;
use FrontendForms\Name as Name;
use FrontendForms\Privacy as Privacy;
use FrontendForms\SendCopy as SendCopy;
use FrontendForms\Subject as Subject;
use FrontendForms\Surname as Surname;
use ProcessWire\WireException;
use function ProcessWire\wireMail;


/**
 *
 * Class to create a simple contact form
 *
 * Class and Function List:
 * Function list:
 * - __construct()
 * - to()
 * - showCopyCheckbox()
 * - showDataPrivacyCheckbox()
 * - render()
 * - __toString()
 * Classes list:
 * - ContactForm extends Form
 */
class ContactForm extends Form
{
    protected $input_default_to = ''; // the address of the recipient of the email
    protected $input_show_sendCopy = true; // show or hide send copy to me checkbox
    protected $input_show_dataprivacy = true; // show or hide data privacy checkbox
    protected $module; // object of the FrontendContact module
    protected $input_emailTemplate; // name of the email template that should be used or none

    //Form field objects
    protected $gender; //the gender field object
    protected $name; // the name field object
    protected $surname; // the surname field object
    protected $email; // the email field object
    protected $subject; // the subject field object
    protected $message; // the message field object
    protected $privacy; // the privacy field object
    protected $sendCopy; // the send copy field object
    protected $button; // the button object

    /**
     * Every form must have an id, so let's add it via the constructor
     * @throws WireException
     */
    public function __construct(string $id = 'contact-form')
    {
        parent::__construct($id);
        $this->module = $this->wire('modules')->get('FrontendContact'); // the module object
        // default settings
        $this->setMaxAttempts(5);
        $this->setMinTime(3);
        $this->setMaxTime(3600);
        // create properties of all configuration data of the module, so they can be reached via $this->module->myProperty
        foreach ($this->wire('modules')->getConfig('FrontendContact') as $key => $value) $this->$key = $value;
        $this->input_default_to = $this->module->getEmailValue();
        // Instantiate form field objects
        $genderField = $this->input_gender ?? null;
        $this->gender = new Gender($genderField);
        $this->name = new Name();
        $this->surname = new Surname();
        $this->email = new Email();
        $this->subject = new Subject();
        $this->message = new Message();
        $this->privacy = new Privacy();
        $this->sendCopy = new SendCopy();
        $this->button = new Button();
    }

    /**
     * Show or hide the box for sending a copy of the message to the own email address
     * @param bool $show - true: the box will be displayed otherwise not
     * @return $this;
     */
    public function showCopyCheckbox(bool $show = true): self
    {
        $this->input_show_sendCopy = $show;
        return $this;
    }

    /**
     * Show or hide the box for showing the data privacy checkbox
     * @param bool $show - true: the box will be displayed otherwise not
     * @return $this;
     */
    public function showDataPrivacyCheckbox(bool $show = true): self
    {
        $this->input_show_dataprivacy = $show;
        return $this;
    }

    /**
     * @return string
     * @throws WireException
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render the form markup
     * @return string
     * @throws WireException
     * @throws Exception
     */
    public function render(): string
    {
        $this->removeRequired($this->gender);
        $this->add($this->gender);
        $this->removeRequired($this->name);
        $this->add($this->name);
        $this->removeRequired($this->surname);
        $this->add($this->surname);
        $this->removeRequired($this->email);
        $this->email->setAttribute('value', $this->user->email);
        if ($this->user->email)
            $this->email->setAttribute('readonly');
        $this->add($this->email);
        $this->removeRequired($this->subject);
        $this->add($this->subject);
        $this->removeRequired($this->message);
        $this->add($this->message);
        if ($this->input_show_dataprivacy)
            $this->add($this->privacy);
        if ($this->input_show_sendCopy)
            $this->add($this->sendCopy);
        $this->add($this->button);
        if ($this->isValid()) {
            if (!$this->input_default_to)
                throw new Exception("Email address for the recipient is missing, so email could not be sent.", 1);
            // create user data for email body
            $senderData = [$this->getValue('gender'), $this->getValue('name'), $this->getValue('surname')];
            $sender = implode(' ', $senderData) ?? $this->_('Unknown sender');
            $subject = $this->getValue('subject') ?? $this->_('No subject');
            if ($this->wire('user')->isLoggedin()) $emailSender = $this->wire('user')->email; else $emailSender = $this->getValue('email') ?? $this->_('No email');
            $message = $this->getValue('message') ?? $this->_('No message');
            $body = '<p>' . $this->_('Sender') . ': ' . $sender . '</p>';
            $body .= '<p>' . $this->getFormelementByName('subject')->getLabel()->getText() . ': ' . $subject . '</p>';
            $body .= '<p>' . $this->getFormelementByName('email')->getLabel()->getText() . ': ' . $emailSender . '</p><br>';
            $body .= '<p>' . $this->getFormelementByName('message')->getLabel()->getText() . ':</p>';
            $body .= '<p>' . $message . '</p>';
            // send the form data via email
            $m = wireMail();

            if ($this->input_show_sendCopy) {
                $sendCopyFieldName = $this->getID() . '-' . $sendCopy->getID();
                if (isset($this->wire('input')->post->$sendCopyFieldName)) $m->to($emailSender);
            }
            $m->to($this->input_default_to);
            $m->from($emailSender, $this->getValue('name') . ' ' . $this->getValue('surname'));
            $m->subject($subject);
            $m->title($this->_('A new message via contact form'));
            if ($this->input_emailTemplate != 'none') $m->body($body); else $m->bodyHTML($body);
            $m->mailTemplate($this->input_emailTemplate);
            if (!$m->send())
                // output an error message that the mail could not be sent
                $this->generateEmailSentErrorAlert();
        }
        return parent::render();
    }

    /**
     * Internal method to remove the required attribute from the input field
     * @param object $inputfieldObject
     * @return void
     */
    protected function removeRequired(object $inputfieldObject): void
    {
        if (!in_array($inputfieldObject->getAttribute('name'), $this->input_required))
            $inputfieldObject->removeRule('required');
    }

    /**
     * Set the recipient for the mail
     * @param string $email
     * @return $this
     */
    public function to(string $email): self
    {
        $this->input_default_to = trim($email);
        return $this;
    }

}
