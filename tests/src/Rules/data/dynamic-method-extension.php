<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DynamicMethodExtension;

use Pepakriz\PHPStanExceptionRules\DynamicMethodThrowTypeExtension;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use RuntimeException;

class DynamicMethodExtension implements DynamicMethodThrowTypeExtension
{

	public function getClass(): string
	{
		return TestClass::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'throwDynamicException';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
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
