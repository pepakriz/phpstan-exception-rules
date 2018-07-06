<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;
use function count;
use function sprintf;

class ThrowsPhpDocInheritanceRule implements Rule
{

	/**
	 * @var CheckedExceptionService
	 */
	private $checkedExceptionService;

	/**
	 * @var FileTypeMapper
	 */
	private $fileTypeMapper;

	/**
	 * @var Broker
	 */
	private $broker;

	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		FileTypeMapper $fileTypeMapper,
		Broker $broker
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
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

		$parentClasses = $classReflection->getInterfaces();
		$parentClass = $classReflection->getParentClass();
		if ($parentClass !== false) {
			$parentClasses[] = $parentClass;
		}

		$messages = [];
		foreach ($parentClasses as $parentClass) {
			$parentClassReflection = $this->broker->getClass($parentClass->getName());
			if (!$parentClassReflection->hasMethod($methodName)) {
				continue;
			}

			$methodReflection = $parentClassReflection->getMethod($methodName, $scope);
			if (!$methodReflection instanceof ThrowableReflection) {
				continue;
			}

			$parentThrowType = $methodReflection->getThrowType();
			if ($parentThrowType === null) {
				$messages[] = sprintf(
					'PHPDoc tag @throws with type %s is not compatible with parent',
					$throwType->describe(VerbosityLevel::typeOnly())
				);

				continue;
			}

			$parentThrowType = $this->filterUnchecked($parentThrowType);
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

	private function filterUnchecked(Type $type): ?Type
	{
		$exceptionClasses = TypeUtils::getDirectClassNames($type);
		$exceptionClasses = $this->checkedExceptionService->filterCheckedExceptions($exceptionClasses);

		if (count($exceptionClasses) === 0) {
			return null;
		}

		$types = [];
		foreach ($exceptionClasses as $exceptionClass) {
			$types[] = new ObjectType($exceptionClass);
		}

		return TypeCombinator::union(...$types);
	}

}
