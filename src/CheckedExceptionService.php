<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use function array_filter;
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

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function filterCheckedExceptions(array $classes): array
	{
		return array_filter($classes, function (string $class): bool {
			return $this->isCheckedException($class);
		});
	}

	public function isCheckedException(string $exceptionClassName): bool
	{
		foreach ($this->checkedExceptions as $whitelistedException) {
			if (is_a($exceptionClassName, $whitelistedException, true)) {
				return true;
			}
		}

		return false;
	}

}
