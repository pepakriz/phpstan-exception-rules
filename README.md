# PHPStan exception rules

[![Build Status](https://travis-ci.org/pepakriz/phpstan-exception-rules.svg)](https://travis-ci.org/pepakriz/phpstan-exception-rules)
[![Latest Stable Version](https://poser.pugx.org/pepakriz/phpstan-exception-rules/v/stable)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)
[![License](https://poser.pugx.org/pepakriz/phpstan-exception-rules/license)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)

* [PHPStan](https://github.com/phpstan/phpstan)
* [Nette Framework](https://nette.org/)

This extension provides following rules and features:

* Require `@throws` annotation when some whitelisted exception is thrown
* Ignore caught whitelisted exceptions
* Ignore blacklisted exceptions which have whitelisted parent
* Thrown value must be instanceof `Throwable`
* `@throws` annotation must contain only valid `Throwable` objects

In future we will provide some next amazing features as:

* Exception propagation over function calls
* Unnecessary `@throws` annotation detection

## Usage

To use this extension, require it in [Composer](https://getcomposer.org/):

```bash
composer require --dev pepakriz/phpstan-exception-rules
```

And include and configure extension.neon in your project's PHPStan config:

```yaml
includes:
	- vendor/pepakriz/phpstan-exception-rules/extension.neon

parameters:
	annotatedExceptions:
		whitelist:
			- RuntimeException
		blacklist: []
```
