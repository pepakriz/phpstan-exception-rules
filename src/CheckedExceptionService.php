<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use LogicException;
use function array_filter;
use function count;
use function is_a;

class CheckedExceptionService
{

	/**
	 * @var string[]|null
	 */
	private $checkedExceptions;

	/**
	 * @var string[]
	 */
	private $uncheckedExceptions;

	/**
	 * @param string[] $checkedExceptions
	 * @param string[] $uncheckedExceptions
	 */
	public function __construct(
		array $checkedExceptions,
		array $uncheckedExceptions = []
	)
	{
		$checkedExceptionsCounter = count($checkedExceptions);
		$uncheckedExceptionsCounter = count($uncheckedExceptions);
		if ($checkedExceptionsCounter > 0 && $uncheckedExceptionsCounter > 0) {
			throw new LogicException('$checkedExceptions and $uncheckedExceptions cannot be configured at the same time');
		}

		$this->checkedExceptions = $checkedExceptionsCounter > 0 ? $checkedExceptions : null;
		$this->uncheckedExceptions = $uncheckedExceptions;
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
		if ($this->checkedExceptions !== null) {
			foreach ($this->checkedExceptions as $checkedException) {
				if (is_a($exceptionClassName, $checkedException, true)) {
					return true;
				}
			}

			return false;
		}

		foreach ($this->uncheckedExceptions as $uncheckedException) {
			if (is_a($exceptionClassName, $uncheckedException, true)) {
				return false;
			}
		}

		return true;
	}

}
