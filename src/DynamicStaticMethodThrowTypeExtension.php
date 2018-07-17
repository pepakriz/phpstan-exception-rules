<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;

interface DynamicStaticMethodThrowTypeExtension
{

	public function getClass(): string;

	/**
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type;

}
