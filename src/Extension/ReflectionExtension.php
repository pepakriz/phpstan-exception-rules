<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use ReflectionProperty;
use ReflectionZendExtension;
use function extension_loaded;
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

		if (is_a($className, ReflectionObject::class, true)) {
			return new VoidType();
		}

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
			return $this->resolveReflectionExtension($newNode, $scope);
		}

		throw new UnsupportedClassException();
	}

	private function resolveReflectionClass(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		if (!isset($newNode->args[0])) {
			return $reflectionExceptionType;
		}

		$valueNode = $newNode->args[0]->value;
		if (
			$valueNode instanceof ClassConstFetch
			&& $valueNode->class->toString() === 'static'
			&& $valueNode->name->toString() === 'class'
		) {
			$valueNode = clone $valueNode;
			$valueNode->class->parts[0] = 'self';
		}

		$valueType = $scope->getType($valueNode);

		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			if (!$this->broker->hasClass($constantString->getValue())) {
				return $reflectionExceptionType;
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		if (!$valueType instanceof NeverType) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

	private function resolveReflectionFunction(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		if (!isset($newNode->args[0])) {
			return $reflectionExceptionType;
		}

		$valueType = $scope->getType($newNode->args[0]->value);
		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			if (!$this->broker->hasFunction(new Name($constantString->getValue()), $scope)) {
				return $reflectionExceptionType;
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		if (!$valueType instanceof NeverType) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

	private function resolveReflectionProperty(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		if (!isset($newNode->args[1])) {
			return $reflectionExceptionType;
		}

		$valueNode = $newNode->args[0]->value;
		if (
			$valueNode instanceof ClassConstFetch
			&& $valueNode->class->toString() === 'static'
			&& $valueNode->name->toString() === 'class'
		) {
			$valueNode = clone $valueNode;
			$valueNode->class->parts[0] = 'self';
		}

		$valueType = $scope->getType($valueNode);
		$propertyType = $scope->getType($newNode->args[1]->value);
		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			if (!$this->broker->hasClass($constantString->getValue())) {
				return $reflectionExceptionType;
			}

			foreach (TypeUtils::getConstantStrings($propertyType) as $constantPropertyString) {
				try {
					if (!$this->broker->getClass($constantString->getValue())->hasProperty($constantPropertyString->getValue())) {
						return $reflectionExceptionType;
					}
				} catch (ClassNotFoundException $e) {
					return $reflectionExceptionType;
				}
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		foreach (TypeUtils::getConstantStrings($propertyType) as $constantPropertyString) {
			$propertyType = TypeCombinator::remove($propertyType, $constantPropertyString);
		}

		if (!$valueType instanceof NeverType) {
			return $reflectionExceptionType;
		}

		if (!$propertyType instanceof NeverType) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

	private function resolveReflectionExtension(New_ $newNode, Scope $scope): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		if (!isset($newNode->args[0])) {
			return $reflectionExceptionType;
		}

		$valueType = $scope->getType($newNode->args[0]->value);

		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			if (!extension_loaded($constantString->getValue())) {
				return $reflectionExceptionType;
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		if (!$valueType instanceof NeverType) {
			return $reflectionExceptionType;
		}

		return new VoidType();
	}

}
