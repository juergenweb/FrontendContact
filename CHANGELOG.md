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

