<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceOverriding\BaseThrowsAnnotations;
use Pepakriz\PHPStanExceptionRules\Rules\Data\InheritanceOverriding\ConcreteException;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use PHPStan\Type\FileTypeMapper;
use RuntimeException;

/**
 * @extends RuleTestCase<ThrowsPhpDocInheritanceRule>
 */
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
			new DefaultThrowTypeService([
				BaseThrowsAnnotations::class => [
					'foo' => [
						RuntimeException::class,
					],
					'bar' => [
						ConcreteException::class,
					],
				],
			], []),
			self::getContainer()->getByType(FileTypeMapper::class),
			$this->createBroker()
		);
	}

	public function testInheritance(): void
	{
		$this->analyseFile(__DIR__ . '/data/throws-inheritance.php');
	}

	public function testInheritanceWithInterfaces(): void
	{
		$this->analyseFile(__DIR__ . '/data/throws-inheritance-interfaces.php');
	}

	public function testInheritanceWithOverriding(): void
	{
		$this->analyseFile(__DIR__ . '/data/throws-inheritance-overriding.php');
	}

}
