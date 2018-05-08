<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\MixedType;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VerbosityLevel;
use Throwable;
use function is_a;
use function sprintf;

class ValidThrowValueRule implements Rule
{

	public function getNodeType(): string
	{
		return Throw_::class;
	}

	/**
	 * @param Throw_ $node
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$type = $scope->getType($node->expr);
		if ($type instanceof MixedType && !$type->isExplicitMixed()) {
			return [];
		}

		if ($type instanceof TypeWithClassName) {
			if (is_a($type->getClassName(), Throwable::class, true)) {
				return [];
			}
		}

		return [
			sprintf('Thrown value must be instanceof %s. %s is given.', Throwable::class, $type->describe(VerbosityLevel::typeOnly())),
		];
	}

}
