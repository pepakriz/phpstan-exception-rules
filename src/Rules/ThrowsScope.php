<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use function array_filter;
use function array_keys;
use function array_pop;
use function array_reverse;
use function is_a;

class ThrowsScope
{

	private const CAUGHT_EXCEPTIONS_ATTRIBUTE = '__CAUGHT_EXCEPTIONS_ATTRIBUTE__';

	/**
	 * @var int
	 */
	private $stackIndex = 0;

	/**
	 * @var array<Type|null>
	 */
	private $throwsAnnotationBlockStack = [null];

	/**
	 * @var bool[][]
	 */
	private $usedThrowsAnnotationsStack = [[]];

	/**
	 * @var TryCatch[][]
	 */
	private $tryCatchStack = [[]];

	public function enterToThrowsAnnotationBlock(?Type $type): void
	{
		$this->stackIndex++;

		$this->throwsAnnotationBlockStack[$this->stackIndex] = $type;
		$this->usedThrowsAnnotationsStack[$this->stackIndex] = [];
		$this->tryCatchStack[$this->stackIndex] = [];
	}

	/**
	 * @return string[]
	 */
	public function exitFromThrowsAnnotationBlock(): array
	{
		$usedThrowsAnnotations = $this->usedThrowsAnnotationsStack[$this->stackIndex];

		unset($this->throwsAnnotationBlockStack[$this->stackIndex]);
		unset($this->usedThrowsAnnotationsStack[$this->stackIndex]);
		unset($this->tryCatchStack[$this->stackIndex]);

		$this->stackIndex--;

		return array_keys($usedThrowsAnnotations);
	}

	public function isInGlobalScope(): bool
	{
		return $this->stackIndex === 0;
	}

	public function enterToTryCatch(TryCatch $tryCatch): void
	{
		$this->tryCatchStack[$this->stackIndex][] = $tryCatch;
	}

	public function exitFromTry(): void
	{
		array_pop($this->tryCatchStack[$this->stackIndex]);
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	public function filterExceptionsByUncaught(array $classes): array
	{
		return array_filter($classes, function (string $class): bool {
			return $this->isExceptionCaught($class) === false;
		});
	}

	/**
	 * @return string[]
	 */
	public function getCaughtExceptions(Name $name): array
	{
		return $name->getAttribute(self::CAUGHT_EXCEPTIONS_ATTRIBUTE, []);
	}

	private function isExceptionCaught(string $exceptionClassName): bool
	{
		foreach (array_reverse(array_keys($this->tryCatchStack[$this->stackIndex])) as $catchKey) {
			$catches = $this->tryCatchStack[$this->stackIndex][$catchKey];

			foreach ($catches->catches as $catch) {
				foreach ($catch->types as $type) {
					$catchType = $type->toString();
					$isCaught = is_a($exceptionClassName, $catchType, true);
					$isMaybeCaught = is_a($catchType, $exceptionClassName, true);
					if (!$isCaught && !$isMaybeCaught) {
						continue;
					}

					$caughtCheckedExceptions = $type->getAttribute(self::CAUGHT_EXCEPTIONS_ATTRIBUTE, []);
					$caughtCheckedExceptions[] = $exceptionClassName;
					$type->setAttribute(self::CAUGHT_EXCEPTIONS_ATTRIBUTE, $caughtCheckedExceptions);

					if ($isCaught) {
						return true;
					}
				}
			}
		}

		if ($this->throwsAnnotationBlockStack[$this->stackIndex] !== null) {
			$throwsExceptionClasses = TypeUtils::getDirectClassNames($this->throwsAnnotationBlockStack[$this->stackIndex]);
			foreach ($throwsExceptionClasses as $throwsExceptionClass) {
				if (is_a($exceptionClassName, $throwsExceptionClass, true)) {
					$this->usedThrowsAnnotationsStack[$this->stackIndex][$throwsExceptionClass] = true;
					return true;
				}
			}
		}

		return false;
	}

}
