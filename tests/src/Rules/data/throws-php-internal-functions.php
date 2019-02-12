<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\PhpInternalFunctions;

use DateTime;
use DateTimeImmutable;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use ReflectionProperty;
use ReflectionZendExtension;
use stdClass;
use Throwable;
use function rand;

class ValueObject
{

	private $property;

	private $secondProperty;

}

class Example
{

	private $property;

	private $secondProperty;

	public function testReflection(): void
	{
		new ReflectionObject(new stdClass());

		new ReflectionClass(self::class);
		new ReflectionClass(static::class);
		new ReflectionClass('undefinedClass'); // error: Missing @throws ReflectionException annotation

		new ReflectionProperty(self::class, 'property');
		new ReflectionProperty(static::class, 'property');
		new ReflectionProperty(self::class, 'undefinedProperty'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty('undefinedClass', 'property'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty('undefinedClass', 'undefinedProperty'); // error: Missing @throws ReflectionException annotation

		new ReflectionFunction('count');
		new ReflectionFunction('undefinedFunction'); // error: Missing @throws ReflectionException annotation

		new ReflectionZendExtension('json');
		new ReflectionZendExtension('unknownZendExtension'); // error: Missing @throws ReflectionException annotation

		new ReflectionClass(rand(0, 1) === 0 ? self::class : Throwable::class);
		new ReflectionClass(rand(0, 1) === 0 ? static::class : Throwable::class); // error: Missing @throws ReflectionException annotation
		new ReflectionClass(rand(0, 1) === 0 ? self::class : null); // error: Missing @throws ReflectionException annotation
		new ReflectionClass(rand(0, 1) === 0 ? self::class : 'undefinedClass'); // error: Missing @throws ReflectionException annotation

		new ReflectionProperty(rand(0, 1) === 0 ? self::class : ValueObject::class, rand(0, 1) === 0 ? 'property' : 'secondProperty');
		new ReflectionProperty(rand(0, 1) === 0 ? static::class : ValueObject::class, rand(0, 1) === 0 ? 'property' : 'secondProperty'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty(rand(0, 1) === 0 ? self::class : null, rand(0, 1) === 0 ? 'property' : 'secondProperty'); // error: Missing @throws ReflectionException annotation
		new ReflectionProperty(rand(0, 1) === 0 ? self::class : Throwable::class, rand(0, 1) === 0 ? 'property' : 'undefinedProperty'); // error: Missing @throws ReflectionException annotation

		new ReflectionFunction(rand(0, 1) === 0 ? 'count' : 'sort');
		new ReflectionFunction(rand(0, 1) === 0 ? 'count' : 'undefinedFunction'); // error: Missing @throws ReflectionException annotation
	}

	/**
	 * @requires PHP 7.3
	 */
	public function testDateTime(): void
	{
		new DateTime();
		new DateTime(null);
		new DateTime('2018-01-01');
		new DateTime('invalid format'); // error: Missing @throws Exception annotation

		new DateTimeImmutable();
		new DateTimeImmutable(null);
		new DateTimeImmutable('2018-01-01');
		new DateTimeImmutable('invalid format'); // error: Missing @throws Exception annotation

		new DateTime(rand(0, 1) === 0 ? '2018-01-01' : '2019-01-01');
		new DateTime(rand(0, 1) === 0 ? '2018-01-01' : null);
		new DateTime(rand(0, 1) === 0 ? '2018-01-01' : 123); // error: Missing @throws Exception annotation
	}

}

