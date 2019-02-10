<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Iterator;
use IteratorAggregate;
use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DefaultThrowTypeService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Node\FunctionEnd;
use Pepakriz\PHPStanExceptionRules\Node\TryCatchTryEnd;
use Pepakriz\PHPStanExceptionRules\ThrowsAnnotationReader;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Broker\FunctionNotFoundException;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use ReflectionException;
use ReflectionFunction;
use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function in_array;
use function is_string;
use function sprintf;

class ThrowsPhpDocRule implements Rule
{

	private const ATTRIBUTE_HAS_CLASS_METHOD_END = '__HAS_CLASS_METHOD_END__';
	private const ATTRIBUTE_HAS_TRY_CATCH_END = '__HAS_TRY_CATCH_END__';

	/**
	 * @var CheckedExceptionService
	 */
	private $checkedExceptionService;

	/**
	 * @var DynamicThrowTypeService
	 */
	private $dynamicThrowTypeService;

	/**
	 * @var DefaultThrowTypeService
	 */
	private $defaultThrowTypeService;

	/**
	 * @var ThrowsAnnotationReader
	 */
	private $throwsAnnotationReader;

	/**
	 * @var Broker
	 */
	private $broker;

	/**
	 * @var ThrowsScope
	 */
	private $throwsScope;

	/**
	 * @var bool
	 */
	private $reportUnusedCatchesOfUncheckedExceptions;

	/**
	 * @var bool
	 */
	private $ignoreDescriptiveUncheckedExceptions;

	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		DynamicThrowTypeService $dynamicThrowTypeService,
		DefaultThrowTypeService $defaultThrowTypeService,
		ThrowsAnnotationReader $throwsAnnotationReader,
		Broker $broker,
		bool $reportUnusedCatchesOfUncheckedExceptions,
		bool $ignoreDescriptiveUncheckedExceptions
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
		$this->dynamicThrowTypeService = $dynamicThrowTypeService;
		$this->defaultThrowTypeService = $defaultThrowTypeService;
		$this->throwsAnnotationReader = $throwsAnnotationReader;
		$this->broker = $broker;
		$this->throwsScope = new ThrowsScope();
		$this->reportUnusedCatchesOfUncheckedExceptions = $reportUnusedCatchesOfUncheckedExceptions;
		$this->ignoreDescriptiveUncheckedExceptions = $ignoreDescriptiveUncheckedExceptions;
	}

	public function getNodeType(): string
	{
		return Node::class;
	}

	/**
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if ($node instanceof TryCatch) {
			return $this->processTryCatch($node, $scope);
		}

		if ($node instanceof TryCatchTryEnd) {
			return $this->processTryCatchTryEnd();
		}

		if ($node instanceof Throw_) {
			return $this->processThrow($node, $scope);
		}

		if ($node instanceof MethodCall) {
			return $this->processMethodCall($node, $scope);
		}

		if ($node instanceof StaticCall) {
			return $this->processStaticCall($node, $scope);
		}

		if ($node instanceof New_) {
			return $this->processNew($node, $scope);
		}

		if ($node instanceof Node\FunctionLike) {
			return $this->processFunction($node, $scope);
		}

		if ($node instanceof FunctionEnd) {
			return $this->processFunctionEnd($scope);
		}

		if ($node instanceof Catch_) {
			return $this->processCatch($node);
		}

		if ($node instanceof Foreach_) {
			return $this->processForeach($node, $scope);
		}

		if ($node instanceof FuncCall) {
			return $this->processFuncCall($node, $scope);
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processTryCatch(TryCatch $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		$methodReflection = $scope->getFunction();
		if ($classReflection === null || $methodReflection === null) {
			return [];
		}

		$this->throwsScope->enterToTryCatch($node);

		if (!$node->hasAttribute(self::ATTRIBUTE_HAS_TRY_CATCH_END)) {
			$node->setAttribute(self::ATTRIBUTE_HAS_TRY_CATCH_END, true);
			$node->stmts[] = new TryCatchTryEnd($node);
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processTryCatchTryEnd(): array
	{
		$this->throwsScope->exitFromTry();

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processThrow(Throw_ $node, Scope $scope): array
	{
		$exceptionType = $scope->getType($node->expr);
		$exceptionClassNames = TypeUtils::getDirectClassNames($exceptionType);
		$exceptionClassNames = $this->throwsScope->filterExceptionsByUncaught($exceptionClassNames);
		$exceptionClassNames = $this->checkedExceptionService->filterCheckedExceptions($exceptionClassNames);

		return array_map(static function (string $exceptionClassName): string {
			return sprintf('Missing @throws %s annotation', $exceptionClassName);
		}, $exceptionClassNames);
	}

	/**
	 * @return string[]
	 */
	private function processMethodCall(MethodCall $node, Scope $scope): array
	{
		$methodName = $node->name;
		if (!$methodName instanceof Identifier) {
			return [];
		}

		$targetType = $scope->getType($node->var);
		$targetClassNames = TypeUtils::getDirectClassNames($targetType);

		$throwTypes = [];
		foreach ($targetClassNames as $targetClassName) {
			try {
				$targetClassReflection = $this->broker->getClass($targetClassName);
			} catch (ClassNotFoundException $e) {
				continue;
			}

			try {
				$targetMethodReflection = $targetClassReflection->getMethod($methodName->toString(), $scope);
			} catch (MissingMethodFromReflectionException $e) {
				try {
					$targetMethodReflection = $targetClassReflection->getMethod('__call', $scope);
				} catch (MissingMethodFromReflectionException $e) {
					continue;
				}
			}

			$throwType = $this->dynamicThrowTypeService->getMethodThrowType($targetMethodReflection, $node, $scope);
			if ($throwType instanceof VoidType) {
				continue;
			}

			$throwTypes[] = $throwType;
		}

		if (count($throwTypes) === 0) {
			return [];
		}

		return $this->processThrowsTypes(TypeCombinator::union(...$throwTypes));
	}

	/**
	 * @return string[]
	 */
	private function processStaticCall(StaticCall $node, Scope $scope): array
	{
		$methodName = $node->name;
		if ($methodName instanceof Identifier) {
			$methodName = $methodName->toString();
		}

		if (!is_string($methodName)) {
			return [];
		}

		$throwTypes = [];
		$targetMethodReflections = $this->getMethodReflections($node->class, [$methodName], $scope);

		if (count($targetMethodReflections) === 0) {
			$targetMethodReflections = $this->getMethodReflections($node->class, ['__callStatic'], $scope);
		}

		foreach ($targetMethodReflections as $targetMethodReflection) {
			$throwType = $this->dynamicThrowTypeService->getStaticMethodThrowType($targetMethodReflection, $node, $scope);
			if ($throwType instanceof VoidType) {
				continue;
			}

			$throwTypes[] = $throwType;
		}

		if (count($throwTypes) === 0) {
			return [];
		}

		return $this->processThrowsTypes(TypeCombinator::union(...$throwTypes));
	}

	/**
	 * @return string[]
	 */
	private function processNew(New_ $node, Scope $scope): array
	{
		$throwTypes = [];
		$targetMethodReflections = $this->getMethodReflections($node->class, ['__construct'], $scope);
		foreach ($targetMethodReflections as $targetMethodReflection) {
			$throwType = $this->dynamicThrowTypeService->getConstructorThrowType($targetMethodReflection, $node, $scope);
			if ($throwType instanceof VoidType) {
				continue;
			}

			$throwTypes[] = $throwType;
		}

		if (count($throwTypes) === 0) {
			return [];
		}

		return $this->processThrowsTypes(TypeCombinator::union(...$throwTypes));
	}

	/**
	 * @return string[]
	 */
	private function processForeach(Foreach_ $node, Scope $scope): array
	{
		$type = $scope->getType($node->expr);

		$messages = [];
		$classNames = TypeUtils::getDirectClassNames($type);
		foreach ($classNames as $className) {
			try {
				$classReflection = $this->broker->getClass($className);
			} catch (ClassNotFoundException $e) {
				continue;
			}

			if ($classReflection->isSubclassOf(Iterator::class)) {
				$iteratorMethods = ['rewind', 'valid', 'current', 'next'];
				if ($node->keyVar !== null) {
					$iteratorMethods[] = 'key';
				}
				$messages = array_merge($messages, $this->processThrowTypesOnMethod($node->expr, $iteratorMethods, $scope));
			} elseif ($classReflection->isSubclassOf(IteratorAggregate::class)) {
				$messages = array_merge($messages, $this->processThrowTypesOnMethod($node->expr, ['getIterator'], $scope));
			}
		}

		return array_unique($messages);
	}

	/**
	 * @return string[]
	 */
	private function processFunction(Node\FunctionLike $node, Scope $scope): array
	{
		if (
			!$node instanceof ClassMethod
			&& !$node instanceof Node\Stmt\Function_
		) {
			return [];
		}

		if ($node->stmts === null) {
			$node->stmts = [];
		}

		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			try {
				$methodReflection = $this->broker->getFunction(new Name($node->name->toString()), $scope);
			} catch (FunctionNotFoundException $e) {
				return [];
			}
		} else {
			try {
				$methodReflection = $classReflection->getMethod($node->name->toString(), $scope);
			} catch (MissingMethodFromReflectionException $e) {
				throw new ShouldNotHappenException();
			}
		}

		if ($methodReflection instanceof ThrowableReflection) {
			$this->throwsScope->enterToThrowsAnnotationBlock($methodReflection->getThrowType());
		}

		if (!$node->hasAttribute(self::ATTRIBUTE_HAS_CLASS_METHOD_END)) {
			$node->setAttribute(self::ATTRIBUTE_HAS_CLASS_METHOD_END, true);
			$node->stmts[] = new FunctionEnd($node);
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processFunctionEnd(Scope $scope): array
	{
		$usedThrowsAnnotations = $this->throwsScope->exitFromThrowsAnnotationBlock();

		$functionReflection = $scope->getFunction();
		if ($functionReflection === null) {
			return [];
		}

		$classReflection = $scope->getClassReflection();
		if ($classReflection !== null && ($classReflection->isInterface() || $classReflection->isAbstract())) {
			return [];
		}

		if (!$functionReflection instanceof ThrowableReflection) {
			return [];
		}

		$throwType = $functionReflection->getThrowType();
		if ($throwType === null) {
			return [];
		}

		$declaredThrows = TypeUtils::getDirectClassNames($throwType);
		$unusedThrows = $this->filterUnusedExceptions($declaredThrows, $usedThrowsAnnotations, $scope);

		$messages = [];
		foreach ($unusedThrows as $unusedClass) {
			$messages[] = sprintf('Unused @throws %s annotation', $unusedClass);
		}

		return $messages;
	}

	/**
	 * @param string[] $declaredThrows
	 * @param string[] $usedThrowsAnnotations
	 *
	 * @return string[]
	 */
	private function filterUnusedExceptions(array $declaredThrows, array $usedThrowsAnnotations, Scope $scope): array
	{
		$checkedThrowsAnnotations = $this->checkedExceptionService->filterCheckedExceptions($usedThrowsAnnotations);
		$unusedThrows = array_diff($declaredThrows, $checkedThrowsAnnotations);

		$functionReflection = $scope->getFunction();
		if ($functionReflection === null) {
			return $unusedThrows;
		}

		try {
			if ($functionReflection instanceof MethodReflection) {
				$defaultThrowsType = $functionReflection->getName() === '__construct' ?
					$this->defaultThrowTypeService->getConstructorThrowType($functionReflection) :
					$this->defaultThrowTypeService->getMethodThrowType($functionReflection);
			} else {
				$defaultThrowsType = $this->defaultThrowTypeService->getFunctionThrowType($functionReflection);
			}
		} catch (UnsupportedClassException | UnsupportedFunctionException $exception) {
			$defaultThrowsType = new VoidType();
		}

		$unusedThrows = array_diff($unusedThrows, TypeUtils::getDirectClassNames($defaultThrowsType));

		try {
			if ($functionReflection instanceof MethodReflection) {
				$nativeClassReflection = $functionReflection->getDeclaringClass()->getNativeReflection();
				$nativeFunctionReflection = $nativeClassReflection->getMethod($functionReflection->getName());

			} else {
				$nativeFunctionReflection = new ReflectionFunction($functionReflection->getName());
			}
		} catch (ReflectionException $exception) {
			return $unusedThrows;
		}

		if (!$this->ignoreDescriptiveUncheckedExceptions) {
			return $unusedThrows;
		}

		$throwsAnnotations = $this->throwsAnnotationReader->read($nativeFunctionReflection);

		return array_filter($unusedThrows, static function (string $type) use ($throwsAnnotations, $usedThrowsAnnotations): bool {
			return !in_array($type, $usedThrowsAnnotations, true)
				|| !isset($throwsAnnotations[$type])
				|| in_array('', $throwsAnnotations[$type], true);
		});
	}

	/**
	 * @return string[]
	 */
	private function processCatch(Catch_ $node): array
	{
		$messages = [];

		foreach ($node->types as $type) {
			$caughtExceptions = $this->throwsScope->getCaughtExceptions($type);

			$caughtChecked = [];
			foreach ($caughtExceptions as $caughtException) {
				if (!$this->checkedExceptionService->isCheckedException($caughtException)) {
					continue;
				}

				$caughtChecked[] = $caughtException;
			}

			if (!$this->checkedExceptionService->isCheckedException($type->toString())) {
				foreach ($caughtChecked as $caughtCheckedException) {
					$messages[] = sprintf(
						'Catching checked exception %s as unchecked %s is not supported properly in this moment. Eliminate checked exceptions by custom catch statement.',
						$caughtCheckedException,
						$type->toString()
					);
				}
			}

			$exceptionClass = $type->toString();
			if (
				!$this->reportUnusedCatchesOfUncheckedExceptions
				&& !$this->checkedExceptionService->isCheckedException($exceptionClass)
			) {
				continue;
			}

			if (!$this->reportUnusedCatchesOfUncheckedExceptions) {
				$caughtExceptions = $this->checkedExceptionService->filterCheckedExceptions($caughtExceptions);
			}

			if (count($caughtExceptions) > 0) {
				continue;
			}

			$messages[] = sprintf('%s is never thrown in the corresponding try block', $exceptionClass);
		}

		return $messages;
	}

	/**
	 * @return string[]
	 */
	private function processFuncCall(FuncCall $node, Scope $scope): array
	{
		$nodeName = $node->name;
		if (!$nodeName instanceof Name) {
			return []; // closure call
		}

		$functionName = $nodeName->toString();
		if ($functionName === 'count') {
			return $this->processThrowTypesOnMethod($node->args[0]->value, ['count'], $scope);
		}

		if ($functionName === 'iterator_count') {
			return $this->processThrowTypesOnMethod($node->args[0]->value, ['rewind', 'valid', 'next'], $scope);
		}

		if ($functionName === 'iterator_to_array') {
			return $this->processThrowTypesOnMethod($node->args[0]->value, ['rewind', 'valid', 'current', 'key', 'next'], $scope);
		}

		if ($functionName === 'iterator_apply') {
			return $this->processThrowTypesOnMethod($node->args[0]->value, ['rewind', 'valid', 'next'], $scope);
		}

		try {
			$functionReflection = $this->broker->getFunction($nodeName, $scope);
		} catch (FunctionNotFoundException $e) {
			return [];
		}

		$throwType = $this->dynamicThrowTypeService->getFunctionThrowType($functionReflection, $node, $scope);

		if ($functionName === 'json_encode') {
			$throwType = TypeCombinator::union(
				$throwType,
				...$this->getThrowTypesOnMethod($node->args[0]->value, ['jsonSerialize'], $scope)
			);
		}

		return $this->processThrowsTypes($throwType);
	}

	/**
	 * @param Name|Expr|ClassLike $class
	 * @param string[] $methods
	 * @return Type[]
	 */
	private function getThrowTypesOnMethod($class, array $methods, Scope $scope): array
	{
		/** @var Type[] $throwTypes */
		$throwTypes = [];
		$targetMethodReflections = $this->getMethodReflections($class, $methods, $scope);
		foreach ($targetMethodReflections as $targetMethodReflection) {
			if (!$targetMethodReflection instanceof ThrowableReflection) {
				continue;
			}

			$throwType = $targetMethodReflection->getThrowType();
			if ($throwType === null) {
				continue;
			}

			$throwTypes[] = $throwType;
		}

		if (count($throwTypes) === 0) {
			return [];
		}

		return $throwTypes;
	}

	/**
	 * @param Name|Expr|ClassLike $class
	 * @param string[] $methods
	 * @return string[]
	 */
	private function processThrowTypesOnMethod($class, array $methods, Scope $scope): array
	{
		$throwTypes = $this->getThrowTypesOnMethod($class, $methods, $scope);

		return $this->processThrowsTypes(TypeCombinator::union(...$throwTypes));
	}

	/**
	 * @return string[]
	 */
	private function processThrowsTypes(Type $targetThrowType): array
	{
		$targetExceptionClasses = TypeUtils::getDirectClassNames($targetThrowType);
		$targetExceptionClasses = $this->throwsScope->filterExceptionsByUncaught($targetExceptionClasses);
		$targetExceptionClasses = $this->checkedExceptionService->filterCheckedExceptions($targetExceptionClasses);

		return array_map(static function (string $targetExceptionClass): string {
			return sprintf('Missing @throws %s annotation', $targetExceptionClass);
		}, $targetExceptionClasses);
	}

	/**
	 * @param Name|Expr|ClassLike $class
	 * @param string[] $methodNames
	 * @return MethodReflection[]
	 */
	private function getMethodReflections(
		$class,
		array $methodNames,
		Scope $scope
	): array
	{
		if ($class instanceof ClassLike) {
			$className = $class->name;
			if ($className === null) {
				return [];
			}

			$calledOnType = new ObjectType($className->name);

		} elseif ($class instanceof Name) {
			$calledOnType = new ObjectType($scope->resolveName($class));
		} else {
			$calledOnType = $scope->getType($class);
		}

		$methodReflections = [];
		$classNames = TypeUtils::getDirectClassNames($calledOnType);
		foreach ($classNames as $className) {
			try {
				$classReflection = $this->broker->getClass($className);
			} catch (ClassNotFoundException $e) {
				continue;
			}

			foreach ($methodNames as $methodName) {
				try {
					$methodReflections[] = $classReflection->getMethod($methodName, $scope);
				} catch (MissingMethodFromReflectionException $e) {
					continue;
				}
			}
		}

		return $methodReflections;
	}

}
