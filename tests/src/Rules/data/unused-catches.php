<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\UnusedCatches;

use LogicException;
use PharData;
use RuntimeException;

class FooException extends RuntimeException
{

}

class BarException extends RuntimeException
{

}

class UnusedCatches
{

	public function correctCatch(): void
	{
		try {
			throw new RuntimeException();
		} catch (RuntimeException $e) {

		}
	}

	public function unusedCatch(): void
	{
		try {
			throw new LogicException();
		} catch (LogicException $e) {

		} catch (RuntimeException $e) { // error: RuntimeException is never thrown in the corresponding try block

		}
	}

	public function unusedCatchWithLogic(): void
	{
		try {
			throw new LogicException();
		} catch (LogicException | RuntimeException $e) { // error: RuntimeException is never thrown in the corresponding try block

		}
	}

	public function unusedWithUsed(): void
	{
		try {
			throw new FooException();
		} catch (BarException | FooException $e) { // error: Pepakriz\PHPStanExceptionRules\Rules\UnusedCatches\BarException is never thrown in the corresponding try block

		}
	}

	public function nestedUnusedCatch(): void
	{
		try {
			try {
				throw new FooException();
			} catch (LogicException $e) {

			} catch (RuntimeException $e) {

			}
		} catch (FooException $e) { // error: Pepakriz\PHPStanExceptionRules\Rules\UnusedCatches\FooException is never thrown in the corresponding try block

		}
	}

	public function correctCatchMethodCall(): void
	{
		try {
			$this->someVoidMethod();
		} catch (LogicException $e) {

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

	public function correctCatchMethodCallWithThrows2(): void
	{
		try {
			$this->throwLogic();
		} catch (\Throwable $e) {

		}
	}

	private function someVoidMethod(): void
	{
	}

	/**
	 * @throws LogicException
	 */
	private function throwLogic(): void
	{
		throw new LogicException();
	}

	private function dynamicThrowType(): void
	{
		try {
			$tar = new PharData('input.txt');
			$tar->extractTo('output');
		} catch (RuntimeException $exception) {
		}
	}

	/**
	 * @throws FooException
	 */
	private function methodWithDefaultThrowType(callable $callable): void
	{
		$callable();
	}

}
