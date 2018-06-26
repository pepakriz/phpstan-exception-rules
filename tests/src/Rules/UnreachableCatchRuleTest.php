<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

class UnreachableCatchRuleTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		return [
			new UnreachableCatchRule($this->createBroker()),
		];
	}

	public function test(): void
	{
		$this->analyse(__DIR__ . '/data/unreachable-catches.php');
	}

}
