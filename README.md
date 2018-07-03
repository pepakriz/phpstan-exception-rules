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
* Unreachable catch statements
	* exception has been caught in some previous catch statement ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/unreachable-catches.php))
	* checked exception is never thrown in the corresponding try block ([examples](https://github.com/pepakriz/phpstan-exception-rules/blob/master/tests/src/Rules/data/unused-catches.php))

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
```

## Motivation

There are 2 types of exceptions:

1) Safety-checks that something should never happen (you should never call some method in some case etc.). We call these [**LogicException**](http://php.net/manual/en/class.logicexception.php) and if they are thrown, programmer did something wrong. For that reason, it is important that this exception is never caught and kills the application. Also, it is important to write good descriptive message of what went wrong and how to fix it - that is why every LogicException must have a message. Therefore, inheriting LogicException does not make much sense. Also, LogicException should never be `@throws` annotation (see below).
2) Special cases in business logic which should be handled by application and error cases that just may happen no matter how hard we try (e.g. HTTP request may fail). These exceptions we called [**RuntimeException**](http://php.net/manual/en/class.runtimeexception.php) or maybe better "checked exception". All these exceptions should be checked. Therefore it must be either caught or written in `@throws` annotation. Also if you call an method with that annotation and do not catch the exception, you must propagate it in your `@throws` annotation. This, of course, may spread quickly. When this exception is handled (caught), it is important for programmer to immediately know what case is handled and therefore all used RuntimeExceptions are inherited from some parent and have very descriptive class name (so that you can see it in catch construct) - for example `CannotCloseAccountWithPositiveBalanceException`. The message is not that important since you should always catch these exceptions somewhere, but in our case we often use that message in API output and display it to end-user, so please use something informative for users in that cases (you can pass custom arguments to constructor (e.g. entities) to provide better message). Sometimes you can meet a place where you know that some exception will never be thrown - in this case you can catch it and wrap to LogicException (because when it is thrown, it is a programmer's fault).

It is always a good idea to wrap previous exception so that we do not lose information of what really happened in some logs.

```php
// no throws annotation
public function decide(int $arg): void
{
	switch ($arg) {
		case self::ONE:
			$this->decided()
		case self::TWO:
			$this->decidedDifferently()
		default:
			throw new LogicException("Decision cannot be made for argument $arg because of ...");
	}
}

/**
 * @return mixed[]
 *
 * @throws PrintJobFailedException
 */
private function sendRequest(Request $request): array
{
	try {
		$response = $this->httpClient->send($request);
		return Json::decode((string) $response->getBody(), Json::FORCE_ARRAY);

	} catch (GuzzleException | JsonException $e) {
		throw new PrintJobFailedException($e);
	}
}

class PrintJobFailedException extends RuntimeException
{

	public function __construct(Throwable $previous)
	{
		parent::__construct('Printing failed, remote printing service is down. Please try again later', $previous);
	}

}
```

## Known limitations

1) Anonymous functions are analyzed at the same place they are declared

False positive when a method does not execute declared function:

```php
/**
 * @throws FooRuntimeException false positive
 */
public function createFnFoo(int $arg): callable
{
	return function () {
		throw new FooRuntimeException();
	};
}
```

But most of use-cases just works:

```php
/**
 * @param string[] $rows
 * @return string[]
 *
 * @throws EmptyLineException
 */
public function normalizeRows(array $rows): array
{
	return array_map(function (string $row): string {
		$row = trim($row);
		if ($row === '') {
			throw new EmptyLineException();
		}

		return $row;
	}, $rows);
}
```

2) `Catch` statement does not know about runtime subtypes

Runtime exception is absorbed:

```php
// @throws phpdoc is not required
public function methodWithoutThrowsPhpDoc(): void
{
	try {
		throw new RuntimeException();
		$this->dangerousCall();

	} catch (Throwable $e) {
		throw $e;
	}
}
```

As a workaround you could use custom catch statement:

```php
/**
 * @throws RuntimeException
 */
public function methodWithThrowsPhpDoc(): void
{
	try {
		throw new RuntimeException();
		$this->dangerousCall();

	} catch (RuntimeException $e) {
		throw $e;
	} catch (Throwable $e) {
		throw $e;
	}
}
```
