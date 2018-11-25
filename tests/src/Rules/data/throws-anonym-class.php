<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\AnonymClass;

use OutOfBoundsException;

class Example
{

	/**
	 * @throws OutOfBoundsException
	 */
	public function testName(): void
	{
		new class
		{

			public function foo(): string
			{
				return 'foo';
			}

		};

		$foo = new Foo();
		$foo->throw();
	}

}

class Foo
{

	/**
	 * @throws OutOfBoundsException
	 */
	public function throw(): void
	{
		throw new OutOfBoundsException();
	}

}
