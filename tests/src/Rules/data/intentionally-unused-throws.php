<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UselessThrows;

use RuntimeException;

interface Foo {

	/**
	 * @throws RuntimeException
	 */
	public function method(): void;

}

class ImplementationExample implements Foo
{

	public function method(): void
	{
	}

}

abstract class AbstractParentExample
{

	/**
	 * @throws RuntimeException
	 */
	abstract public function method(): void;

}

class AbstractExample extends AbstractParentExample {

	public function method(): void
	{
	}

}

class OverridingAbstractExample extends AbstractExample
{

	public function method(): void
	{
	}

}

class ConcreteParentExample
{

	/**
	 * @throws RuntimeException
	 */
	public function method(): void
	{
		throw new RuntimeException();
	}

}

class OverridingConcreteExample extends ConcreteParentExample
{

	public function method(): void
	{
	}

}
