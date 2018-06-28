<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\BaseBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException;
use Pepakriz\PHPStanExceptionRules\Rules\Data\SomeBlacklistedRuntimeException;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use RuntimeException;

class ThrowsPhpDocRuleTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		$throwsRule = new ThrowsPhpDocRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
					CheckedException::class,
				],
				[
					BaseBlacklistedRuntimeException::class,
					SomeBlacklistedRuntimeException::class,
				]
			),
			$this->createBroker()
		);

		return [
			$throwsRule->enableThrowsPhpDocChecker(),
			$throwsRule->enableTryCatchCrawler(),
			$throwsRule->enableTryEndCatchCrawler(),
			$throwsRule->enableCallPropagation(),
			$throwsRule->enableStaticCallPropagation(),
			$throwsRule->enableCallConstructorPropagation(),
			$throwsRule->enableMethodDeclaration(),
			$throwsRule->enableMethodEnd(),
			$throwsRule->enableCatchValidation(),
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

	public function testUnusedThrows(): void
	{
		$this->analyse(__DIR__ . '/data/unused-throws.php');
	}

	public function testUnusedCatches(): void
	{
		$this->analyse(__DIR__ . '/data/unused-catches.php');
	}

}
