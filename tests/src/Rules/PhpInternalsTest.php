<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Extension\DateTimeExtension;
use Pepakriz\PHPStanExceptionRules\Extension\JsonEncodeDecodeExtension;
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
		$jsonEncodeDecodeExtension = new JsonEncodeDecodeExtension();
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
				[
					$jsonEncodeDecodeExtension,
				]
			),
			new DefaultThrowTypeService([], []),
			$this->createThrowsAnnotationReader(),
			$this->createBroker(),
			true,
			true,
			true
		);
	}

	public function testPhpInternalFunctions(): void
	{
		$this->analyse(__DIR__ . '/data/throws-php-internal-functions.php');
	}

	/**
	 * @requires PHP 7.3
	 */
	public function testPhpInternalFunctionsPhp73(): void
	{
		$this->analyse(__DIR__ . '/data/throws-php-internal-functions-php7.3.php');
	}

}
