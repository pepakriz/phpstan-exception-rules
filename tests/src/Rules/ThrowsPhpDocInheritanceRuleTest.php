<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use LogicException;
use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use PHPStan\Type\FileTypeMapper;
use RuntimeException;

class ThrowsPhpDocInheritanceRuleTest extends RuleTestCase
{

	/**
	 * @return Rule[]
	 */
	protected function getRules(): array
	{
		$throwsRule = new ThrowsPhpDocInheritanceRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
				],
				[
					LogicException::class,
				]
			),
			self::getContainer()->getByType(FileTypeMapper::class),
			$this->createBroker()
		);

		return [
			$throwsRule,
		];
	}

	public function testInheritance(): void
	{
		$this->analyse(__DIR__ . '/data/throws-inheritance.php');
	}

	public function testInheritanceWithInterfaces(): void
	{
		$this->analyse(__DIR__ . '/data/throws-inheritance-interfaces.php');
	}

}
