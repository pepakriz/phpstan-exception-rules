<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DynamicMethodExtension;

use Pepakriz\PHPStanExceptionRules\DynamicMethodThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use RuntimeException;

class DynamicMethodExtension implements DynamicMethodThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		if (!is_a($methodReflection->getDeclaringClass()->getName(), TestClass::class, true)) {
			throw new UnsupportedClassException();
		}

		if ($methodReflection->getName() !== 'throwDynamicException') {
			throw new UnsupportedFunctionException();
		}

		return new ObjectType(RuntimeException::class);
	}

}

class TestClass
{

	public function test()
	{
		$this->blankMethod();
		$this->throwDynamicException(); // error: Missing @throws RuntimeException annotation
	}

	public function blankMethod(): void
	{

	}

	public function throwDynamicException(): void
	{

	}

}
