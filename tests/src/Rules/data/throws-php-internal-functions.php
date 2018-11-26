<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\PhpInternalFunctions;

use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;
use ReflectionZendExtension;

class Example
{

	private $property;

	public function testName(): void
	{
		new ReflectionClass(self::class);
		new ReflectionClass('undefinedClass'); // error: Missing @throws ReflectionException annotation

		new ReflectionProperty(self::class, 'property');
		new ReflectionProperty(self::class, 'undefinedProperty'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty('undefinedClass', 'property'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty('undefinedClass', 'undefinedProperty'); // error: Missing @throws ReflectionException annotation

		new ReflectionFunction('count');
		new ReflectionFunction('undefinedFunction'); // error: Missing @throws ReflectionException annotation

		new ReflectionZendExtension('unknownZendExtension'); // error: Missing @throws ReflectionException annotation
	}

}

