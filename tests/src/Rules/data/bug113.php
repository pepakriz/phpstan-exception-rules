<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Bug113;

trait FooTrait
{

	abstract public function getString(): string;

}

class Foo
{

	use FooTrait;

	public function getString(): string
	{
		return 'Foo';
	}

}
