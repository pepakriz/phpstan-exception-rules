<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Countables;

use Countable;
use RuntimeException;

class Countables
{

	public function callCountWithCountable(): void
	{
		count([1, 2, 3]);
		$countableObject = new FooCountable();
		count($countableObject); // error: Missing @throws RuntimeException annotation
	}

}

class FooCountable implements Countable
{

	/**
	 * @throws RuntimeException
	 */
	public function count()
	{
		throw new RuntimeException();
	}

}
