<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\ThrowsAnnotationReader;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Broker\FunctionNotFoundException;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use function array_keys;
use function ltrim;
use function sprintf;
use function uksort;

class UselessThrowsPhpDocRule implements Rule
{

	/**
	 * @var Broker
	 */
	private $broker;

	/**
	 * @var ThrowsAnnotationReader
	 */
	private $throwsAnnotationReader;

	public function __construct(
		Broker $broker,
		ThrowsAnnotationReader $throwsAnnotationReader
	)
	{
		$this->broker = $broker;
		$this->throwsAnnotationReader = $throwsAnnotationReader;
	}

	public function getNodeType(): string
	{
		return FunctionLike::class;
	}

	/**
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$docComment = $node->getDocComment();
		if ($docComment === null) {
			return [];
		}

		if ($node instanceof ClassMethod) {
			$classReflection = $scope->getClassReflection();
			if ($classReflection === null) {
				throw new ShouldNotHappenException();
			}

			$methodName = $node->name->toString();
			try {
				$functionReflection = $classReflection->getMethod($methodName, $scope);
			} catch (MissingMethodFromReflectionException $e) {
				throw new ShouldNotHappenException();
			}

		} elseif ($node instanceof Function_) {
			$functionName = ltrim($scope->getNamespace() . '\\' . $node->name->toString(), '\\');
			try {
				$functionReflection = $this->broker->getFunction(new Node\Name\FullyQualified($functionName), $scope);
			} catch (FunctionNotFoundException $e) {
				throw new ShouldNotHappenException();
			}

		} else {
			return [];
		}

		$throwsAnnotations = $this->throwsAnnotationReader->readByReflection($functionReflection, $scope);

		try {
			return $this->checkUselessThrows($throwsAnnotations);
		} catch (ClassNotFoundException $exception) {
			return [];
		}
	}

	/**
	 * @param string[][] $throwsAnnotations
	 * @return string[]
	 *
	 * @throws ClassNotFoundException
	 */
	private function checkUselessThrows(array $throwsAnnotations): array
	{
		/** @var string[] $errors */
		$errors = [];

		$this->sortThrowsAnnotationsHierarchically($throwsAnnotations);

		/** @var bool[] $usefulThrows */
		$usefulThrows = [];
		foreach ($throwsAnnotations as $exceptionClass => $descriptions) {
			foreach ($descriptions as $description) {
				if (
					isset($usefulThrows[$exceptionClass])
					|| (
						$description === '' && $this->isSubtypeOfUsefulThrows($exceptionClass, array_keys($usefulThrows))
					)
				) {
					$errors[] = sprintf('Useless @throws %s annotation', $exceptionClass);
				}

				$usefulThrows[$exceptionClass] = true;
			}
		}

		return $errors;
	}

	/**
	 * @param string[] $usefulThrows
	 *
	 * @throws ClassNotFoundException
	 */
	private function isSubtypeOfUsefulThrows(string $exceptionClass, array $usefulThrows): bool
	{
		$classReflection = $this->broker->getClass($exceptionClass);

		foreach ($usefulThrows as $usefulThrow) {
			if ($classReflection->isSubclassOf($usefulThrow)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[][] $throwsAnnotations
	 *
	 * @throws ClassNotFoundException
	 */
	private function sortThrowsAnnotationsHierarchically(array &$throwsAnnotations): void
	{
		uksort($throwsAnnotations, function (string $leftClass, string $rightClass): int {
			$leftReflection = $this->broker->getClass($leftClass);
			$rightReflection = $this->broker->getClass($rightClass);

			// Ensure canonical class names
			$leftClass = $leftReflection->getName();
			$rightClass = $rightReflection->getName();

			if ($leftClass === $rightClass) {
				return 0;
			}

			if ($leftReflection->isSubclassOf($rightClass)) {
				return 1;
			}

			if ($rightReflection->isSubclassOf($leftClass)) {
				return -1;
			}

			// Doesn't matter, sort consistently on classname
			return $leftClass <=> $rightClass;
		});
	}

}
