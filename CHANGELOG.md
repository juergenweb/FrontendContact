# Change Log
All notable changes to this project will be documented in this file.

## [1.0.1] - 2023-06-08

### A lot of different updates concerning issues and improving some features
- Preparing the code to run without problems on PHP 8.2
- Simplify some code 
- Fix some bugs
- Improve the site wide warning, if no default email address is set
- Update language file

## [1.2.0] - 2024-01-18

Support for [Postmark mail service](https://postmarkapp.com/) added.

There are 2 ProcessWire modules in the modules directory which implement the Postmark service in Processwire:

- [WireMailPostmark](https://processwire.com/modules/wire-mail-postmark/) by Netcarver
- [WireMailPostmarkApp](https://processwire.com/modules/wire-mail-postmark-app/) by NB Communication

Both modules do pretty the same, only the module configuration is slightly different.

If you have installed one of them, you will be able to send mails from this module via the Postmark service.

I have added a new field to the module configuration which let you select, if you want to send the mails of this module via the Postmark service or not.

Please note: This new field is only visible if you have installed one of the modules mentionend above. If not, you will not see this new field.

I have planned to make this module working with other 3rd party mail service modules too, but for now I have only implemented and tested Postmark.

## [1.2.1] - 2024-01-19

As the next step, support for an additional mailing module added: [WireMailSmtp](https://processwire.com/modules/wire-mail-smtp/) by Horst.

## [1.2.2] - 2024-01-21

Support for sending mails with the [WireMailPHPMailer](https://processwire.com/modules/altivebirit/) module added.

## [1.3.0] - 2024-04-20

This update comes with some major changes, so please test if the contact form sends correctly after upgrade. This new version needs the latest version of FrontendForms (2.1.68) to work. If you have a lower version installed, please update to the latest version. Otherwise you are not able to download this version of FrontendContact. 

If you are using the German translation, that comes with this module, do not forget to update this as well, because there are a lot of new translation strings in this version.

### Now you can select the default email address from all email values from the same email field as the default receiver email address

In all previous versions of this module, it was possible to select a certain PW email field, where the value of this field was taken into account for sending emails to this email address.

But there was a problem: An email field can contain multiple email addresses and not only one. Until now, the first one in line (in the database) was taken as the default email address and all the mails will be sent to this email address. All the other email addresses stored in the database could not be selected.

In version 1.3.0 this problem has been solved. Now you can select from all email values on all pages. So there is no limit any longer.

Go to the module configuration and take a look, which email addresses can be selected. Each option will be displayed in the following syntax:

myemail@exaple.com[Page title: Home, Page-ID: 1, Fieldname: myField, Fieldtype: FieldtypeEmail]

- myemail@exaple.com: This is the email address value, where the mails will be sent to
- Page title: Home: This shows you the title of the page, where the email address is stored (comes from)
- Fieldname: myField: This is the name of the email field
- Fieldtype: FieldtypeEmail: This shows you that this field is of the type FieldtypeEmail

All this information should help you to identify the email address, that you want to use as the default receiver address for this module.

## 2 new contact form fields have been added

### Phone number field

Now you can add a field for entering a phone number to the contact form.

### Request a callback field

This is a checkbox field, which can be added to the form too in combination with the phone number field. If you add this field to the form, following happens:

- the phone number field is no longer visible by default. It appears after the checkbox has been checked.
- the phone number field is always required, if the checkbox is checked.

You will find the new configuration fields inside the module configuration.

## [1.3.1] - 2024-06-13

I have discovered a wrong field name for the default email address -> this leads to that the module complains about a missing default email address. This writing mistake has been corrected now.

## [1.3.2] - 2024-07-04

If a user is logged in and sends an email, the email address has not been added to the mail, because it will not be added to the POST data (email field is disabled in this case). This has been fixed now.

## [1.3.3] - 2024-07-12

Re-written for new Inputfield dependencies for the phone field (requires FrontendForms >= 2.2.5).
If set, the phone field will only be visible if the checkbox for "request a callback" will be checked. Otherwise the phone field is hidden.

## [1.3.4] - 2024-10-06

Check for user permission "profile-edit" added to the method getUserFields(), to prevent display of error message on module initialization if user has not "profile-edit" permission. Thanks to Christian for reporting this issue.

## [1.3.5] 2024-10-27

- **Support for RockLanguage added**

If you have installed the [RockLanguage](https://processwire.com/modules/rock-language/) module by Bernhard Baumrock, this module now supports the sync of the language files. This means that you do not have to take care about new translations after you have downloaded a new version of FrontendContact. All new translations (at the moment only German translations) will be synced with your your ProcessWire language files. 

Please note: The sync will only take place if you are logged in as Superuser and $config->debug is set to true (take a look at the [docs](https://www.baumrock.com/en/processwire/modules/rocklanguage/docs/)).

The (old) CSV files usage is still supported.

- **Logging of successful form submissions added**

A new checkbox field in the module configuration offers you the possibility to log successfull form submissions if you want. Everytime a form has been be sent successfully, a log entry containing the sender email address and the IP adress (not the content of the message) will be written to a log file called "successful-submissions-frontendcontact".
You can use this information to take a look how many mails have been sent, so it is only an additional source of information.

## [1.3.6] 2024-11-09

- **Saving mails as pages too**

According to a user request in the forum by Flashmaster82 [https://processwire.com/talk/topic/28442-frontendcontact-module-for-creating-one-or-more-contact-forms-on-the-frontend/?do=findComment&comment=235364], you have now the possibility to select which action should be taken after the form has been validated successfull:

- send as mail (this is the default behavior)
- save email as a page only (this is new and saves the message only as a page without sending it as an mail)
- save email as a page and send as email (this is also new and combines both possibilities)

Read more about this new feature [here](https://github.com/juergenweb/FrontendContact/tree/main?tab=readme-ov-file#save-messages-as-pages).

- **New module configuration added**

A new configuration field to limit the file size of uploaded files globally added. Can be overwritten on each form via adding the validation rule "allowedFileSize" to the file upload field.

## [1.3.7] 2024-11-19

- **New module FrontendContact Manager added**

Read more about this new module [here](https://github.com/juergenweb/FrontendContact/blob/main/README.md#extra-module-frontendcontact-manager)

- **Bug on checking $_Files array for checking of uploaded files fixed**

There was a problem of checking for an empty $_Files array, because this array always contains at least one array key and this leads to it that the array is never empty. This has been fixed now by cleaning the array with the array_filter function.

## [1.3.8] 2024-11-24

- **Support for saving custom fields value inside the database added**

If you want to save the mails as pages too, it is now possible to the data of custom fields in the database. [Read more](https://github.com/juergenweb/FrontendContact?tab=readme-ov-file#save-custom-fields-in-database-too)

## [1.3.9] 2024-12-17

- **Test-code removed**

A code for testing was accidentally inside the ContactForm.php. This code has been removed now.

## [1.3.10] 2024-12-18

- **Image picker select added**

The default input select field for selecting the email template has been replaced by a nice image picker select like in FrontendForms.


## [1.3.11] 2025-02-01

- **Prepared for new hookings**

The module needs to be updated to work with the FrontendForms version 2.2.28. This version includes a lot of changes to make a lot of method hookable.

## [1.3.12] 2025-05-02

- **Bug on email type validation fixed**

According to the [issue](https://processwire.com/talk/topic/28442-frontendcontact-module-for-creating-one-or-more-contact-forms-on-the-frontend/?do=findComment&comment=248662) as posted in the forum by Claus, this bug has been fixed now

## [1.3.13] 2025-08-12

- **Bug on selection email address of a stored user fixed**
  
  There was a problem on selecting the email address of a user stored in the database as the receiver of the mails. This is fixed now.
  
- **Bug on showing message after putting it to trash fixed**
  
  After a message (email) was deleted, it was still visible in the contact manager list, even if it was in the trash. This has been fixed now.

## [1.3.14] 2025-08-25

- **Use email address containing the domain as sender email address**
  
  Related to a problem of sending emails from a shared host as written in the support forum [here](https://processwire.com/talk/topic/31416-problem-sending-mails-on-a-shared-host-using-the-wiremail-class-solved/), I have changed the sender email address to noreply@mydomain.com, where mydomain.com will be replaced by the current domain name. This prevents mails from beeing not sent on some share hosts.

## [1.3.15] 2025-12-18

- **New validation rule added to the phone field**
  
Now, if you enable the diesplay of the checkbox for the "callback", then the phone field will be required automatically, if the checkbox is checked. This can be done by using the new "requiredIfEqual" validator. The HTML5 required attribute will be added/removed via JS depending on the checkbox status.


