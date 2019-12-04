<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<UnreachableCatchRule>
 */
class UnreachableCatchRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new UnreachableCatchRule($this->createBroker());
	}

	public function test(): void
	{
		$this->analyse(__DIR__ . '/data/unreachable-catches.php');
	}

}
