<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VoidType;
use function array_map;
use function count;

class DefaultThrowTypeExtension implements DynamicFunctionThrowTypeExtension, DynamicMethodThrowTypeExtension, DynamicConstructorThrowTypeExtension, DynamicStaticMethodThrowTypeExtension
{

	/**
	 * @var Type[][]
	 */
	private $methodThrowTypes = [];

	/**
	 * @var Type[]
	 */
	private $functionThrowTypes = [];

	/**
	 * @param string[][][] $methodThrowTypes
	 * @param string[][] $functionThrowTypes
	 */
	public function __construct(
		array $methodThrowTypes,
		array $functionThrowTypes
	)
	{
		foreach ($methodThrowTypes as $className => $methods) {
			foreach ($methods as $methodName => $throwTypes) {
				if (count($throwTypes) === 0) {
					$this->methodThrowTypes[$className][$methodName] = new VoidType();
					continue;
				}

				$this->methodThrowTypes[$className][$methodName] = TypeCombinator::union(
					...array_map(static function (string $throwType): ObjectType {
						return new ObjectType($throwType);
					}, $throwTypes)
				);
			}
		}

		foreach ($functionThrowTypes as $functionName => $throwTypes) {
			if (count($throwTypes) === 0) {
				$this->functionThrowTypes[$functionName] = new VoidType();
				continue;
			}

			$this->functionThrowTypes[$functionName] = TypeCombinator::union(
				...array_map(static function (string $throwType): ObjectType {
					return new ObjectType($throwType);
				}, $throwTypes)
			);
		}
	}

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		$functionName = $functionReflection->getName();
		if (!isset($this->functionThrowTypes[$functionName])) {
			throw new UnsupportedFunctionException();
		}

		return $this->functionThrowTypes[$functionName];
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		return $this->getMethodThrowType($methodReflection);
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		return $this->getMethodThrowType($methodReflection);
	}

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		try {
			return $this->getMethodThrowType($methodReflection);
		} catch (UnsupportedFunctionException $e) {
			throw new UnsupportedClassException();
		}
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	private function getMethodThrowType(MethodReflection $methodReflection): Type
	{
		$className = $methodReflection->getDeclaringClass()->getName();
		if (!isset($this->methodThrowTypes[$className])) {
			throw new UnsupportedClassException();
		}

		if (!isset($this->methodThrowTypes[$className][$methodReflection->getName()])) {
			throw new UnsupportedFunctionException();
		}

		return $this->methodThrowTypes[$className][$methodReflection->getName()];
	}

}
