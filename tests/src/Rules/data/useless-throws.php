<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UselessThrows;

use RuntimeException;

class FooException extends RuntimeException {}

/**
 * @throws RuntimeException
 * @throws RuntimeException
 */
function duplicatedAnnotation(): void // error: Useless @throws RuntimeException annotation
{
	throw new FooException();
}

trait UselessThrowsTrait
{

	/**
	 * @throws RuntimeException
	 * @throws RuntimeException
	 */
	public function duplicatedAnnotationInTrait(): void // error: Useless @throws RuntimeException annotation
	{
		throw new FooException();
	}

}

class UselessThrows
{

	use UselessThrowsTrait;

	/**
	 * @throws RuntimeException
	 * @throws RuntimeException
	 */
	public function duplicatedAnnotation(): void // error: Useless @throws RuntimeException annotation
	{
		throw new FooException();
	}

	/**
	 * @throws RuntimeException
	 * @throws RuntimeException Because of ...
	 */
	public function duplicatedWithDescription(): void // error: Useless @throws RuntimeException annotation
	{
		throw new FooException();
	}

	/**
	 * @throws RuntimeException
	 * @throws FooException
	 */
	public function inheritedAnnotation(): void // error: Useless @throws Pepakriz\PHPStanExceptionRules\Rules\UselessThrows\FooException annotation
	{
		throw new FooException();
	}

	/**
	 * @throws RuntimeException
	 * @throws FooException Because of ...
	 */
	public function inheritedWithDescription(): void
	{
		throw new FooException();
	}

}
