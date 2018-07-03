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

	private const CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE = '__CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE__';

	/**
	 * @var Type|null
	 */
	private $throwsAnnotationBlock;

	/**
	 * @var bool[]
	 */
	private $usedThrowsAnnotations = [];

	/**
	 * @var TryCatch[]
	 */
	private $tryCatchQueue = [];

	public function enterToThrowsAnnotationBlock(?Type $type): void
	{
		$this->throwsAnnotationBlock = $type;
		$this->usedThrowsAnnotations = [];
	}

	/**
	 * @return string[]
	 */
	public function exitFromThrowsAnnotationBlock(): array
	{
		$this->throwsAnnotationBlock = null;
		$usedThrowsAnnotations = $this->usedThrowsAnnotations;
		$this->usedThrowsAnnotations = [];

		return array_keys($usedThrowsAnnotations);
	}

	public function enterToTryCatch(TryCatch $tryCatch): void
	{
		$this->tryCatchQueue[] = $tryCatch;
	}

	public function exitFromTry(): void
	{
		array_pop($this->tryCatchQueue);
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
		return $name->getAttribute(self::CAUGHT_CHECKED_EXCEPTIONS_ATTRIBUTE, []);
	}

	private function isExceptionCaught(string $exceptionClassName): bool
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

		if ($this->throwsAnnotationBlock !== null) {
			$throwsExceptionClasses = TypeUtils::getDirectClassNames($this->throwsAnnotationBlock);
			foreach ($throwsExceptionClasses as $throwsExceptionClass) {
				if (is_a($exceptionClassName, $throwsExceptionClass, true)) {
					$this->usedThrowsAnnotations[$throwsExceptionClass] = true;
					return true;
				}
			}
		}

		return false;
	}

}
