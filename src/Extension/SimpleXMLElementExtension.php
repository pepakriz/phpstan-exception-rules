<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

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
use SimpleXMLElement;
use function is_a;

class SimpleXMLElementExtension implements DynamicConstructorThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		if (is_a($methodReflection->getDeclaringClass()->getName(), SimpleXMLElement::class, true)) {
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
				new SimpleXMLElement($constantString->getValue());
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
