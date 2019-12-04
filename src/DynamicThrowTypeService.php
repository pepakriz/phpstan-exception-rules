<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;
use function spl_object_hash;
use function sprintf;

class DynamicThrowTypeService
{

	private const VARIANT_CONSTRUCTOR = 'constructor';
	private const VARIANT_METHOD = 'method';

	/**
	 * @var DynamicMethodThrowTypeExtension[]
	 */
	private $dynamicMethodThrowTypeExtensions = [];

	/**
	 * @var DynamicStaticMethodThrowTypeExtension[]
	 */
	private $dynamicStaticMethodThrowTypeExtensions = [];

	/**
	 * @var DynamicConstructorThrowTypeExtension[]
	 */
	private $dynamicConstructorThrowTypeExtensions = [];

	/**
	 * @var DynamicFunctionThrowTypeExtension[]
	 */
	private $dynamicFunctionThrowTypeExtensions = [];

	/**
	 * @var bool[][][]
	 */
	private $unsupportedClasses = [];

	/**
	 * @var bool[][]
	 */
	private $unsupportedFunctions = [];

	/**
	 * @param DynamicMethodThrowTypeExtension[] $dynamicMethodThrowTypeExtensions
	 * @param DynamicStaticMethodThrowTypeExtension[] $dynamicStaticMethodThrowTypeExtensions
	 * @param DynamicConstructorThrowTypeExtension[] $dynamicConstructorThrowTypeExtensions
	 * @param DynamicFunctionThrowTypeExtension[] $dynamicFunctionThrowTypeExtensions
	 */
	public function __construct(
		array $dynamicMethodThrowTypeExtensions,
		array $dynamicStaticMethodThrowTypeExtensions,
		array $dynamicConstructorThrowTypeExtensions,
		array $dynamicFunctionThrowTypeExtensions
	)
	{
		foreach ($dynamicMethodThrowTypeExtensions as $dynamicMethodThrowTypeExtension) {
			$this->addDynamicMethodExtension($dynamicMethodThrowTypeExtension);
		}

		foreach ($dynamicStaticMethodThrowTypeExtensions as $dynamicStaticMethodThrowTypeExtension) {
			$this->addDynamicStaticMethodExtension($dynamicStaticMethodThrowTypeExtension);
		}

		foreach ($dynamicConstructorThrowTypeExtensions as $dynamicConstructorThrowTypeExtension) {
			$this->addDynamicConstructorExtension($dynamicConstructorThrowTypeExtension);
		}

		foreach ($dynamicFunctionThrowTypeExtensions as $dynamicFunctionThrowTypeExtension) {
			$this->addDynamicFunctionExtension($dynamicFunctionThrowTypeExtension);
		}
	}

	private function addDynamicMethodExtension(DynamicMethodThrowTypeExtension $extension): void
	{
		$this->dynamicMethodThrowTypeExtensions[] = $extension;
	}

	private function addDynamicStaticMethodExtension(DynamicStaticMethodThrowTypeExtension $extension): void
	{
		$this->dynamicStaticMethodThrowTypeExtensions[] = $extension;
	}

	private function addDynamicConstructorExtension(DynamicConstructorThrowTypeExtension $extension): void
	{
		$this->dynamicConstructorThrowTypeExtensions[] = $extension;
	}

	private function addDynamicFunctionExtension(DynamicFunctionThrowTypeExtension $extension): void
	{
		$this->dynamicFunctionThrowTypeExtensions[] = $extension;
	}

	public function getMethodThrowType(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$classReflection = $methodReflection->getDeclaringClass();

		$functionName = sprintf('%s::%s', $classReflection->getName(), $methodReflection->getName());
		foreach ($this->dynamicMethodThrowTypeExtensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedClasses[self::VARIANT_METHOD][$classReflection->getName()][$extensionHash])) {
				continue;
			}

			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromMethodCall($methodReflection, $methodCall, $scope);
			} catch (UnsupportedClassException $e) {
				$this->unsupportedClasses[self::VARIANT_METHOD][$classReflection->getName()][$extensionHash] = true;
			} catch (UnsupportedFunctionException $e) {
				$this->unsupportedFunctions[$functionName][$extensionHash] = true;
			}
		}

		$throwType = $methodReflection->getThrowType();

		return $throwType ?? new VoidType();
	}

	public function getStaticMethodThrowType(MethodReflection $methodReflection, StaticCall $staticCall, Scope $scope): Type
	{
		$classReflection = $methodReflection->getDeclaringClass();

		$functionName = sprintf('%s::%s', $classReflection->getName(), $methodReflection->getName());
		foreach ($this->dynamicStaticMethodThrowTypeExtensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedClasses[self::VARIANT_METHOD][$classReflection->getName()][$extensionHash])) {
				continue;
			}

			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromStaticMethodCall($methodReflection, $staticCall, $scope);
			} catch (UnsupportedClassException $e) {
				$this->unsupportedClasses[self::VARIANT_METHOD][$classReflection->getName()][$extensionHash] = true;
			} catch (UnsupportedFunctionException $e) {
				$this->unsupportedFunctions[$functionName][$extensionHash] = true;
			}
		}

		$throwType = $methodReflection->getThrowType();

		return $throwType ?? new VoidType();
	}

	public function getConstructorThrowType(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		$classReflection = $methodReflection->getDeclaringClass();

		$functionName = sprintf('%s::%s', $classReflection->getName(), $methodReflection->getName());
		foreach ($this->dynamicConstructorThrowTypeExtensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedClasses[self::VARIANT_CONSTRUCTOR][$classReflection->getName()][$extensionHash])) {
				continue;
			}

			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromConstructor($methodReflection, $newNode, $scope);
			} catch (UnsupportedClassException $e) {
				$this->unsupportedClasses[self::VARIANT_CONSTRUCTOR][$classReflection->getName()][$extensionHash] = true;
			}
		}

		$throwType = $methodReflection->getThrowType();

		return $throwType ?? new VoidType();
	}

	public function getFunctionThrowType(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		$functionName = $functionReflection->getName();
		foreach ($this->dynamicFunctionThrowTypeExtensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromFunctionCall($functionReflection, $functionCall, $scope);
			} catch (UnsupportedFunctionException $e) {
				$this->unsupportedFunctions[$functionName][$extensionHash] = true;
			}
		}

		$throwType = $functionReflection->getThrowType();

		return $throwType ?? new VoidType();
	}

}
