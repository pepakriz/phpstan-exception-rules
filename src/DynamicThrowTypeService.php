<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Type\Type;
use function array_merge;
use function spl_object_hash;
use function sprintf;

class DynamicThrowTypeService
{

	/**
	 * @var DynamicMethodThrowTypeExtension[][]
	 */
	private $dynamicMethodThrowTypeExtensions = [];

	/**
	 * @var DynamicStaticMethodThrowTypeExtension[][]
	 */
	private $dynamicStaticMethodThrowTypeExtensions = [];

	/**
	 * @var DynamicFunctionThrowTypeExtension[]
	 */
	private $dynamicFunctionThrowTypeExtensions = [];

	/**
	 * @var bool[][]
	 */
	private $unsupportedFunctions = [];

	/**
	 * @param DynamicMethodThrowTypeExtension[] $dynamicMethodThrowTypeExtensions
	 * @param DynamicStaticMethodThrowTypeExtension[] $dynamicStaticMethodThrowTypeExtensions
	 * @param DynamicFunctionThrowTypeExtension[] $dynamicFunctionThrowTypeExtensions
	 */
	public function __construct(
		array $dynamicMethodThrowTypeExtensions,
		array $dynamicStaticMethodThrowTypeExtensions,
		array $dynamicFunctionThrowTypeExtensions
	)
	{
		foreach ($dynamicMethodThrowTypeExtensions as $dynamicMethodThrowTypeExtension) {
			$this->addDynamicMethodExtension($dynamicMethodThrowTypeExtension);
		}

		foreach ($dynamicStaticMethodThrowTypeExtensions as $dynamicStaticMethodThrowTypeExtension) {
			$this->addDynamicStaticMethodExtension($dynamicStaticMethodThrowTypeExtension);
		}

		foreach ($dynamicFunctionThrowTypeExtensions as $dynamicFunctionThrowTypeExtension) {
			$this->addDynamicFunctionExtension($dynamicFunctionThrowTypeExtension);
		}
	}

	private function addDynamicMethodExtension(DynamicMethodThrowTypeExtension $extension): void
	{
		$this->dynamicMethodThrowTypeExtensions[$extension->getClass()][] = $extension;
	}

	private function addDynamicStaticMethodExtension(DynamicStaticMethodThrowTypeExtension $extension): void
	{
		$this->dynamicStaticMethodThrowTypeExtensions[$extension->getClass()][] = $extension;
	}

	private function addDynamicFunctionExtension(DynamicFunctionThrowTypeExtension $extension): void
	{
		$this->dynamicFunctionThrowTypeExtensions[] = $extension;
	}

	public function getMethodThrowType(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		$classReflection = $methodReflection->getDeclaringClass();
		$classNames = array_merge(
			[$classReflection->getName()],
			$classReflection->getParentClassesNames(),
			$classReflection->getNativeReflection()->getInterfaceNames()
		);

		/** @var DynamicMethodThrowTypeExtension[] $extensions */
		$extensions = [];
		foreach ($classNames as $className) {
			if (!isset($this->dynamicMethodThrowTypeExtensions[$className])) {
				continue;
			}

			$extensions = array_merge($extensions, $this->dynamicMethodThrowTypeExtensions[$className]);
		}

		$functionName = sprintf('%s::%s', $classReflection->getName(), $methodReflection->getName());
		foreach ($extensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromMethodCall($methodReflection, $methodCall, $scope);
			} catch (UnsupportedFunctionException $e) {
				$this->unsupportedFunctions[$functionName][$extensionHash] = true;
			}
		}

		if ($methodReflection instanceof ThrowableReflection) {
			return $methodReflection->getThrowType();
		}

		return null;
	}

	public function getStaticMethodThrowType(MethodReflection $methodReflection, StaticCall $staticCall, Scope $scope): ?Type
	{
		$classReflection = $methodReflection->getDeclaringClass();
		$classNames = array_merge(
			[$classReflection->getName()],
			$classReflection->getParentClassesNames(),
			$classReflection->getNativeReflection()->getInterfaceNames()
		);

		/** @var DynamicStaticMethodThrowTypeExtension[] $extensions */
		$extensions = [];
		foreach ($classNames as $className) {
			if (!isset($this->dynamicStaticMethodThrowTypeExtensions[$className])) {
				continue;
			}

			$extensions = array_merge($extensions, $this->dynamicStaticMethodThrowTypeExtensions[$className]);
		}

		$functionName = sprintf('%s::%s', $classReflection->getName(), $methodReflection->getName());
		foreach ($extensions as $extension) {
			$extensionHash = spl_object_hash($extension);
			if (isset($this->unsupportedFunctions[$functionName][$extensionHash])) {
				continue;
			}

			try {
				return $extension->getThrowTypeFromStaticMethodCall($methodReflection, $staticCall, $scope);
			} catch (UnsupportedFunctionException $e) {
				$this->unsupportedFunctions[$functionName][$extensionHash] = true;
			}
		}

		if ($methodReflection instanceof ThrowableReflection) {
			return $methodReflection->getThrowType();
		}

		return null;
	}

	public function getFunctionThrowType(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): ?Type
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

		return $functionReflection->getThrowType();
	}

}
