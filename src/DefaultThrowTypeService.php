<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VoidType;
use function array_map;
use function count;

class DefaultThrowTypeService
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
	public function getFunctionThrowType(FunctionReflection $functionReflection): Type
	{
		$functionName = $functionReflection->getName();
		if (!isset($this->functionThrowTypes[$functionName])) {
			throw new UnsupportedFunctionException();
		}

		return $this->functionThrowTypes[$functionName];
	}

	/**
	 * @throws UnsupportedClassException
	 */
	public function getConstructorThrowType(MethodReflection $methodReflection): Type
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
	public function getMethodThrowType(MethodReflection $methodReflection): Type
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
