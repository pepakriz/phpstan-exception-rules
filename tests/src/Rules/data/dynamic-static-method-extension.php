<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DynamicStaticMethodExtension;

use Pepakriz\PHPStanExceptionRules\DynamicStaticMethodThrowTypeExtension;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use RuntimeException;

class DynamicStaticMethodExtension implements DynamicStaticMethodThrowTypeExtension
{

	public function getClass(): string
	{
		return TestClass::class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'throwDynamicException';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		return new ObjectType(RuntimeException::class);
	}

}

class TestClass
{

	public function test()
	{
		self::blankMethod();
		self::throwDynamicException(); // error: Missing @throws RuntimeException annotation
	}

	public static function blankMethod(): void
	{

	}

	public static function throwDynamicException(): void
	{

	}

}
