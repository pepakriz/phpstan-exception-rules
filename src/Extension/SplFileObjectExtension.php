<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use LogicException;
use Pepakriz\PHPStanExceptionRules\DynamicConstructorThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use RuntimeException;
use SplFileObject;
use function is_a;

class SplFileObjectExtension implements DynamicConstructorThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 */
	public function getThrowTypeFromConstructor(MethodReflection $methodReflection, New_ $newNode, Scope $scope): Type
	{
		if (is_a($methodReflection->getDeclaringClass()->getName(), SplFileObject::class, true)) {
			return new UnionType([
				new ObjectType(RuntimeException::class),
				new ObjectType(LogicException::class),
			]);
		}

		throw new UnsupportedClassException();
	}

}
