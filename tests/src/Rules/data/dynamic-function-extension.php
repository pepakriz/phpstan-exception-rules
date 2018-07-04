<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DynamicFunctionExtension;

use Pepakriz\PHPStanExceptionRules\DynamicFunctionThrowTypeExtension;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use RuntimeException;

function throwDynamicException(): void {

}

class DynamicFunctionExtension implements DynamicFunctionThrowTypeExtension
{

	public function isFunctionSupported(FunctionReflection $functionReflection): bool
	{
		return $functionReflection->getName() === __NAMESPACE__ . '\\throwDynamicException';
	}

	public function getTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		return new ObjectType(RuntimeException::class);
	}

}

class TestClass
{

	public function test()
	{
		blankFunction();
		throwDynamicException(); // error: Missing @throws RuntimeException annotation
	}

}
