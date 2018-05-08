<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

class ValidThrowsPhpDocRuleTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		return [
			new ValidThrowsPhpDocRule(),
		];
	}

	public function testRule(): void
	{
		$this->analyse(__DIR__ . '/data/throws-phpdoc.php');
	}

}
