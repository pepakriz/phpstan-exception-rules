<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\ThrowsAnnotationReader;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\FunctionNotFoundException;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use function array_keys;
use function is_a;
use function ltrim;
use function sprintf;

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

		return $this->checkUselessThrows($throwsAnnotations);
	}

	/**
	 * @param string[][] $throwsAnnotations
	 * @return string[]
	 */
	private function checkUselessThrows(array $throwsAnnotations): array
	{
		/** @var string[] $errors */
		$errors = [];

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
	 */
	private function isSubtypeOfUsefulThrows(string $exceptionClass, array $usefulThrows): bool
	{
		foreach ($usefulThrows as $usefulThrow) {
			if (is_a($exceptionClass, $usefulThrow, true)) {
				return true;
			}
		}

		return false;
	}

}
