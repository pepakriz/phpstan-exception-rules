<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\AnonymousFunctions;

use Pepakriz\PHPStanExceptionRules\DynamicMethodThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\DynamicStaticMethodThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\Type\ClosureWithThrowType;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;
use RuntimeException;

class AnonymousFunctionsClassDynamicMethodExtension implements DynamicMethodThrowTypeExtension, DynamicStaticMethodThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		if ($methodReflection->getDeclaringClass()->getName() !== AnonymousFunctionsClass::class) {
			throw new UnsupportedClassException;
		}

		if ($methodReflection->getName() !== 'runCallback') {
			throw new UnsupportedFunctionException;
		}

		$type = $scope->getType($methodCall->args[0]->value);
		if (!$type instanceof ClosureWithThrowType) {
			return new VoidType();
		}

		return $type->getThrowType();
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		if ($methodReflection->getDeclaringClass()->getName() !== AnonymousFunctionsClass::class) {
			throw new UnsupportedClassException;
		}

		if ($methodReflection->getName() !== 'runCallbackAsStatic') {
			throw new UnsupportedFunctionException;
		}

		$type = $scope->getType($methodCall->args[0]->value);
		if (!$type instanceof ClosureWithThrowType) {
			return new VoidType();
		}

		return $type->getThrowType();
	}

}

class AnonymousFunctionsClass
{

	public function returnOnly(): callable
	{
		return function (): void {
			throw new RuntimeException();
		};
	}

	public function returnAsVariable(): callable
	{
		$fn = function (): void {
			throw new RuntimeException();
		};

		return $fn;
	}

	public function executedAfterDeclaration(): void
	{
		(function (): void { // error: Missing @throws RuntimeException annotation
			throw new RuntimeException();
		})();
	}

	public function executedByVariable(): void
	{
		$fn = function (): void {
			throw new RuntimeException();
		};

		$fn(); // error: Missing @throws RuntimeException annotation
	}

	public function notExecutedWithNestedClosures(): void
	{
		$fn = function (): callable {
			return function (): void {
				throw new RuntimeException();
			};
		};

		$fn();
	}

	public function notExecutedWithNestedExecutedClosures(): void
	{
		function (): void {
			$fn = function (): void {
				throw new RuntimeException();
			};

			(function (): void {
				throw new RuntimeException();
			})();

			$fn();
		};
	}

	public function executedByVariableWithNestedExecutedClosures(): void
	{
		$fn = function (): void {
			$fn2 = function (): void {
				throw new RuntimeException();
			};

			$fn2();
		};

		$fn(); // error: Missing @throws RuntimeException annotation
	}

	public function executedByVariableWithNestedInlineExecutedClosures(): void
	{
		$fn = function (): void {
			(function (): void {
				throw new RuntimeException();
			})();
		};

		$fn(); // error: Missing @throws RuntimeException annotation
	}

	public function executedByVariableWithCaughtNestedExecutedClosures(): void
	{
		$fn = function (): void {
			$fn2 = function (): void {
				throw new RuntimeException();
			};

			try {
				$fn2();
			} catch (RuntimeException $e) {
				// ignore
			}
		};

		$fn();
	}

	public function executedByVariableWithCaughtNestedInlineExecutedClosures(): void
	{
		$fn = function (): void {
			try {
				(function (): void {
					throw new RuntimeException();
				})();
			} catch (RuntimeException $e) {
				// ignore
			}
		};

		$fn();
	}

	public function executedByFunction(): void
	{
		array_map(function (): void { // error: Missing @throws RuntimeException annotation
			throw new RuntimeException();
		}, []);
	}

	public function executedByMethod(): void
	{
		$this->runCallback(function (): void { // error: Missing @throws RuntimeException annotation
			throw new RuntimeException();
		});
	}

	public function executedByStaticMethod(): void
	{
		self::runCallbackAsStatic(function (): void { // error: Missing @throws RuntimeException annotation
			throw new RuntimeException();
		});
	}

	private function runCallback(callable $callback): void
	{
		$callback();
	}

	private static function runCallbackAsStatic(callable $callback): void
	{
		$callback();
	}

}
