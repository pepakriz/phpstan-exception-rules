<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use DateInterval;
use Exception;
use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use function is_a;

class DateIntervalExtension implements DynamicConstructorThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		if (is_a($methodReflection->getDeclaringClass()->getName(), DateInterval::class, true)) {
			return $this->resolveThrowType($newNode->getArgs(), $scope);
		}

		throw new UnsupportedClassException();
	}

	/**
	 * @param Arg[] $args
	 */
	private function resolveThrowType(array $args, Scope $scope): Type
	{
		$exceptionType = new ObjectType(Exception::class);
		if (!isset($args[0])) {
			return $exceptionType;
		}

		$valueType = $scope->getType($args[0]->value);
		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			try {
				new DateInterval($constantString->getValue());
			} catch (Exception $e) {
				return $exceptionType;
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		if (!$valueType instanceof NeverType) {
			return $exceptionType;
		}

		return new VoidType();
	}

}
