<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UnusedDescriptiveThrows;

use LogicException;
use LogicException as LogicExceptionAlias;
use RuntimeException;
use RuntimeException as RuntimeExceptionAlias;

class FooException extends RuntimeException {}
class BarException extends RuntimeException {}

/**
 * @throws RuntimeException
 * @throws RuntimeException Description.
 */
function unusedAnnotation() { // error: Unused @throws RuntimeException annotation
}

/**
 * @throws RuntimeExceptionAlias
 * @throws RuntimeException Description.
 */
function unusedAnnotationAlias() { // error: Unused @throws RuntimeException annotation
}

/**
 * @throws FooException
 * @throws BarException Description
 */
function unusedDescriptiveAnnotation(): void // error: Unused @throws Pepakriz\PHPStanExceptionRules\Rules\UnusedDescriptiveThrows\BarException annotation
{
	throwFooExceptions();
}

/**
 * @throws FooException
 */
function throwFooExceptions(): void
{
	throw new FooException();
}

/**
 * @throws LogicException Description.
 */
function descriptiveAnnotation(): void
{
	throw new LogicException();
}

/**
 * @throws LogicExceptionAlias Description.
 */
function descriptiveAnnotationAlias(): void
{
	throw new LogicException();
}

class UnusedThrows
{

	/**
	 * @throws RuntimeException
	 * @throws RuntimeException Description.
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
	 * @throws RuntimeExceptionAlias
	 * @throws RuntimeException Description.
	 */
	public function unusedAnnotationAlias(): void // error: Unused @throws RuntimeException annotation
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
	public function unusedBarAnnotation(): void // error: Unused @throws Pepakriz\PHPStanExceptionRules\Rules\UnusedDescriptiveThrows\BarException annotation
	{
		$this->throwFooExceptions();
	}

	/**
	 * @throws FooException
	 * @throws BarException Description
	 */
	public function unusedDescriptiveAnnotation(): void // error: Unused @throws Pepakriz\PHPStanExceptionRules\Rules\UnusedDescriptiveThrows\BarException annotation
	{
		$this->throwFooExceptions();
	}

	/**
	 * @throws LogicException Description.
	 */
	public function descriptiveAnnotation(): void
	{
		throw new LogicException();
	}

	/**
	 * @throws LogicExceptionAlias Description.
	 */
	public function descriptiveAnnotationAlias(): void
	{
		throw new LogicException();
	}

	/**
	 * @throws LogicException
	 */
	public function unusedLogic(): void // error: Unused @throws LogicException annotation
	{
		throw new LogicException();
	}

}

new class {

	/**
	 * @throws RuntimeException Overridden description.
	 */
	public function descriptiveAnnotationAlias(): void
	{
		throw new RuntimeException();
	}

};
