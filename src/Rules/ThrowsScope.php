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
	 * @var array<Type|null>
	 */
	private $throwsAnnotationBlock = [];

	/**
	 * @var int
	 */
	private $throwsAnnotationBlockIndex = -1;

	/**
	 * @var bool[][]
	 */
	private $usedThrowsAnnotations = [];

	/**
	 * @var TryCatch[][]
	 */
	private $tryCatchQueue = [];

	public function enterToThrowsAnnotationBlock(?Type $type): void
	{
		$this->throwsAnnotationBlockIndex++;

		$this->throwsAnnotationBlock[$this->throwsAnnotationBlockIndex] = $type;
		$this->usedThrowsAnnotations[$this->throwsAnnotationBlockIndex] = [];
		$this->tryCatchQueue[$this->throwsAnnotationBlockIndex] = [];
	}

	/**
	 * @return string[]
	 */
	public function exitFromThrowsAnnotationBlock(): array
	{
		$usedThrowsAnnotations = $this->usedThrowsAnnotations[$this->throwsAnnotationBlockIndex];

		unset($this->throwsAnnotationBlock[$this->throwsAnnotationBlockIndex]);
		unset($this->usedThrowsAnnotations[$this->throwsAnnotationBlockIndex]);
		unset($this->tryCatchQueue[$this->throwsAnnotationBlockIndex]);

		$this->throwsAnnotationBlockIndex--;

		return array_keys($usedThrowsAnnotations);
	}

	public function enterToTryCatch(TryCatch $tryCatch): void
	{
		$this->tryCatchQueue[$this->throwsAnnotationBlockIndex][] = $tryCatch;
	}

	public function exitFromTry(): void
	{
		array_pop($this->tryCatchQueue[$this->throwsAnnotationBlockIndex]);
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
		foreach (array_reverse(array_keys($this->tryCatchQueue[$this->throwsAnnotationBlockIndex])) as $catchKey) {
			$catches = $this->tryCatchQueue[$this->throwsAnnotationBlockIndex][$catchKey];

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

		if ($this->throwsAnnotationBlock[$this->throwsAnnotationBlockIndex] !== null) {
			$throwsExceptionClasses = TypeUtils::getDirectClassNames($this->throwsAnnotationBlock[$this->throwsAnnotationBlockIndex]);
			foreach ($throwsExceptionClasses as $throwsExceptionClass) {
				if (is_a($exceptionClassName, $throwsExceptionClass, true)) {
					$this->usedThrowsAnnotations[$this->throwsAnnotationBlockIndex][$throwsExceptionClass] = true;
					return true;
				}
			}
		}

		return false;
	}

}
