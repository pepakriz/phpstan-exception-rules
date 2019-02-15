<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\ThrowsInGlobalScope;

use LogicException;
use RuntimeException;

if (false) {
	throw new LogicException();
	throw new RuntimeException(); // error: Throwing checked exception RuntimeException in global scope is prohibited

	try {
		throw new RuntimeException();
	} catch (RuntimeException $e) {
		// ignore
	}
}
