<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;

interface DynamicStaticMethodThrowTypeExtension
{

	public function getClass(): string;

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool;

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type;

}
