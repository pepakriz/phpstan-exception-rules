<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use ArithmeticError;
use DivisionByZeroError;
use Pepakriz\PHPStanExceptionRules\DynamicFunctionThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use const PHP_INT_MAX;

class IntdivExtension implements DynamicFunctionThrowTypeExtension
{

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		if ($functionReflection->getName() !== 'intdiv') {
			throw new UnsupportedFunctionException();
		}

		$containsMax = false;
		$valueType = $scope->getType($functionCall->args[0]->value);
		foreach (TypeUtils::getConstantScalars($valueType) as $constantScalarType) {
			if ($constantScalarType->getValue() === PHP_INT_MAX) {
				$containsMax = true;
			}

			$valueType = TypeCombinator::remove($valueType, $constantScalarType);
		}

		if (!$valueType instanceof NeverType) {
			return new ObjectType(ArithmeticError::class);
		}

		$divisionByZero = false;
		$divisorType = $scope->getType($functionCall->args[1]->value);
		foreach (TypeUtils::getConstantScalars($divisorType) as $constantScalarType) {
			if ($constantScalarType->getValue() === 0) {
				$divisionByZero = true;
			}

			if ($containsMax && $constantScalarType->getValue() === -1) {
				return new ObjectType(ArithmeticError::class);
			}

			$divisorType = TypeCombinator::remove($divisorType, $constantScalarType);
		}

		if (!$divisorType instanceof NeverType) {
			return new ObjectType(ArithmeticError::class);
		}

		if ($divisionByZero) {
			return new ObjectType(DivisionByZeroError::class);
		}

		return new VoidType();
	}

}
