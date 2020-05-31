<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data;

use ArrayIterator;
use Exception;
use Generator;
use IteratorAggregate;
use LogicException;
use RuntimeException;
use Traversable;

class CustomLogicException extends LogicException {}
abstract class BaseRuntimeException extends RuntimeException {}
abstract class BaseBlacklistedRuntimeException extends BaseRuntimeException {}
class SomeRuntimeException extends BaseRuntimeException {}
class NextRuntimeException extends BaseRuntimeException {}
class ConcreteNextRuntimeException extends NextRuntimeException {}
class CheckedException extends Exception {}

/**
 * @throws RuntimeException
 */
function foo()  {
	throw new RuntimeException();
}

class MagicService
{

	/**
	 * @throws CheckedException
	 */
	public function __call($name, $arguments): void
	{
		throw new CheckedException();
	}

	/**
	 * @throws CheckedException
	 */
	public static function __callStatic($name, $arguments)
	{
		throw new CheckedException();
	}

}

class ThrowsAnnotationsClass
{

	public function missingAnnotations(): void
	{
		if (rand(0, 1)) {
			throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		}
		if (rand(0, 1)) {
			throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		}
		if (rand(0, 1)) {
			throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
		}
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
		if (rand(0, 1)) {
			throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		}
		if (rand(0, 1)) {
			throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		}
		if (rand(0, 1)) {
			throw new CheckedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
		}
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

	public function callUnionContainsUnknown(): void
	{
		/** @var UnionOne|UnknownClass $union */
		$union = getUnion();
		$union->foo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
	}

	public function callStaticUnionContainsUnknown(): void
	{
		/** @var UnionOne|UnknownClass $union */
		$union = getStaticUnion();
		$union::staticFoo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
	}

	public function callMagicMethod(): void
	{
		(new MagicService())->foo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
	}

	public function callStaticMagicMethod(): void
	{
		MagicService::staticFoo(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException annotation
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
	public function foo(): void
	{

	}

}

/**
 * @return Generator|int[]
 *
 * @throws RuntimeException
 */
function functionInner(): Generator
{
	throw new RuntimeException('abc');
	yield 1;
	yield 2;
	yield 3;
}

class YieldFromIteratorAggregate implements IteratorAggregate
{

	/**
	 * @throws RuntimeException
	 */
	public function getIterator(): Traversable
	{
		throw new RuntimeException();
	}

}

class YieldFromIterator extends ArrayIterator
{

	/**
	 * @throws RuntimeException
	 */
	public function valid()
	{
		throw new RuntimeException();
	}

}

class YieldFrom
{

	/**
	 * @return Generator|int[]
	 *
	 * @throws RuntimeException
	 */
	public function inner(): Generator
	{
		throw new RuntimeException('abc');
		yield 1;
		yield 2;
		yield 3;
	}

	/**
	 * @return Generator|int[]
	 *
	 * @throws RuntimeException
	 */
	public static function staticInner(): Generator
	{
		throw new RuntimeException('abc');
		yield 1;
		yield 2;
		yield 3;
	}

	public function gen() {
		yield 0;
		yield from $this->inner(); // error: Missing @throws RuntimeException annotation
		yield from self::staticInner(); // error: Missing @throws RuntimeException annotation
		yield from functionInner(); // error: Missing @throws RuntimeException annotation
		yield from new YieldFromIteratorAggregate(); // error: Missing @throws RuntimeException annotation
		yield from new YieldFromIterator(); // error: Missing @throws RuntimeException annotation
		yield 4;
	}

}
