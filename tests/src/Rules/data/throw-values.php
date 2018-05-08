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

	throw 123; // error: Thrown value must be instanceof Throwable. int is given.
	throw new InvalidException(); // error: Thrown value must be instanceof Throwable. Pepakriz\PHPStanExceptionRules\Rules\Data\InvalidException is given.
	throw $invalidInterface; // error: Thrown value must be instanceof Throwable. Pepakriz\PHPStanExceptionRules\Rules\Data\InvalidInterfaceException is given.
};
