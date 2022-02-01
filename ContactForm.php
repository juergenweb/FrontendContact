<?php
namespace FrontendContact;

use \FrontendForms\Form as Form;
use \FrontendForms\Gender as Gender;
use \FrontendForms\Name as Name;
use \FrontendForms\Surname as Surname;
use \FrontendForms\Email as Email;
use \FrontendForms\Subject as Subject;
use \FrontendForms\Message as Message;
use \FrontendForms\Button as Button;
use \FrontendForms\Privacy as Privacy;
use \FrontendForms\SendCopy as SendCopy;
use \ProcessWire\Wire as Wire;
use \ProcessWire\User as User;
use function \ProcessWire\wireMail;

/**
* Class to create a simple contact form
*/

/**
 * Class and Function List:
 * Function list:
 * - __construct()
 * - to()
 * - getData()
 * - showCopyCheckbox()
 * - showDataPrivacyCheckbox()
 * - render()
 * - __toString()
 * Classes list:
 * - ContactForm extends Form
 */

class ContactForm extends Form {

  protected $input_default_to = ''; // the address of the recipient of the email
  protected $input_show_sendCopy = true; // show or hide send copy to me checkbox
  protected $input_show_dataprivacy = true; // show or hide data privacy checkbox
  protected $module; // object of the FrontendContact module

  /**
  * Every form must have an id, so lets add it via the constructor
  */
  public function __construct(string $id = 'contact-form')
  {
    parent::__construct($id);

    $this->module =  $this->wire('modules')->get('FrontendContact');

    // default settings
    $this->setMaxAttempts(5);
    $this->setMinTime(3);
    $this->setMaxTime(3600);

    // create properties of all configuration data of the module
    foreach($this->getData() as $key => $value) $this->$key = $value;

    // set value from email field as default value if present
    if($this->module->getValueFromEmailField())
      $this->input_default_to = $this->module->getValueFromEmailField();

  }


  /**
  * Set the recipient for the mail
  * @param string $email
  */
  public function to(string $email)
  {
    $this->input_default_to = trim($email);
  }

  /**
  * Get the configuration data from the module configuration
  * @return array
  */
  protected function getData(): array
  {
    return array_merge($this->module->getDefaultData(), $this->wire('modules')->getConfig('FrontendContact'));
  }


  /**
  * Show or hide the box for sending a copy of the message to the own email address
  * @param bool $show - true: the box will be displayed otherwise not
  * @return $this;
  */
  public function showCopyCheckbox(bool $show = true)
  {
    $this->input_show_sendCopy = $show;
    return $this;
  }


  /**
  * Show or hide the box for showing the data privacy checkbox
  * @param bool $show - true: the box will be displayed otherwise not
  * @return $this;
  */
  public function showDataPrivacyCheckbox(bool $show = true)
  {
    $this->input_show_dataprivacy = $show;
    return $this;
  }

  /**
  * Render the form markup
  * @return string
  */
  public function render(): string
  {

    $gender = new Gender();
    $this->add($gender);

    $name = new Name();
    $this->add($name);

    $surname = new Surname();
    $this->add($surname);

    $emailSender = new Email();
    $emailSender->setAttribute('value', $this->user->email);
    if($this->user->email)
      $emailSender->setAttribute('readonly');
    $this->add($emailSender);

    $subject = new Subject();
    $this->add($subject);

    $message = new Message();
    $this->add($message);

    if($this->input_show_dataprivacy){
      $privacy = new Privacy();
      $this->add($privacy);
    }

    if($this->input_show_sendCopy){
      $sendCopy = new SendCopy();
      $this->add($sendCopy);
    }

    $button = new Button();
    $this->add($button);

    if($this->isValid()){
      if(!$this->input_default_to)
        throw new \Exception("Email address for the recipient is missing, so email could not be sent.", 1);

      // create user data for email body
      $sender = $this->getValue('gender').' '.$this->getValue('name').' '.$this->getValue('surname');
      $subject = $this->getValue('subject');
      if($this->user->email){
        $emailSender = $this->user->email;
      } else {
        $emailSender = $this->getValue('email');
      }
      $message = $this->getValue('message');

      $body = '<p>'.$this->_('Sender').': '.$sender.'</p>';
      $body .= '<p>'.$this->getFormelementByName('subject')->getLabel()->getText().': '.$subject.'</p>';
      $body .= '<p>'.$this->getFormelementByName('email')->getLabel()->getText().': '.$emailSender.'</p><br>';
      $body .= '<p>'.$this->getFormelementByName('message')->getLabel()->getText().':</p>';
      $body .= '<p>'.$message.'</p>';

      // send the form data via email
      $m = wireMail();

      $m->to($this->input_default_to);
      if(($this->input_show_sendCopy) && ($this->getValue('sendcopy')))
        $m->to($emailSender);
      $m->from($emailSender, $this->getValue('name').' '.$this->getValue('surname'));
      $m->subject($subject);
      $m->title($this->_('A new message via contact form'));
      if($this->input_emailTemplate != 'none'){
        $m->body($body);
      } else {
        $m->bodyHTML($body);
      }

      $m->mailTemplate($this->input_emailTemplate);

      if($m->send()){
        // fe save the email as a page or something else...
        // nothing planned at the moment
      } else {
        // output an error message that the mail could not be sent
        $this->generateEmailSentErrorAlert();
      }

    }

    return parent::render();

  }


  public function __toString()
  {
    return $this->render();
  }

}
