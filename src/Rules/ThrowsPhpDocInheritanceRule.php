<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\VerbosityLevel;
use function class_implements;
use function get_parent_class;
use function sprintf;

class ThrowsPhpDocInheritanceRule implements Rule
{

	/**
	 * @var FileTypeMapper
	 */
	private $fileTypeMapper;

	/**
	 * @var Broker
	 */
	private $broker;

	public function __construct(
		FileTypeMapper $fileTypeMapper,
		Broker $broker
	)
	{
		$this->fileTypeMapper = $fileTypeMapper;
		$this->broker = $broker;
	}

	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	/**
	 * @param ClassMethod $node
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			return [];
		}

		$docComment = $node->getDocComment();
		if ($docComment === null) {
			return [];
		}

		$traitReflection = $scope->getTraitReflection();
		$traitName = $traitReflection !== null ? $traitReflection->getName() : null;

		$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			$classReflection->getName(),
			$traitName,
			$docComment->getText()
		);

		$throwsTag = $resolvedPhpDoc->getThrowsTag();
		if ($throwsTag === null) {
			return [];
		}

		$throwType = $throwsTag->getType();
		$methodName = $node->name->toString();

		$parentClass = get_parent_class($classReflection->getName());
		$parentClasses = class_implements($classReflection->getName());
		if ($parentClass !== false) {
			$parentClasses += [$parentClass];
		}

		$messages = [];
		foreach ($parentClasses as $parentClass) {
			$parentClassReflection = $this->broker->getClass($parentClass);
			if (!$parentClassReflection->hasMethod($methodName)) {
				continue;
			}

			$methodReflection = $parentClassReflection->getMethod($methodName, $scope);
			if (!$methodReflection instanceof ThrowableReflection) {
				continue;
			}

			$parentThrowType = $methodReflection->getThrowType();
			if ($parentThrowType === null) {
				continue;
			}

			if ($parentThrowType->isSuperTypeOf($throwType)->yes()) {
				continue;
			}

			$messages[] = sprintf(
				'PHPDoc tag @throws with type %s is not compatible with parent %s',
				$throwType->describe(VerbosityLevel::typeOnly()),
				$parentThrowType->describe(VerbosityLevel::typeOnly())
			);
		}

		return $messages;
	}

}
