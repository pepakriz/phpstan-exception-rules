<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\ExpressionThrows;

use RuntimeException;

class FooException extends RuntimeException
{
	public static function create(): self
	{
		return new self();
	}
}

/**
 * @throws FooException
 */
function foo1() {
	$callable = fn() => throw new FooException();
}

/**
 * @throws FooException
 */
function foo2() {
	return match (random_bytes(1)) {
		'a' => 'b',
		default => throw new FooException(),
	};
}

/**
 * @throws FooException
 */
function foo3() {
	$value = $nullableValue ?? throw new FooException();
}

/**
 * @throws FooException
 */
function foo4() {
	$value = $falsableValue ?: throw new FooException();
}


class FutureThrows
{
	private ?string $name;

	/**
	 * @throws FooException
	 */
	public function ok1(): string
	{
		return $this->name ?? throw new FooException();
	}

	/**
	 * @throws FooException
	 */
	public function ok2(): string
	{
		return $this->name ?? $this->ok2();
	}

	/**
	 * @throws FooException
	 */
	public function ok3(): string
	{
		return $this->name ?? throw FooException::create();
	}

	public function err(): string
	{
		return $this->name ?? throw new FooException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\ExpressionThrows\FooException annotation
	}
}
