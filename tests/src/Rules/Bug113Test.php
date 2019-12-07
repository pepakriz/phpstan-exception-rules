<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Rules\Data\CheckedException;
use Pepakriz\PHPStanExceptionRules\Rules\DynamicExtension\DynamicExtension;
use Pepakriz\PHPStanExceptionRules\RuleTestCase;
use PHPStan\Rules\Rule;
use ReflectionException;
use RuntimeException;

/**
 * @extends RuleTestCase<ThrowsPhpDocRule>
 */
class Bug113Test extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$defaultThrowTypeService = new DefaultThrowTypeService([], []);

		$extensions = [
			new DynamicExtension(),
			new DefaultThrowTypeExtension($defaultThrowTypeService),
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
			$defaultThrowTypeService,
			$this->createThrowsAnnotationReader(),
			$this->createBroker(),
			false,
			true,
			false,
			false,
			[]
		);
	}

	public function testBasicThrows(): void
	{
		$this->analyse(__DIR__ . '/data/bug113.php');
	}

}
