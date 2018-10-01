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

class DefaultThrowTypeExtension implements DynamicFunctionThrowTypeExtension, DynamicMethodThrowTypeExtension, DynamicConstructorThrowTypeExtension, DynamicStaticMethodThrowTypeExtension
{

	/**
	 * @var DefaultThrowTypeService
	 */
	private $defaultThrowTypeService;

	public function __construct(
		DefaultThrowTypeService $defaultThrowTypeService
	)
	{
		$this->defaultThrowTypeService = $defaultThrowTypeService;
	}

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $functionCall, Scope $scope): Type
	{
		return $this->defaultThrowTypeService->getFunctionThrowType($functionReflection);
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		return $this->defaultThrowTypeService->getMethodThrowType($methodReflection);
	}

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		return $this->defaultThrowTypeService->getMethodThrowType($methodReflection);
	}

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		return $this->defaultThrowTypeService->getConstructorThrowType($methodReflection);
	}

}
