<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceInterfaces;

use RuntimeException;

class BaseException extends RuntimeException {};
class ConcreteException extends BaseException {};

interface BaseThrowsAnnotations
{

	/**
	 * @throws BaseException
	 */
	public function correct(): void;

	/**
	 * @throws ConcreteException
	 */
	public function wrong(): void;

}

interface ThrowsAnnotations extends BaseThrowsAnnotations
{

	/**
	 * @throws ConcreteException
	 */
	public function correct(): void;

	/**
	 * @throws BaseException
	 */
	public function wrong(): void; // error: PHPDoc tag @throws with type Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceInterfaces\BaseException is not compatible with parent Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceInterfaces\ConcreteException

}

class Implementation implements BaseThrowsAnnotations
{

	/**
	 * @throws ConcreteException
	 */
	public function correct(): void
	{

	}

	/**
	 * @throws BaseException
	 */
	public function wrong(): void // error: PHPDoc tag @throws with type Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceInterfaces\BaseException is not compatible with parent Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceInterfaces\ConcreteException
	{

	}

}
