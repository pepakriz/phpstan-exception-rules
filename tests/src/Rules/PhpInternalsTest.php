<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Extension\DateTimeExtension;
use Pepakriz\PHPStanExceptionRules\Extension\ReflectionExtension;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use Throwable;

class PhpInternalsTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$reflectionClassExtension = new ReflectionExtension($this->createBroker());
		$dateTimeExtension = new DateTimeExtension();
		return new ThrowsPhpDocRule(
			new CheckedExceptionService(
				[
					Throwable::class,
				]
			),
			new DynamicThrowTypeService(
				[],
				[],
				[
					$reflectionClassExtension,
					$dateTimeExtension,
				],
				[]
			),
			$this->createBroker(),
			true
		);
	}

	public function testPhpInternalFunctions(): void
	{
		$this->analyse(__DIR__ . '/data/throws-php-internal-functions.php');
	}

}
