<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use DateTime;
use DateTimeImmutable;
use Exception;
use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use function is_a;

class DateTimeExtension implements DynamicConstructorThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		if (
			is_a($methodReflection->getDeclaringClass()->getName(), DateTime::class, true)
			|| is_a($methodReflection->getDeclaringClass()->getName(), DateTimeImmutable::class, true)
		) {
			return $this->resolveThrowType($newNode->getArgs(), $scope);
		}

		throw new UnsupportedClassException();
	}

	/**
	 * @param Arg[] $args
	 */
	private function resolveThrowType(array $args, Scope $scope): Type
	{
		if (!isset($args[0])) {
			return new VoidType();
		}

		$valueType = $scope->getType($args[0]->value);
		if ($valueType instanceof NullType) {
			return new VoidType();
		}

		$valueType = TypeCombinator::removeNull($valueType);
		$exceptionType = new ObjectType(Exception::class);
		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			try {
				new DateTime($constantString->getValue());
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
