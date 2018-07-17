<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use RuntimeException;

class UnsupportedClassException extends RuntimeException
{

	public function __construct()
	{
		parent::__construct('This class is not supported');
	}

}
