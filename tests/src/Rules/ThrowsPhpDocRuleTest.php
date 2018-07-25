<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\CoreFunctionsDynamicThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\AnonymousFunctions\AnonymousFunctionsClassDynamicMethodExtension;
use Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicFunctionExtension\DynamicFunctionExtension;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicMethodExtension\DynamicMethodExtension;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicStaticMethodExtension\DynamicStaticMethodExtension;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use RuntimeException;

class ThrowsPhpDocRuleTest extends RuleTestCase
{

	/**
	 * @var bool
	 */
	private $reportUnusedCatchesOfUncheckedExceptions = false;

	protected function getRule(): Rule
	{
		$dynamicExtension = new AnonymousFunctionsClassDynamicMethodExtension();

		$throwsRule = new ThrowsPhpDocRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
					CheckedException::class,
				]
			),
			new DynamicThrowTypeService([
				new DynamicMethodExtension(),
				$dynamicExtension,
			], [
				new DynamicStaticMethodExtension(),
				$dynamicExtension,
			], [
				new DynamicFunctionExtension(),
				new CoreFunctionsDynamicThrowTypeExtension(),
			]),
			$this->createBroker(),
			$this->reportUnusedCatchesOfUncheckedExceptions
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

	public function testAllUnusedCatches(): void
	{
		$this->reportUnusedCatchesOfUncheckedExceptions = true;
		$this->analyse(__DIR__ . '/data/unused-catches-all.php');
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

	public function testUnsupportedCatchCheckedAndUnchecked(): void
	{
		$this->analyse(__DIR__ . '/data/unsupported-catch.php');
	}

	public function testClosures(): void
	{
		$this->analyse(__DIR__ . '/data/anonymous-functions.php');
	}

}
