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

### Now you can select the default email address from all email values from the same email field as the default receiver email address

In all previous versions of this module, it was possible to select a certain PW email field, where the value of this field was taken into account for sending emails to this email address.

But there was a problem: An email field can contain multiple email addresses and not only once. Until now, the first one in line was taken as the default email address and all the mails will be sent to this email address. All the other email addresses stored in the database could not be selected.

In version 1.3.0 this problem has been solved. Now you can select from all email values on all pages. So there is no limit any longer.

Go to the module configuration and take a look, which email addresses can be selected. Each option will be displayed in the following syntax:

myemail@exaple.com[Page title: Home, Page-ID: 1, Fieldname: myField, Fieldtype: FieldtypeEmail]

- myemail@exaple.com: This is the email address value, where the mails will be sent to
- Page title: Home: This shows you the title of the page, where the email address is stored
- Fieldname: myField: This is the name of the email field
- Fieldtype: FieldtypeEmail: This shows you that this field is of the type FieldtypeEmail

  All this information helps you to identify the email address, that you want to use as the default receiver address for this module.

## 2 new contact form fields have been added

### Phone number field

Now you can add a field for entering a phone number to the contact form

### Request a callback field

This is a checkbox field, which can be added to the form too. If you add this field to the form, following happens:

- the phone number field is no longer visible by default. It appears after the checkbox has been checked.
- the phone number field is always required, if the checkbox is checked.

You will find the new configuration fields inside the module configuration.

