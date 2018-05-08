# PHPStan exception rules

[![Build Status](https://travis-ci.org/pepakriz/phpstan-exception-rules.svg)](https://travis-ci.org/pepakriz/phpstan-exception-rules)
[![Latest Stable Version](https://poser.pugx.org/pepakriz/phpstan-exception-rules/v/stable)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)
[![License](https://poser.pugx.org/pepakriz/phpstan-exception-rules/license)](https://packagist.org/packages/pepakriz/phpstan-exception-rules)


## Installation

```bash
composer require --dev pepakriz/phpstan-exception-rules
```

## Configuration

```neon
includes:
	- vendor/pepakriz/phpstan-exception-rules/extension.neon
parameters:
	annotatedExceptions:
		whitelist:
			- RuntimeException
		blacklist: []
```
