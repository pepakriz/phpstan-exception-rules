<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\Rules\Data\BaseBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\SomeBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\WhitelistedException;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use RuntimeException;

class ThrowsRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new ThrowsRule([
			RuntimeException::class,
			WhitelistedException::class,
		], [
			BaseBlacklistedRuntimeException::class,
			SomeBlacklistedRuntimeException::class,
		]);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/throws-annotations.php'], [
			[
				'Missing @throws RuntimeException annotation',
				22,
			],
			[
				'Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation',
				23,
			],
			[
				'Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\WhitelistedException annotation',
				24,
			],
		]);
	}

}
