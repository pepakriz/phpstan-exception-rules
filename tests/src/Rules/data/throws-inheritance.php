<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\Inheritance;

use LogicException;
use RuntimeException;

class BaseException extends RuntimeException {};
class ConcreteException extends BaseException {};
class SecondConcreteException extends BaseException {};

class BaseThrowsAnnotations
{

	/**
	 * @throws BaseException
	 */
	public function correct(): void
	{

	}

	/**
	 * @throws LogicException
	 */
	public function correctWithLogicException(): void
	{

	}

	/**
	 * @throws ConcreteException
	 */
	public function wrong(): void
	{

	}

	public function __construct()
	{

	}

	public function parentWithoutThrows(): void
	{

	}

}

class ThrowsAnnotations extends BaseThrowsAnnotations
{

	/**
	 * @throws ConcreteException
	 */
	public function methodWithoutParent(): void
	{

	}

	/**
	 * @throws ConcreteException
	 * @throws SecondConcreteException
	 */
	public function correct(): void
	{

	}

	/**
	 * @throws ConcreteException
	 */
	public function correctWithLogicException(): void
	{

	}

	/**
	 * @throws BaseException
	 */
	public function wrong(): void // error: PHPDoc tag @throws with type Pepakriz\PHPStanExceptionRules\Rules\Data\Inheritance\BaseException is not compatible with parent Pepakriz\PHPStanExceptionRules\Rules\Data\Inheritance\ConcreteException
	{

	}

	/**
	 * @throws BaseException
	 */
	public function __construct()
	{

	}

	/**
	 * @throws BaseException
	 */
	public function parentWithoutThrows(): void // error: PHPDoc tag @throws with type Pepakriz\PHPStanExceptionRules\Rules\Data\Inheritance\BaseException is not compatible with parent
	{

	}

}
