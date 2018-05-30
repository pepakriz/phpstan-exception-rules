<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

class ValidThrowValueRuleTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		return [
			new ValidThrowValueRule(true),
		];
	}

	public function testRule(): void
	{
		$this->analyse(__DIR__ . '/data/throw-values.php');
	}

}
