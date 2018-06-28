<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TryCatch;
use function array_keys;
use function array_pop;
use function array_reverse;
use function is_a;

class ThrowsScope
{

	private const CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE = '__CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE__';

	/**
	 * @var TryCatch[]
	 */
	private $tryCatchQueue = [];

	public function enterToTryCatch(TryCatch $tryCatch): void
	{
		$this->tryCatchQueue[] = $tryCatch;
	}

	public function exitFromTry(): void
	{
		array_pop($this->tryCatchQueue);
	}

	public function isExceptionCaught(string $exceptionClassName): bool
	{
		foreach (array_reverse(array_keys($this->tryCatchQueue)) as $catchKey) {
			$catches = $this->tryCatchQueue[$catchKey];

			foreach ($catches->catches as $catch) {
				foreach ($catch->types as $type) {
					if (is_a($exceptionClassName, $type->toString(), true)) {
						$caughtCheckedExceptions = $type->getAttribute(self::CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE, []);
						$caughtCheckedExceptions[] = $exceptionClassName;
						$type->setAttribute(self::CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE, $caughtCheckedExceptions);

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	public function getCaughtExceptions(Name $name): array
	{
		return $name->getAttribute(self::CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE, []);
	}

}
