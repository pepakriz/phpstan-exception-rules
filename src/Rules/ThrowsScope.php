<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\ShouldNotHappenException;
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
	public const CLOSURE_THROWS_ATTRIBUTE = '__CLOSURE_THROWS_ATTRIBUTE__';

	/**
	 * @var Type|null
	 */
	private $throwsAnnotationBlock;

	/**
	 * @var bool[]
	 */
	private $usedThrowsAnnotations = [];

	/**
	 * @var TryCatch[][]
	 */
	private $tryCatchQueue = [];

	/**
	 * @var Type[][]
	 */
	private $closureThrowTypeStack = [];

	/**
	 * @var int
	 */
	private $closureThrowTypeStackIndex = -1;

	/**
	 * @var bool
	 */
	private $inClosure = false;

	/**
	 * ThrowsScope constructor.
	 */
	public function __construct()
	{
		$this->tryCatchQueue[$this->closureThrowTypeStackIndex] = [];
	}

	public function enterToThrowsAnnotationBlock(?Type $type): void
	{
		$this->throwsAnnotationBlock = $type;
		$this->usedThrowsAnnotations = [];
	}

	public function enterToClosure(): void
	{
		$this->closureThrowTypeStack[++$this->closureThrowTypeStackIndex] = [];
		$this->tryCatchQueue[$this->closureThrowTypeStackIndex] = [];
		$this->inClosure = true;
	}

	public function exitFromClosure(Closure $node): void
	{
		$types = array_pop($this->closureThrowTypeStack);
		if ($types === null) {
			throw new ShouldNotHappenException();
		}

		$this->closureThrowTypeStackIndex--;
		if ($this->closureThrowTypeStackIndex === -1) {
			$this->inClosure = false;
		}

		$node->setAttribute(self::CLOSURE_THROWS_ATTRIBUTE, array_keys($types));
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
		$this->tryCatchQueue[$this->closureThrowTypeStackIndex][] = $tryCatch;
	}

	public function exitFromTry(): void
	{
		array_pop($this->tryCatchQueue[$this->closureThrowTypeStackIndex]);
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
		foreach (array_reverse(array_keys($this->tryCatchQueue[$this->closureThrowTypeStackIndex])) as $catchKey) {
			$catches = $this->tryCatchQueue[$this->closureThrowTypeStackIndex][$catchKey];

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

		if ($this->inClosure) {
			$this->closureThrowTypeStack[$this->closureThrowTypeStackIndex][$exceptionClassName] = true;
			return true;
		} elseif ($this->throwsAnnotationBlock !== null) {
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

	public function isInClosure(): bool
	{
		return $this->closureThrowTypeStackIndex >= 0;
	}

}
