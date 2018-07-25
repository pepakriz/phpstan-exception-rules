<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UnusedCatchesAll;

use LogicException;
use RuntimeException;

class FooException extends RuntimeException
{

}

class UnusedCatches
{

	public function nestedUnusedCatch(): void
	{
		try {
			try {
				throw new FooException();
			} catch (LogicException $e) { // error: LogicException is never thrown in the corresponding try block

			} catch (RuntimeException $e) {

			}
		} catch (FooException $e) { // error: Pepakriz\PHPStanExceptionRules\Rules\UnusedCatchesAll\FooException is never thrown in the corresponding try block

		}
	}

	public function correctCatchMethodCall(): void
	{
		try {
			$this->someVoidMethod();
		} catch (LogicException $e) { // error: LogicException is never thrown in the corresponding try block

		} catch (RuntimeException $e) { // error: RuntimeException is never thrown in the corresponding try block

		}
	}

	public function correctCatchMethodCallWithThrows(): void
	{
		try {
			$this->throwLogic();
		} catch (LogicException $e) {

		} catch (RuntimeException $e) { // error: RuntimeException is never thrown in the corresponding try block

		}
	}

	private function someVoidMethod(): void
	{
	}

	/**
	 * @throws LogicException
	 */
	private function throwLogic(): void // error: Unused @throws LogicException annotation
	{
		throw new LogicException();
	}

}

