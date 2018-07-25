<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use Pepakriz\PHPStanExceptionRules\Type\ClosureWithThrowType;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;

class CoreFunctionsDynamicThrowTypeExtension implements DynamicFunctionThrowTypeExtension
{

	/**
	 * @var int[]
	 */
	private static $supportedSynchronizedFunctions = [
		'array_map' => 0,
	];

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		$argumentIndex = self::$supportedSynchronizedFunctions[$functionReflection->getName()] ?? null;
		if ($argumentIndex === null) {
			throw new UnsupportedFunctionException();
		}

		$type = $scope->getType($functionCall->args[$argumentIndex]->value);
		if ($type instanceof ClosureWithThrowType) {
			return $type->getThrowType();
		}

		return new VoidType();
	}

}
