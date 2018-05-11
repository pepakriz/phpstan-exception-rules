<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data;

use Exception;
use LogicException;
use RuntimeException;

class CustomLogicException extends LogicException {}
abstract class BaseRuntimeException extends RuntimeException {}
abstract class BaseBlacklistedRuntimeException extends BaseRuntimeException {}
class SomeRuntimeException extends BaseRuntimeException {}
class NextRuntimeException extends BaseRuntimeException {}
class SomeUncheckedRuntimeException extends BaseRuntimeException {}
class SomeInheritedUncheckedRuntimeException extends BaseBlacklistedRuntimeException {}
class CheckedException extends Exception {}

class ThrowsAnnotationsClass
{

	public function missingAnnotations(): void
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
	}

	public function ignoreUnchecked(): void
	{
		throw new LogicException();
		throw new CustomLogicException();
	}

	public function ignoreBlacklisted(): void
	{
		throw new SomeUncheckedRuntimeException();
		throw new SomeInheritedUncheckedRuntimeException();
	}

	/**
	 * @throws SomeRuntimeException
	 */
	public function correctSomeException(): void
	{
		throw new SomeRuntimeException();
	}

	/**
	 * @throws SomeRuntimeException
	 */
	public static function staticCorrectSomeException(): void
	{
		throw new SomeRuntimeException();
	}

	/**
	 * @throws NextRuntimeException
	 */
	public function correctNextException(): void
	{
		throw new NextRuntimeException();
	}

	/**
	 * @throws BaseRuntimeException
	 */
	public function correctAbstract(): void
	{
		throw new SomeRuntimeException();
	}

	public function missingAnnotationsByMethodCall(): void
	{
		$this->correctAbstract(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\BaseRuntimeException annotation
	}

	public function missingAnnotationsByStaticMethodCall(): void
	{
		self::staticCorrectSomeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		$this::staticCorrectSomeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
	}

	/**
	 * @throws BaseRuntimeException
	 */
	public function correctAnnotationsByMethodCall(): void
	{
		$this->correctAbstract();
	}

	/**
	 * @throws SomeRuntimeException
	 * @throws NextRuntimeException
	 */
	public function correctTwoAnnotationsByMethodCalls(): void
	{
		$this->correctSomeException();
		$this->correctNextException();
	}

	/**
	 * @throws BaseRuntimeException
	 */
	public function correctAbstractAnnotationByMethodCalls(): void
	{
		$this->correctSomeException();
		$this->correctNextException();
	}

	public function correctBlacklistedByMethodCalls(): void
	{
		$this->ignoreUnchecked();
		$this->ignoreBlacklisted();
	}

	public static function staticMissingAnnotations(): void
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
	}

}
