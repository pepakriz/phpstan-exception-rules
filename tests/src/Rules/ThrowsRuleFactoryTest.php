<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\Rules\Data\BaseBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\SomeBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\WhitelistedException;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use RuntimeException;

class ThrowsRuleFactoryTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		$throwsRule = new ThrowsRuleFactory([
			RuntimeException::class,
			WhitelistedException::class,
		], [
			BaseBlacklistedRuntimeException::class,
			SomeBlacklistedRuntimeException::class,
		], $this->createBroker());

		return [
			$throwsRule->createThrow(),
			$throwsRule->createTryCatch(),
			$throwsRule->createMethodCall(),
		];
	}

	public function testBasicThrows(): void
	{
		$this->analyse(__DIR__ . '/data/throws-annotations.php');
	}

	public function testTryCatch(): void
	{
		$this->analyse(__DIR__ . '/data/try-catch.php');
	}

}
