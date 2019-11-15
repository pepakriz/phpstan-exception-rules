<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UselessThrows;

use RuntimeException;

interface Foo {
	/**
	 * @throws RuntimeException
	 */
	public function method() : void;
}

class UnusedThrows implements Foo
{
	public function method() : void
	{
	}
}
