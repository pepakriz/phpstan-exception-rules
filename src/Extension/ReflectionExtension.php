<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
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

		if (is_a($className, ReflectionMethod::class, true)) {
			return $this->resolveReflectionMethod($newNode, $scope);
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
		if (!isset($newNode->getArgs()[0])) {
			return $reflectionExceptionType;
		}

		$valueType = $this->resolveType($newNode->getArgs()[0]->value, $scope);
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
		if (!isset($newNode->getArgs()[0])) {
			return $reflectionExceptionType;
		}

		$valueType = $this->resolveType($newNode->getArgs()[0]->value, $scope);
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
		return $this->resolveReflectionMethodOrProperty($newNode, $scope, static function (ClassReflection $classReflection, ConstantStringType $type): bool {
			return $classReflection->hasProperty($type->getValue());
		});
	}

	private function resolveReflectionMethod(New_ $newNode, Scope $scope): Type
	{
		return $this->resolveReflectionMethodOrProperty($newNode, $scope, static function (ClassReflection $classReflection, ConstantStringType $type): bool {
			return $classReflection->hasMethod($type->getValue());
		});
	}

	private function resolveReflectionMethodOrProperty(New_ $newNode, Scope $scope, callable $existenceChecker): Type
	{
		$reflectionExceptionType = new ObjectType(ReflectionException::class);
		if (!isset($newNode->getArgs()[1])) {
			return $reflectionExceptionType;
		}

		$valueType = $this->resolveType($newNode->getArgs()[0]->value, $scope);
		$propertyType = $this->resolveType($newNode->getArgs()[1]->value, $scope);
		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			try {
				$classReflection = $this->broker->getClass($constantString->getValue());
			} catch (ClassNotFoundException $e) {
				return $reflectionExceptionType;
			}

			foreach (TypeUtils::getConstantStrings($propertyType) as $constantPropertyString) {
				if (!$existenceChecker($classReflection, $constantPropertyString)) {
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
		if (!isset($newNode->getArgs()[0])) {
			return $reflectionExceptionType;
		}

		$valueType = $this->resolveType($newNode->getArgs()[0]->value, $scope);
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

	private function resolveType(Expr $node, Scope $scope): Type
	{
		$classReflection = $scope->getClassReflection();

		if (
			$classReflection !== null
			&& $node instanceof ClassConstFetch
			&& $node->class instanceof Name
			&& $node->name instanceof Identifier
			&& $node->class->toString() === 'static'
			&& $node->name->toString() === 'class'
		) {
			return new ConstantStringType($classReflection->getName());
		}

		$traverser = new NodeTraverser();
		$traverser->addVisitor(new CloningVisitor()); // deep copy
		$traverser->addVisitor(new class extends NodeVisitorAbstract {

			public function enterNode(Node $node): Node
			{
				if (
					$node instanceof ClassConstFetch
					&& $node->class instanceof Name
					&& $node->name instanceof Identifier
					&& $node->class->toString() === 'static'
					&& $node->name->toString() === 'class'
				) {
					$node->class->parts[0] = 'self';
				}

				return $node;
			}

		});

		$node = $traverser->traverse([$node])[0];
		if (!$node instanceof Expr) {
			throw new ShouldNotHappenException();
		}

		// Reset the cache to force a new computation
		$node->setAttribute('phpstan_cache_printer', null);

		return $scope->getType($node);
	}

}
