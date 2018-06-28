<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\CatchReassign;

use RuntimeException;
use Throwable;

class CatchReassignClass
{

	/**
	 * @throws RuntimeException
	 */
	public function reassignInTry(): void
	{
		try {
			$e = new RuntimeException();
			throw $e;

		} catch (Throwable $e) {
			throw $e;
		}
	}

	/**
	 * @throws RuntimeException
	 */
	public function reassignInCatch(): void
	{
		try {
			$e = new RuntimeException();
			throw $e;

		} catch (Throwable $e) {
			$e = $e; // error: Reassigning variable $e is prohibited in catch block

			throw $e;
		}
	}

}
