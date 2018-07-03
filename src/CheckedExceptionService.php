<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use function is_a;

class CheckedExceptionService
{

	/**
	 * @var string[]
	 */
	private $checkedExceptions;

	/**
	 * @param string[] $checkedExceptions
	 */
	public function __construct(
		array $checkedExceptions
	)
	{
		$this->checkedExceptions = $checkedExceptions;
	}

	public function isExceptionClassWhitelisted(string $exceptionClassName): bool
	{
		foreach ($this->checkedExceptions as $whitelistedException) {
			if (is_a($exceptionClassName, $whitelistedException, true)) {
				return true;
			}
		}

		return false;
	}

}
