# MX Mandrill

Mandrill mailer adapter for ExpressionEngine.

## Installation
* Download the latest version of MX Mandrill and extract the .zip to your desktop.
* Copy *inline* to */system/user/addons/*

## Compatibility	

* ExpressionEngine 4
* ExpressionEngine 5
* ExpressionEngine 6


## Configuration

**api key** 

[Mandrill API Key](https://mandrillapp.com/settings)

## Configuration Overrides

**Main configuration file**

The main configuration file, found at system/user/config/config.php, is loaded every time the system is run, meaning that config overrides set in config.php always affect the system’s configuration.

	$config['mx_mandrill'] = [
	        'enable'   => true,
	        'mandrill_api_key'  => ''
	];


## Support Policy
This is Communite Edition add-on.

## Contributing To MX Mandrill for ExpressionEngine

Your participation to MX Mandrill development is very welcome!

You may participate in the following ways:

* [Report issues](https://github.com/MaxLazar/mx-mandrill/issues)
* Fix issues, develop features, write/polish documentation
Before you start, please adopt an existing issue (labelled with "ready for adoption") or start a new one to avoid duplicated efforts.
Please submit a merge request after you finish development.

# Thanks to

* [A PHP email parser](https://mail-mime-parser.org/)

### License

The MX Mandrill is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
