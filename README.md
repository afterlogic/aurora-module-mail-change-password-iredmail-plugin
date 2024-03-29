# aurora-module-mail-change-password-iredmail-plugin

Allows users to change passwords on their email accounts hosted by [iRedMail](http://www.iredmail.org/) mail server.

How to install a module (taking WebMail Lite as an example of the product built on Aurora framework): [Adding modules in WebMail Lite](https://afterlogic.com/docs/webmail-lite-8/installation/adding-modules)

In `data/settings/modules/MailChangePasswordIredmailPlugin.config.json` file, you need to supply array of mailserver hostnames or IP addresses the feature is enabled for. If you put "*" item there, it means the feature is enabled for all accounts.

In the same file, you need to provide MySQL credentials used by iRedMail to access the database - this may be either **root** account, or **vmailadmin** user created by iRedMail when setting up the product.

# Development
This repository has a pre-commit hook. To make it work you need to configure git to use the particular hooks folder.

`git config --local core.hooksPath .githooks/`

# License
This module is licensed under AGPLv3 license if free version of the product is used or Afterlogic Software License if commercial version of the product was purchased.
