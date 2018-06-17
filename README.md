# PHPStan exception rules

[![Build Status](https://travis-ci.org/pepakriz/phpstan-exception-rules.svg)](https://travis-ci.org/pepakriz/phpstan-exception-rules)
[![Latest Stable Version](https://poser.pugx.org/pepakriz/phpstan-exception-rules/v/stable)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)
[![License](https://poser.pugx.org/pepakriz/phpstan-exception-rules/license)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)

* [PHPStan](https://github.com/phpstan/phpstan)

This extension provides following rules and features:

* Require `@throws` annotation when some checked exception is thrown ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/throws-annotations.php))
	* Skip ignored exceptions which have checked parent
	* Exception propagation over function calls
* Ignore caught checked exceptions ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/try-catch.php))
* Unnecessary `@throws` annotation detection ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/unused-throws.php))
* `@throws` annotation variance validation ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/throws-inheritance.php))

Features and rules provided by PHPStan core (we rely on):

* `@throws` annotation must contain only valid `Throwable` types
* Thrown value must be subclass of `Throwable`

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
	exceptionRules:
		checkedExceptions:
			- RuntimeException
		ignoredExceptions:
			- LogicException
```
