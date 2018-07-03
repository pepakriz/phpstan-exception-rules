<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\JsonSerializable;

use JsonSerializable;
use RuntimeException;
use function json_encode;

class TestClass
{

	public function callJsonEncode(): void
	{
		$serializableObject = new JsonSerializableClass();
		json_encode($serializableObject); // error: Missing @throws RuntimeException annotation
	}

}

class JsonSerializableClass implements JsonSerializable
{

	/**
	 * @throws RuntimeException
	 */
	public function jsonSerialize()
	{
		throw new RuntimeException();
	}

}
