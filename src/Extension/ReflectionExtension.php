<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionProperty;
use ReflectionZendExtension;
use function is_a;

class ReflectionExtension implements DynamicConstructorThrowTypeExtension
{

	/**
	 * @var Broker
	 */
	private $broker;

	public function __construct(
		Broker $broker
	)
	{
		$this->broker = $broker;
	}

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		$className = $methodReflection->getDeclaringClass()->getName();

		if (is_a($className, ReflectionClass::class, true)) {
			return $this->resolveReflectionClass($newNode, $scope);
		}

		if (is_a($className, ReflectionProperty::class, true)) {
			return $this->resolveReflectionProperty($newNode, $scope);
		}

		if (is_a($className, ReflectionFunction::class, true)) {
			return $this->resolveReflectionFunction($newNode, $scope);
		}

		if (is_a($className, ReflectionZendExtension::class, true)) {
			return $this->resolveReflectionClass($newNode, $scope);
		}

		throw new UnsupportedClassException();
	}

	private function resolveReflectionClass(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		$valueType = $scope->getType($newNode->args[0]->value);
		if (!$valueType instanceof ConstantStringType) {
			return $reflectionExceptionType;
		}

		if (!$this->broker->hasClass($valueType->getValue())) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

	private function resolveReflectionFunction(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		$valueType = $scope->getType($newNode->args[0]->value);
		if (!$valueType instanceof ConstantStringType) {
			return $reflectionExceptionType;
		}

		if (!$this->broker->hasFunction(new Name($valueType->getValue()), $scope)) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

	private function resolveReflectionProperty(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		$valueType = $scope->getType($newNode->args[0]->value);
		if (!$valueType instanceof ConstantStringType) {
			return $reflectionExceptionType;
		}

		$propertyType = $scope->getType($newNode->args[1]->value);
		if (!$propertyType instanceof ConstantStringType) {
			return $reflectionExceptionType;
		}

		try {
			if (!$this->broker->getClass($valueType->getValue())->hasProperty($propertyType->getValue())) {
				return $reflectionExceptionType;
			}
		} catch (ClassNotFoundException $e) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

}
