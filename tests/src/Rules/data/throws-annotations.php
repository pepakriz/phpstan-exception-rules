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
class ConcreteNextRuntimeException extends NextRuntimeException {}
class CheckedException extends Exception {}

class ThrowsAnnotationsClass
{

	public function missingAnnotations(): void
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
	}

	public function ignoreNonWhitelisted(): void
	{
		throw new LogicException();
		throw new CustomLogicException();
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
		$this->ignoreNonWhitelisted();
		$this->ignoreBlacklisted();
	}

	public static function staticMissingAnnotations(): void
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
	}

	public function callUndefinedMethod(): void
	{
		$this->undefinedMethod();
	}

	public function createNewInstance(): void
	{
		new ThrowInConstructor(); // error: Missing @throws RuntimeException annotation
	}

	/**
	 * @throws NextRuntimeException
	 */
	public function callUnion(): void
	{
		/** @var UnionOne|UnionTwo|UnionThree $union */
		$union = getUnion();
		$union->foo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
	}

	/**
	 * @throws NextRuntimeException
	 */
	public function callStaticUnion(): void
	{
		/** @var UnionOne|UnionTwo|UnionThree $union */
		$union = getStaticUnion();
		$union::staticFoo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
	}

}

class ThrowInConstructor
{

	/**
	 * @throws RuntimeException
	 */
	public function __construct()
	{
		throw new RuntimeException();
	}

}

class UnionOne  {

	/**
	 * @throws SomeRuntimeException
	 */
	public function foo(): void
	{
		throw new SomeRuntimeException();
	}

	/**
	 * @throws SomeRuntimeException
	 */
	public static function staticFoo(): void
	{
		throw new SomeRuntimeException();
	}

}

class UnionTwo {

	/**
	 * @throws NextRuntimeException
	 */
	public function foo(): void
	{
		throw new NextRuntimeException();
	}

	/**
	 * @throws NextRuntimeException
	 */
	public static function staticFoo(): void
	{
		throw new NextRuntimeException();
	}

}

class UnionThree {

	/**
	 * @throws ConcreteNextRuntimeException
	 */
	public function foo(): void
	{
		throw new ConcreteNextRuntimeException();
	}

	/**
	 * @throws ConcreteNextRuntimeException
	 */
	public static function staticFoo(): void
	{
		throw new ConcreteNextRuntimeException();
	}

}

class Issue6
{

	/**
	 * @throws SomeRuntimeException
	 */
	public function foo()
	{
		$this->bar(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\NextRuntimeException annotation
	}

	/**
	 * @throws SomeRuntimeException
	 * @throws NextRuntimeException
	 */
	public function bar()
	{
		throw new SomeRuntimeException();
		throw new NextRuntimeException();
	}

}

abstract class BaseInheritdoc
{

	/**
	 * @throws RuntimeException
	 */
	public function foo(): void
	{
		throw new RuntimeException();
	}

}

class Inheritdoc extends BaseInheritdoc
{

	/**
	 * {@inheritdoc}
	 */
	public function foo(): void // error: Unused @throws RuntimeException annotation
	{

	}

}
