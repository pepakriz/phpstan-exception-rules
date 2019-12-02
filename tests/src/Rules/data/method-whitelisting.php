<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceOverriding;

use RuntimeException;
use PHPUnit\Framework\TestCase;

class FooTest extends TestCase {

	public function foo(): void
	{
	}

	public function fooBis(): void  // I expect error here, since it's not in the whitelist
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
	}

	/**
	 * @throws RuntimeException
	 */
	public function testBar(): void  // I don't expect error here, you can annotate if you want
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

	public function testFoo(): void
	{
		throw new RuntimeException();
	}

	public function testFooBis(): void
	{
		$this->bar();
	}

}
