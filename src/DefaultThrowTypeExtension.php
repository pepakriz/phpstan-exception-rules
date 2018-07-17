<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function array_map;

class DefaultThrowTypeExtension implements DynamicFunctionThrowTypeExtension, DynamicMethodThrowTypeExtension, DynamicStaticMethodThrowTypeExtension
{

	/**
	 * @var string[][][]
	 */
	private $methodThrowTypes = [];

	/**
	 * @var string[][]
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
				$this->methodThrowTypes[$className][$methodName] = [];
				foreach ($throwTypes as $throwType) {
					$this->addMethodThrowType($className, $methodName, $throwType);
				}
			}
		}

		foreach ($functionThrowTypes as $functionName => $throwTypes) {
			$this->functionThrowTypes[$functionName] = [];
			foreach ($throwTypes as $throwType) {
				$this->addFunctionThrowType($functionName, $throwType);
			}
		}
	}

	private function addMethodThrowType(string $className, string $methodName, string $throwType): void
	{
		$this->methodThrowTypes[$className][$methodName][] = $throwType;
	}

	private function addFunctionThrowType(string $functionName, string $throwType): void
	{
		$this->functionThrowTypes[$functionName][] = $throwType;
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

		$throwTypes = array_map(function (string $className): ObjectType {
			return new ObjectType($className);
		}, $this->functionThrowTypes[$functionName]);

		return TypeCombinator::union(...$throwTypes);
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

		$throwTypeClasses = $this->methodThrowTypes[$className][$methodReflection->getName()];
		$throwTypes = array_map(function (string $className): ObjectType {
			return new ObjectType($className);
		}, $throwTypeClasses);

		return TypeCombinator::union(...$throwTypes);
	}

}
