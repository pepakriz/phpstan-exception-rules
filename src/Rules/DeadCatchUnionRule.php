<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use function array_unique;
use function count;
use function is_a;

class DeadCatchUnionRule implements Rule
{

	public function getNodeType(): string
	{
		return Catch_::class;
	}

	/**
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node instanceof Catch_) {
			return [];
		}

		if (count($node->types) <= 1) {
			return [];
		}

		/** @var string[] $types */
		$types = [];
		foreach ($node->types as $type) {
			$types[] = $type->toString();
		}

		/** @var string[] $errors */
		$errors = [];
		foreach ($types as $key => $type) {
			foreach ($types as $nestedKey => $nestedType) {
				if ($key === $nestedKey) {
					continue;
				}

				if ($type === $nestedType) {
					$errors[] = "Type $type is caught twice";
					continue 2;
				}

				if (is_a($type, $nestedType, true)) {
					$errors[] = "Type $type is already caught by $nestedType";
					continue 2;
				}
			}
		}

		return array_unique($errors);
	}

}
