<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use JsonException;
use Pepakriz\PHPStanExceptionRules\DynamicFunctionThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use function json_decode;
use function json_encode;
use function version_compare;
use const JSON_THROW_ON_ERROR;
use const PHP_VERSION;

class JsonEncodeDecodeExtension implements DynamicFunctionThrowTypeExtension
{

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		if ($functionReflection->getName() === 'json_decode') {
			if (version_compare(PHP_VERSION, '7.3.0RC1') < 0) {
				return new VoidType();
			}

			if (!isset($functionCall->getArgs()[3])) {
				return new VoidType();
			}

			$valueType = $scope->getType($functionCall->getArgs()[0]->value);
			foreach (TypeUtils::getConstantScalars($valueType) as $constantScalarType) {
				try {
					json_decode((string) $constantScalarType->getValue(), true, 512, JSON_THROW_ON_ERROR);
					$valueType = TypeCombinator::remove($valueType, $constantScalarType);
				} catch (JsonException $e) {
					// ignore error
				}
			}

			if ($valueType instanceof NeverType) {
				return new VoidType();
			}

			$exceptionType = new ObjectType(JsonException::class);
			$optionsType = $scope->getType($functionCall->getArgs()[3]->value);
			foreach (TypeUtils::getConstantScalars($optionsType) as $constantScalarType) {
				if (!$constantScalarType instanceof IntegerType) {
					continue;
				}

				if (!$constantScalarType instanceof ConstantIntegerType) {
					return $exceptionType;
				}

				if (($constantScalarType->getValue() & JSON_THROW_ON_ERROR) === JSON_THROW_ON_ERROR) {
					return $exceptionType;
				}

				$optionsType = TypeCombinator::remove($optionsType, $constantScalarType);
			}

			if (!$optionsType instanceof NeverType) {
				return $exceptionType;
			}

			return new VoidType();
		}

		if ($functionReflection->getName() === 'json_encode') {
			if (version_compare(PHP_VERSION, '7.3.0RC1') < 0) {
				return new VoidType();
			}

			if (!isset($functionCall->getArgs()[1])) {
				return new VoidType();
			}

			$valueType = $scope->getType($functionCall->getArgs()[0]->value);
			foreach (TypeUtils::getConstantScalars($valueType) as $constantScalarType) {
				try {
					json_encode($constantScalarType->getValue(), JSON_THROW_ON_ERROR);
					$valueType = TypeCombinator::remove($valueType, $constantScalarType);
				} catch (JsonException $e) {
					// ignore error
				}
			}

			if ($valueType instanceof NeverType) {
				return new VoidType();
			}

			$exceptionType = new ObjectType(JsonException::class);
			$optionsType = $scope->getType($functionCall->getArgs()[1]->value);
			foreach (TypeUtils::getConstantScalars($optionsType) as $constantScalarType) {
				if (!$constantScalarType instanceof IntegerType) {
					continue;
				}

				if (!$constantScalarType instanceof ConstantIntegerType) {
					return $exceptionType;
				}

				if (($constantScalarType->getValue() & JSON_THROW_ON_ERROR) === JSON_THROW_ON_ERROR) {
					return $exceptionType;
				}

				$optionsType = TypeCombinator::remove($optionsType, $constantScalarType);
			}

			if (!$optionsType instanceof NeverType) {
				return $exceptionType;
			}

			return new VoidType();
		}

		throw new UnsupportedFunctionException();
	}

}
