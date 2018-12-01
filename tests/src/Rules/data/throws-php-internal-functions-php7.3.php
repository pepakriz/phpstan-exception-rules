<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\PhpInternalFunctionsPhp73;

use function json_decode;
use function json_encode;
use function rand;
use const JSON_THROW_ON_ERROR;

class Example
{

	public function testJsonDecode(string $data, int $options): void
	{
		json_decode('');
		json_decode($data, true, 512, JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_decode($data, true, 512, $options); // error: Missing @throws JsonException annotation
		json_decode('{}', true, 512, JSON_THROW_ON_ERROR);

		json_decode($data, true, 512, rand(0, 1) ? JSON_BIGINT_AS_STRING : JSON_OBJECT_AS_ARRAY);
		json_decode($data, true, 512, rand(0, 1) ? JSON_BIGINT_AS_STRING : JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_decode($data, true, 512, rand(0, 1) ? JSON_BIGINT_AS_STRING : $options); // error: Missing @throws JsonException annotation

		json_decode(rand(0, 1) === 0 ? '{}' : '', true, 512, JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_decode(rand(0, 1) === 0 ? '{{' : '', true, 512, JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_decode(rand(0, 1) === 0 ? '{}' : '[]', true, 512, JSON_THROW_ON_ERROR);
	}

	public function testJsonEncode(array $data, array $nextData, int $options): void
	{
		json_encode('');
		json_encode($data, JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_encode($data, $options); // error: Missing @throws JsonException annotation
		json_encode(123, JSON_THROW_ON_ERROR);

		json_encode($data, rand(0, 1) ? JSON_PRESERVE_ZERO_FRACTION : JSON_UNESCAPED_UNICODE);
		json_encode($data, rand(0, 1) ? JSON_PRESERVE_ZERO_FRACTION : JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_encode($data, rand(0, 1) ? JSON_PRESERVE_ZERO_FRACTION : $options); // error: Missing @throws JsonException annotation

		json_encode(rand(0, 1) === 0 ? $data : '', JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_encode(rand(0, 1) === 0 ? $data : $nextData, JSON_THROW_ON_ERROR); // error: Missing @throws JsonException annotation
		json_encode(rand(0, 1) === 0 ? 123 : 'abc', JSON_THROW_ON_ERROR);
	}

}

