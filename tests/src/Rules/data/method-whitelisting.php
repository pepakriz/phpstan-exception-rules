<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceOverriding;

use RuntimeException;
use PHPUnit\Framework\TestCase;

class FooTest extends TestCase {

	public function foo(): void
	{
	}

	/**
	 * @throws RuntimeException
	 */
	public function testBar(): void  // error: Unused @throws RuntimeException annotation
	{
		throw new RuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function bar(): void
	{
		throw new RuntimeException();
	}

}
