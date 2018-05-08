<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data;

use Exception;
use LogicException;
use RuntimeException;

class CustomLogicException extends LogicException {}
abstract class BaseRuntimeException extends RuntimeException {}
abstract class BaseBlacklistedRuntimeException extends BaseRuntimeException {}
class SomeRuntimeException extends BaseRuntimeException {}
class SomeBlacklistedRuntimeException extends BaseRuntimeException {}
class SomeInheritedBlacklistedRuntimeException extends BaseBlacklistedRuntimeException {}
class WhitelistedException extends Exception {}

class ThrowsAnnotationsClass
{

	public function wrongAnnotations(): void
	{
		throw new RuntimeException(); // error: Missing @throws RuntimeException annotation
		throw new SomeRuntimeException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\SomeRuntimeException annotation
		throw new WhitelistedException(); // error: Missing @throws Pepakriz\PHPStanExceptionRules\Rules\Data\WhitelistedException annotation
	}

	public function ignoreNonWhitelisted(): void
	{
		throw new LogicException();
		throw new CustomLogicException();
	}

	public function ignoreBlacklisted(): void
	{
		throw new SomeBlacklistedRuntimeException();
		throw new SomeInheritedBlacklistedRuntimeException();
	}

	/**
	 * @throws SomeRuntimeException
	 */
	public function correct(): void
	{
		throw new SomeRuntimeException();
	}

	/**
	 * @throws BaseRuntimeException
	 */
	public function correctAbstract(): void
	{
		throw new SomeRuntimeException();
	}

}
