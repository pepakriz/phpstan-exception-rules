<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UnusedThrows;

use RuntimeException;

class FooException extends RuntimeException {}
class BarException extends RuntimeException {}

class UnusedThrows
{

	/**
	 * @throws RuntimeException
	 */
	public function correctAnnotation(): void
	{
		throw new RuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function unusedAnnotation(): void // error: Unused @throws RuntimeException annotation
	{

	}

	/**
	 * @throws FooException
	 */
	private function throwFooExceptions(): void
	{
		throw new FooException();
	}

	/**
	 * @throws FooException
	 * @throws BarException
	 */
	public function unusedBarAnnotation(): void // error: Unused @throws Pepakriz\PHPStanExceptionRules\Rules\UnusedThrows\BarException annotation
	{
		$this->throwFooExceptions();
	}

}

interface IgnoreOnInterface
{

	/**
	 * @throws RuntimeException
	 */
	public function fooMethod();

}

abstract class IgnoreOnAbstractMethod
{

	/**
	 * @throws RuntimeException
	 */
	abstract public function fooMethod();

	/**
	 * @throws RuntimeException
	 */
	public function unusedAnnotation(): void // error: Unused @throws RuntimeException annotation
	{

	}

}
