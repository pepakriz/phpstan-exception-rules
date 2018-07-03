<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Iterators;

use Iterator;
use IteratorAggregate;
use RuntimeException;

class Iterators
{

	public function iteratorInForeach(): void
	{
		$fooValues = new FooIterator();
		foreach ($fooValues as $value) { // error: Missing @throws RuntimeException annotation

		}
	}

	public function iteratorAggregateInForeach(): void
	{
		$fooValues = new FooIteratorAggregate();
		foreach ($fooValues as $value) { // error: Missing @throws RuntimeException annotation

		}
	}

}

class FooIterator implements Iterator
{

	/**
	 * @throws RuntimeException
	 */
	public function current()
	{
		throw new RuntimeException();
	}

	public function next()
	{
	}

	public function key()
	{
	}

	public function valid()
	{
	}

	public function rewind()
	{
	}

}

class FooIteratorAggregate implements IteratorAggregate
{

	/**
	 * @throws RuntimeException
	 */
	public function getIterator()
	{
		throw new RuntimeException();
	}

}
