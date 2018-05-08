<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data;

use LogicException;
use RuntimeException;
use Throwable;

class FooRuntimeException extends RuntimeException {}
class BarRuntimeException extends RuntimeException {}

class TryCatchClass
{

	public function catchedException(): void
	{
		try {
			throw new RuntimeException();
		} catch (Throwable $e) {
			// ignore
		}
	}

	public function uncatchedException(): void
	{
		try {
			throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		} catch (LogicException $e) {
			// ignore
		}
	}

	public function catchedAndThrowedException(): void
	{
		try {
			throw new RuntimeException();
		} catch (RuntimeException $e) {
			throw $e; // error: Missing @throws RuntimeException annotation
		}
	}

	public function catchedAndThrowedAsBlacklistedException(): void
	{
		try {
			throw new RuntimeException();
		} catch (Throwable $e) {
			throw $e;
		}
	}

	public function catchedOneException(): void
	{
		try {
			throw new FooRuntimeException();
			throw new BarRuntimeException();
		} catch (FooRuntimeException $e) {
			// ignore
		} catch (RuntimeException $e) {
			throw $e; // error: Missing @throws RuntimeException annotation
		}
	}

	public function nestedCatchedException(): void
	{
		try {
			try {
				throw new FooRuntimeException();
			} catch (BarRuntimeException $e) {
				// ignore
			}
		} catch (FooRuntimeException $e) {

		}
	}

}
