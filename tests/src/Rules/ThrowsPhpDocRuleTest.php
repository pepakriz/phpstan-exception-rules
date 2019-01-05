<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicExtension\DynamicExtension;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PharData;
use PHPStan\Rules\Rule;
use ReflectionException;
use RuntimeException;

class ThrowsPhpDocRuleTest extends RuleTestCase
{

	/**
	 * @var bool
	 */
	private $reportUnusedCatchesOfUncheckedExceptions = false;

	protected function getRule(): Rule
	{
		$extensions = [
			new DynamicExtension(),
			new DefaultThrowTypeExtension(
				new DefaultThrowTypeService([
					PharData::class => [
						'extractTo' => [
							RuntimeException::class,
						],
					],
				], [])
			),
		];

		return new ThrowsPhpDocRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
					CheckedException::class,
					ReflectionException::class,
				]
			),
			new DynamicThrowTypeService(
				$extensions,
				$extensions,
				$extensions,
				$extensions
			),
			$this->createBroker(),
			$this->reportUnusedCatchesOfUncheckedExceptions
		);
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

	public function testDynamicExtension(): void
	{
		$this->analyse(__DIR__ . '/data/dynamic-extension.php');
	}

	public function testUnsupportedCatchCheckedAndUnchecked(): void
	{
		$this->analyse(__DIR__ . '/data/unsupported-catch.php');
	}

	public function testAnonymClass(): void
	{
		$this->analyse(__DIR__ . '/data/throws-anonym-class.php');
	}

}
