<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DynamicFunctionExtension;

use Pepakriz\PHPStanExceptionRules\DynamicFunctionThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
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

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		if ($functionReflection->getName() !== __NAMESPACE__ . '\\throwDynamicException') {
			throw new UnsupportedFunctionException();
		}

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
