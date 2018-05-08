<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\ThrowsPhpDoc;

use RuntimeException;

class InvalidException {}

class ThrowsAnnotationsClass
{

	/**
	 * @throws RuntimeException
	 */
	public function correctAnnotations(): void
	{

	}

	/**
	 * @throws InvalidException
	 */
	public function invalidClass(): void // error: @throws phpdoc type must be instanceof Throwable. Pepakriz\PHPStanExceptionRules\Rules\ThrowsPhpDoc\InvalidException is given.
	{

	}

	/**
	 * @throws RuntimeException|InvalidException
	 */
	public function validAndInvalid(): void // error: @throws phpdoc type must be instanceof Throwable. Pepakriz\PHPStanExceptionRules\Rules\ThrowsPhpDoc\InvalidException|RuntimeException is given.
	{

	}

}
