<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicFunctionExtension\DynamicFunctionExtension;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicMethodExtension\DynamicMethodExtension;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicStaticMethodExtension\DynamicStaticMethodExtension;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use RuntimeException;

class ThrowsPhpDocRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$throwsRule = new ThrowsPhpDocRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
					CheckedException::class,
				]
			),
			new DynamicThrowTypeService([
				new DynamicMethodExtension(),
			], [
				new DynamicStaticMethodExtension(),
			], [
				new DynamicFunctionExtension(),
			]),
			$this->createBroker()
		);

		return $throwsRule;
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

	public function testIterators(): void
	{
		$this->analyse(__DIR__ . '/data/iterators.php');
	}

	public function testCountable(): void
	{
		$this->analyse(__DIR__ . '/data/countables.php');
	}

	public function testJsonSerializable(): void
	{
		$this->analyse(__DIR__ . '/data/json-serializable.php');
	}

	public function testDynamicMethodExtension(): void
	{
		$this->analyse(__DIR__ . '/data/dynamic-method-extension.php');
	}

	public function testDynamicStaticMethodExtension(): void
	{
		$this->analyse(__DIR__ . '/data/dynamic-static-method-extension.php');
	}

	public function testDynamicFunctionExtension(): void
	{
		$this->analyse(__DIR__ . '/data/dynamic-function-extension.php');
	}

}
