<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VerbosityLevel;
use PHPStan\Type\VoidType;
use function array_filter;
use function array_merge;
use function count;
use function sprintf;

/**
 * @implements Rule<ClassMethod>
 */
class ThrowsPhpDocInheritanceRule implements Rule
{

	/**
	 * @var CheckedExceptionService
	 */
	private $checkedExceptionService;

	/**
	 * @var DefaultThrowTypeService
	 */
	private $defaultThrowTypeService;

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
		DefaultThrowTypeService $defaultThrowTypeService,
		FileTypeMapper $fileTypeMapper,
		Broker $broker
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
		$this->defaultThrowTypeService = $defaultThrowTypeService;
		$this->fileTypeMapper = $fileTypeMapper;
		$this->broker = $broker;
	}

	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	/**
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		/** @var ClassMethod $node */
		$node = $node;

		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			return [];
		}

		$docComment = $node->getDocComment();
		if ($docComment === null) {
			return [];
		}

		$methodName = $node->name->toString();
		if ($methodName === '__construct') {
			return [];
		}

		$traitReflection = $scope->getTraitReflection();
		$traitName = $traitReflection !== null ? $traitReflection->getName() : null;

		$resolvedPhpDoc = $this->fileTypeMapper->getResolvedPhpDoc(
			$scope->getFile(),
			$classReflection->getName(),
			$traitName,
			$methodName,
			$docComment->getText()
		);

		$throwsTag = $resolvedPhpDoc->getThrowsTag();
		if ($throwsTag === null || $throwsTag->getType() instanceof VoidType) {
			return [];
		}

		$throwType = $throwsTag->getType();
		$parentClasses = array_filter(
			array_merge($classReflection->getInterfaces(), [$classReflection->getParentClass()])
		);

		$messages = [];
		foreach ($parentClasses as $parentClass) {
			try {
				$parentClassReflection = $this->broker->getClass($parentClass->getName());
			} catch (ClassNotFoundException $e) {
				throw new ShouldNotHappenException();
			}

			try {
				$methodReflection = $parentClassReflection->getMethod($methodName, $scope);
			} catch (MissingMethodFromReflectionException $e) {
				continue;
			}

			try {
				$parentThrowType = $this->defaultThrowTypeService->getMethodThrowType($methodReflection);
			} catch (UnsupportedClassException | UnsupportedFunctionException $e) {
				$parentThrowType = $methodReflection->getThrowType();
			}

			if ($parentThrowType === null || $parentThrowType instanceof VoidType) {
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
