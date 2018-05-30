<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data;

use Exception;
use Throwable;

class InvalidException {};
interface InvalidInterfaceException {};
interface ValidInterfaceException extends Throwable {};

function () {
	/** @var ValidInterfaceException $validInterface */
	$validInterface = new Exception();

	/** @var InvalidInterfaceException $invalidInterface */
	$invalidInterface = new Exception();

	throw new Exception();
	throw $validInterface;

	throw 123; // error: Invalid type int to throw.
	throw new InvalidException(); // error: Possibly invalid type Pepakriz\PHPStanExceptionRules\Rules\Data\InvalidException to throw.
	throw $invalidInterface; // error: Possibly invalid type Pepakriz\PHPStanExceptionRules\Rules\Data\InvalidInterfaceException to throw.
};
