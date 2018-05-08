<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Throwable;
use function sprintf;

class ValidThrowsPhpDocRule implements Rule
{

	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	/**
	 * @param ClassMethod $node
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			return [];
		}

		$methodReflection = $classReflection->getMethod($node->name, $scope);
		if (!$methodReflection instanceof ThrowableReflection) {
			return [];
		}

		$throwType = $methodReflection->getThrowType();
		if ($throwType === null) {
			return [];
		}

		if ((new ObjectType(Throwable::class))->accepts($throwType)) {
			return [];
		}

		return [
			sprintf('@throws phpdoc type must be instanceof %s. %s is given.', Throwable::class, $throwType->describe(VerbosityLevel::typeOnly())),
		];
	}

}
