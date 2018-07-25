<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use Pepakriz\PHPStanExceptionRules\Rules\ThrowsScope;
use Pepakriz\PHPStanExceptionRules\Type\ClosureWithThrowType;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope as PHPStanScope;
use PHPStan\Type\ClosureType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function is_array;

class Scope extends PHPStanScope
{

	public function getType(Expr $node): Type
	{
		$type = parent::getType($node);

		if (!$node instanceof Expr\Closure || !$type instanceof ClosureType) {
			return $type;
		}

		$throwTypeClasses = $node->getAttribute(ThrowsScope::CLOSURE_THROWS_ATTRIBUTE);
		if (!is_array($throwTypeClasses)) {
			return $type;
		}

		$throwTypes = [];
		foreach ($throwTypeClasses as $throwTypeClass) {
			$throwTypes[] = new ObjectType($throwTypeClass);
		}

		return new ClosureWithThrowType($type->getParameters(), $type->getReturnType(), $type->isVariadic(), TypeCombinator::union(...$throwTypes));
	}

}
