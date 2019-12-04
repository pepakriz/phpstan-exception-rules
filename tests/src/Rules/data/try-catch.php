<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\TryCatch;

use LogicException;
use RuntimeException;
use Throwable;

class FooRuntimeException extends RuntimeException {}
class BarRuntimeException extends RuntimeException {}
class SomeRuntimeException extends RuntimeException {}

class TryCatchClass
{

	public function caughtException(): void
	{
		try {
			throw new RuntimeException();
		} catch (Throwable $e) { // error: Catching checked exception RuntimeException as unchecked Throwable is not supported properly in this moment. Eliminate checked exceptions by custom catch statement.
			// ignore
		}
	}

	public function uncaughtException(): void
	{
		try {
			throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		} catch (LogicException $e) {
			// ignore
		}
	}

	public function caughtAndThrewException(): void
	{
		try {
			throw new RuntimeException();
		} catch (RuntimeException $e) {
			throw $e; // error: Missing @throws RuntimeException annotation
		}
	}

	public function caughtAndThrownAsBlacklistedException(): void
	{
		try {
			throw new RuntimeException();
		} catch (Throwable $e) { // error: Catching checked exception RuntimeException as unchecked Throwable is not supported properly in this moment. Eliminate checked exceptions by custom catch statement.
			throw $e;
		}
	}

	public function caughtOneException(): void
	{
		try {
			if (rand(0, 1)) {
				throw new FooRuntimeException();
			}
			if (rand(0, 1)) {
				throw new BarRuntimeException();
			}
		} catch (FooRuntimeException $e) {
			// ignore
		} catch (RuntimeException $e) {
			throw $e; // error: Missing @throws RuntimeException annotation
		}
	}

	public function nestedCaughtException(): void
	{
		try {
			try {
				throw new FooRuntimeException();
			} catch (LogicException $e) {
				// ignore
			}
		} catch (FooRuntimeException $e) {

		}
	}

	public function caughtOneFromUnion(): void
	{
		try {
			$this->throwUnion(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\TryCatch\SomeRuntimeException annotation
		} catch (FooRuntimeException | BarRuntimeException $e) {
			// ignore
		}
	}

	public function caughtOneFromStaticUnion(): void
	{
		try {
			self::throwStaticUnion(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\TryCatch\SomeRuntimeException annotation
		} catch (FooRuntimeException | BarRuntimeException $e) {
			// ignore
		}
	}

	public function caughtOnlySomeSubtypes(): void
	{
		try {
			$this->throwAsParent(); // error: Missing @throws RuntimeException annotation
		} catch (FooRuntimeException $e) {
			// ignore
		} catch (BarRuntimeException $e) {
			// ignore
		} catch (SomeRuntimeException $e) {
			// ignore
		}
	}

	public function caughtSomeSubtypesAndConcreteException(): void
	{
		try {
			$this->throwAsParent();
		} catch (FooRuntimeException $e) {
			// ignore
		} catch (BarRuntimeException $e) {
			// ignore
		} catch (SomeRuntimeException $e) {
			// ignore
		} catch (RuntimeException $e) {
			// ignore
		}
	}

	/**
	 * @throws FooRuntimeException
	 * @throws BarRuntimeException
	 * @throws SomeRuntimeException
	 */
	private function throwUnion(): void
	{
		throw new FooRuntimeException();
		throw new BarRuntimeException();
		throw new SomeRuntimeException();
	}

	/**
	 * @throws FooRuntimeException
	 * @throws BarRuntimeException
	 * @throws SomeRuntimeException
	 */
	private static function throwStaticUnion(): void
	{
		throw new FooRuntimeException();
		throw new BarRuntimeException();
		throw new SomeRuntimeException();
	}

	/**
	 * @throws RuntimeException
	 */
	private function throwAsParent(): void
	{
		throw new FooRuntimeException();
		throw new BarRuntimeException();
		throw new SomeRuntimeException();
	}

}
