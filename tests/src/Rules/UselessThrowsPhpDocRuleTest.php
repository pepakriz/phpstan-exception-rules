<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;

/**
 * @extends RuleTestCase<UselessThrowsPhpDocRule>
 */
class UselessThrowsPhpDocRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new UselessThrowsPhpDocRule(
			$this->createBroker([]),
			$this->createThrowsAnnotationReader()
		);
	}

	public function testBasicUselessThrows(): void
	{
		$this->analyseFile(__DIR__ . '/data/useless-throws.php');
	}

}
