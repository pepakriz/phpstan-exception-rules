<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use PHPStan\Type\FileTypeMapper;
use RuntimeException;

class ThrowsPhpDocInheritanceRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new ThrowsPhpDocInheritanceRule(
			new CheckedExceptionService(
				[
					RuntimeException::class,
				]
			),
			self::getContainer()->getByType(FileTypeMapper::class),
			$this->createBroker()
		);
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
