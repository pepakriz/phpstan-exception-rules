<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use Throwable;
use function sprintf;

class ValidThrowValueRule implements Rule
{

	/**
	 * @var bool
	 */
	private $reportMaybes;

	public function __construct(bool $reportMaybes)
	{
		$this->reportMaybes = $reportMaybes;
	}

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

		$throwableType = new ObjectType(Throwable::class);
		$isSuperType = $throwableType->isSuperTypeOf($type);

		if ($isSuperType->no()) {
			return [
				sprintf('Invalid type %s to throw.', $type->describe(VerbosityLevel::typeOnly())),
			];
		}

		if ($this->reportMaybes && $isSuperType->maybe()) {
			return [
				sprintf('Possibly invalid type %s to throw.', $type->describe(VerbosityLevel::typeOnly())),
			];
		}

		return [];
	}

}
