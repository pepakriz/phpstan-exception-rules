<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

class DeadCatchUnionRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new DeadCatchUnionRule();
	}

	public function test(): void
	{
		$this->analyse(__DIR__ . '/data/dead-catch-union.php');
	}

}
