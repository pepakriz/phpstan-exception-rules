<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceOverriding;

use RuntimeException;

class ConcreteException extends RuntimeException {};

abstract class BaseThrowsAnnotations
{

	public function foo(): void
	{

	}

	/**
	 * @throws RuntimeException
	 */
	public function bar(): void
	{

	}

}

class ThrowsAnnotations extends BaseThrowsAnnotations
{

	/**
	 * @throws RuntimeException
	 */
	public function foo(): void
	{

	}

	/**
	 * @throws ConcreteException
	 */
	public function bar(): void
	{

	}

}
