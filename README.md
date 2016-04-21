<a href="https://aimeos.org/">
    <img src="https://aimeos.org/fileadmin/template/icons/logo.png" alt="Aimeos logo" title="Aimeos" align="right" height="60" />
</a>

Aimeos payment extension
========================

[![Build Status](https://travis-ci.org/aimeos/ai-payments.svg?branch=master)](https://travis-ci.org/aimeos/ai-payments)
[![Coverage Status](https://coveralls.io/repos/aimeos/ai-payments/badge.svg?branch=master&service=github)](https://coveralls.io/github/aimeos/ai-payments?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/aimeos/ai-payments/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/aimeos/ai-payments/?branch=master)
[![HHVM Status](http://hhvm.h4cc.de/badge/aimeos/ai-payments.svg)](http://hhvm.h4cc.de/package/aimeos/ai-payments)

Aimeos extension for additional payment methods and their service provider.
Some of them may have beta quality and improvements or contributions are always welcome!

**Tip:** There's also [commercial support](https://aimeos.com/support/) available if you need help to get a specific provider working.

## Table of contents

- [Installation](#installation)
- [Configuration](#configuration)
- [License](#license)
- [Links](#links)

## Installation

As every Aimeos extension, the easiest way is to install it via [composer](https://getcomposer.org/). If you don't have composer installed yet, you can execute this string on the command line to download it:
```
php -r "readfile('https://getcomposer.org/installer');" | php -- --filename=composer
```

Add the cache extension name to the "require" section of your ```composer.json``` (or your ```composer.aimeos.json```, depending on what is available) file:
```
"require": [
    "aimeos/ai-payments": "dev-master",
    ...
],
```
You should use a stable release if you don't want to add code or improve the implementation. The available stable versions can be found on [Packagist](https://packagist.org/packages/aimeos/ai-payments).

Afterwards you only need to execute the composer update command on the command line:
```
composer update
```

If your composer file is named "composer.aimeos.json", you must use this:
```
COMPOSER=composer.aimeos.json composer update
```

These commands will install the Aimeos extension into the extension directory and it will be available immediately.

## Configuration

Payment options are configured via the shop administration interface in the ["Service" tab](https://aimeos.org/docs/User_Manual/Administration_Interface/Service_list) and you can add as many payment options as you need to the list for each site. They will be shown on the payment page in the checkout process. In the detail view of a new payment option, you have to enter some values:

![Aimeos payment detail view](https://aimeos.org/docs/images/Admin-backend-service-detail-payment.png)

Make sure you set the status to "enabled" and the type to "Payment". Use an unique code for the payment option, idealy it should be readable and consist only of characters a-z, 0-9 and a few special characters like "-", "_" or ".". The value for the field "Provider" must be the last part of the class name of the payment service provider. Each of the following sections will tell you how it must be named. The last input field influences the position of the payment option within the list of payment options and you should use zero for the top position and greater values for the next payment options.

In the right side of the panel you can add the configuration settings that are specifically required for each payment provider. The list of available settings for each payment provider can be found in the [service documentation](https://aimeos.org/docs/User_Manual/Administration_Interface/Service_list#Supported_by_ai-payments).

## License

The Aimeos payments extension is licensed under the terms of the LGPLv3 Open Source license and is available for free.

## Links

* [Web site](https://aimeos.org/)
* [Documentation](https://aimeos.org/docs)
* [Help](https://aimeos.org/help)
* [Issue tracker](https://github.com/aimeos/ai-payments/issues)
* [Source code](https://github.com/aimeos/ai-payments)
