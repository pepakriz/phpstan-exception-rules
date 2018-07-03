<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Iterators;

use Iterator;
use function iterator_apply;
use function iterator_count;
use function iterator_to_array;
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

	public function iteratorFunctions(): void
	{
		iterator_count(new FooIterator()); // error: Missing @throws RuntimeException annotation
		iterator_to_array(new FooIterator()); // error: Missing @throws RuntimeException annotation
		iterator_apply(new FooIterator(), function () {}); // error: Missing @throws RuntimeException annotation
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

	/**
	 * @throws RuntimeException
	 */
	public function next()
	{
		throw new RuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function key()
	{
		throw new RuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function valid()
	{
		throw new RuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	public function rewind()
	{
		throw new RuntimeException();
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
