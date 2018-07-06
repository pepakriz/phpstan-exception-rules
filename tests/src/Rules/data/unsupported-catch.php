<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\UnsupportedCatch;

use LogicException;
use RuntimeException;
use Throwable;

class FooRuntimeException extends RuntimeException {}
class BarRuntimeException extends RuntimeException {}
class SomeRuntimeException extends RuntimeException {}

class UnsupportedCatchClass
{

	public function catchCheckedAndUnchecked(): void
	{
		try {
			throw new RuntimeException();
			throw new LogicException();
		} catch (Throwable $e) { // error: Catching checked (RuntimeException) and unchecked (LogicException) exceptions in one catch statement is not supported
			// ignore
		}
	}

}
