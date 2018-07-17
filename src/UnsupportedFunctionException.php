<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

class UnsupportedFunctionException extends RuntimeException
{

	public function __construct()
	{
		parent::__construct('This method or function is not supported');
	}

}
