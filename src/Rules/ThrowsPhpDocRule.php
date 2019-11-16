<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use ArithmeticError;
use DivisionByZeroError;
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
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use ReflectionException;
use ReflectionMethod;
use function array_diff;
use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function in_array;
use function is_string;
use function preg_match;
use function sprintf;

class ThrowsPhpDocRule implements Rule
{

	private const ATTRIBUTE_HAS_CLASS_METHOD_END = '__HAS_CLASS_METHOD_END__';
	private const ATTRIBUTE_HAS_TRY_CATCH_END = '__HAS_TRY_CATCH_END__';

	private const ITERATOR_METHODS_WITHOUT_KEY = ['rewind', 'valid', 'current', 'next'];
	private const ITERATOR_METHODS = self::ITERATOR_METHODS_WITHOUT_KEY + ['key'];
	private const ITERATOR_AGGREGATE_METHODS = ['getIterator'];

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
	private $reportCheckedThrowsInGlobalScope;

	/**
	 * @var bool
	 */
	private $ignoreDescriptiveUncheckedExceptions;

	/**
	 * @var bool
	 */
	private $allowUnusedThrowsInImplementation;

	/** @var string[] */
	private $methodWhitelist;

	/**
	 * @param string[] $methodWhitelist
	 */
	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		DynamicThrowTypeService $dynamicThrowTypeService,
		DefaultThrowTypeService $defaultThrowTypeService,
		ThrowsAnnotationReader $throwsAnnotationReader,
		Broker $broker,
		bool $reportUnusedCatchesOfUncheckedExceptions,
		bool $reportCheckedThrowsInGlobalScope,
		bool $ignoreDescriptiveUncheckedExceptions,
		bool $allowUnusedThrowsInImplementation,
		array $methodWhitelist
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
		$this->dynamicThrowTypeService = $dynamicThrowTypeService;
		$this->defaultThrowTypeService = $defaultThrowTypeService;
		$this->throwsAnnotationReader = $throwsAnnotationReader;
		$this->broker = $broker;
		$this->throwsScope = new ThrowsScope();
		$this->reportUnusedCatchesOfUncheckedExceptions = $reportUnusedCatchesOfUncheckedExceptions;
		$this->reportCheckedThrowsInGlobalScope = $reportCheckedThrowsInGlobalScope;
		$this->ignoreDescriptiveUncheckedExceptions = $ignoreDescriptiveUncheckedExceptions;
		$this->allowUnusedThrowsInImplementation = $allowUnusedThrowsInImplementation;
		$this->methodWhitelist = $methodWhitelist;
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
			return $this->processTryCatch($node);
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

		if ($node instanceof Expr\YieldFrom) {
			return $this->processExprTraversing($node->expr, $scope, true);
		}

		if ($node instanceof Node\FunctionLike) {
			return $this->processFunction($node, $scope);
		}

		if ($node instanceof FunctionEnd) {
			$method = $scope->getFunction();

			if ($method instanceof MethodReflection && $this->isWhitelistedMethod($method)) {
				return $this->processWhitelistedMethod($method);
			}

			return $this->processFunctionEnd($scope);
		}

		if ($node instanceof Catch_) {
			return $this->processCatch($node);
		}

		if ($node instanceof Foreach_) {
			return $this->processExprTraversing($node->expr, $scope, $node->keyVar !== null);
		}

		if ($node instanceof FuncCall) {
			return $this->processFuncCall($node, $scope);
		}

		if ($node instanceof Expr\BinaryOp\Div || $node instanceof Expr\BinaryOp\Mod) {
			return $this->processDiv($node->right, $scope);
		}

		if ($node instanceof Expr\AssignOp\Div || $node instanceof Expr\AssignOp\Mod) {
			return $this->processDiv($node->expr, $scope);
		}

		if ($node instanceof Expr\BinaryOp\ShiftLeft || $node instanceof Expr\BinaryOp\ShiftRight) {
			return $this->processShift($node->right, $scope);
		}

		if ($node instanceof Expr\AssignOp\ShiftLeft || $node instanceof Expr\AssignOp\ShiftRight) {
			return $this->processShift($node->expr, $scope);
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processWhitelistedMethod(MethodReflection $methodReflection): array
	{
		if (!$methodReflection instanceof ThrowableReflection) {
			return [];
		}

		$throwType = $methodReflection->getThrowType();

		if ($throwType === null) {
			return [];
		}

		return array_map(
			static function (string $throwClass): string {
				return sprintf('Unused @throws %s annotation', $throwClass);
			},
			TypeUtils::getDirectClassNames($throwType)
		);
	}

	private function isWhitelistedMethod(MethodReflection $methodReflection): bool
	{
		$classReflection = $methodReflection->getDeclaringClass();

		foreach ($this->methodWhitelist as $className => $pattern) {
			if (!$classReflection->isSubclassOf($className)) {
				continue;
			}

			if (preg_match($pattern, $methodReflection->getName()) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private function processTryCatch(TryCatch $node): array
	{
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

		$isInGlobalScope = $this->throwsScope->isInGlobalScope();
		if (!$this->reportCheckedThrowsInGlobalScope && $isInGlobalScope) {
			return [];
		}

		return array_map(static function (string $exceptionClassName) use ($isInGlobalScope): string {
			if ($isInGlobalScope) {
				return sprintf('Throwing checked exception %s in global scope is prohibited', $exceptionClassName);
			}

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
	private function processExprTraversing(Expr $expr, Scope $scope, bool $useKey): array
	{
		$type = $scope->getType($expr);

		$messages = [];
		$classNames = TypeUtils::getDirectClassNames($type);
		foreach ($classNames as $className) {
			try {
				$classReflection = $this->broker->getClass($className);
			} catch (ClassNotFoundException $e) {
				continue;
			}

			if ($classReflection->isSubclassOf(Iterator::class)) {
				$messages = array_merge($messages, $this->processThrowTypesOnMethod(
					$expr,
					$useKey ? self::ITERATOR_METHODS : self::ITERATOR_METHODS_WITHOUT_KEY,
					$scope
				));
			} elseif ($classReflection->isSubclassOf(IteratorAggregate::class)) {
				$messages = array_merge($messages, $this->processThrowTypesOnMethod(
					$expr,
					self::ITERATOR_AGGREGATE_METHODS,
					$scope
				));
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

			if ($classReflection->getNativeReflection()->getMethod($methodReflection->getName())->isAbstract()) {
				return [];
			}
		}

		if ($methodReflection instanceof ThrowableReflection) {
			$this->throwsScope->enterToThrowsAnnotationBlock($methodReflection->getThrowType());
		}

		if (!$node->hasAttribute(self::ATTRIBUTE_HAS_CLASS_METHOD_END)) {
			$node->setAttribute(self::ATTRIBUTE_HAS_CLASS_METHOD_END, true);
			if ($node->stmts === null) {
				$node->stmts = [];
			}
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

		if ($this->allowUnusedThrowsInImplementation && $functionReflection instanceof MethodReflection) {
			$declaringClass = $functionReflection->getDeclaringClass();
			$nativeClassReflection = $declaringClass->getNativeReflection();
			$nativeMethodReflection = $nativeClassReflection->getMethod($functionReflection->getName());

			if ($this->isImplementation($nativeMethodReflection)) {
				return [];
			}
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

		if (!$this->ignoreDescriptiveUncheckedExceptions) {
			return $unusedThrows;
		}

		$throwsAnnotations = $this->throwsAnnotationReader->read($scope);

		return array_filter($unusedThrows, static function (string $type) use ($throwsAnnotations, $usedThrowsAnnotations): bool {
			return !in_array($type, $usedThrowsAnnotations, true)
				|| !isset($throwsAnnotations[$type])
				|| in_array('', $throwsAnnotations[$type], true);
		});
	}

	private function isImplementation(ReflectionMethod $reflection): bool
	{
		if ($reflection->isAbstract()) {
			return false;
		}

		try {
			$reflection->getPrototype();
		} catch (ReflectionException $exception) {
			return false;
		}

		return true;
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
	 * @return string[]
	 */
	private function processDiv(Expr $divisor, Scope $scope): array
	{
		$divisionByZero = false;
		$divisorType = $scope->getType($divisor);
		foreach (TypeUtils::getConstantScalars($divisorType) as $constantScalarType) {
			if ($constantScalarType->getValue() === 0) {
				$divisionByZero = true;
			}

			$divisorType = TypeCombinator::remove($divisorType, $constantScalarType);
		}

		if (!$divisorType instanceof NeverType) {
			return $this->processThrowsTypes(new ObjectType(ArithmeticError::class));
		}

		if ($divisionByZero) {
			return $this->processThrowsTypes(new ObjectType(DivisionByZeroError::class));
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	private function processShift(Expr $value, Scope $scope): array
	{
		$valueType = $scope->getType($value);
		foreach (TypeUtils::getConstantScalars($valueType) as $constantScalarType) {
			if ($constantScalarType->getValue() < 0) {
				return $this->processThrowsTypes(new ObjectType(ArithmeticError::class));
			}

			$valueType = TypeCombinator::remove($valueType, $constantScalarType);
		}

		if (!$valueType instanceof NeverType) {
			return $this->processThrowsTypes(new ObjectType(ArithmeticError::class));
		}

		return [];
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
		$calledOnType = $this->getClassType($class, $scope);
		if ($calledOnType === null) {
			return [];
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

	/**
	 * @param Name|Expr|ClassLike $class
	 */
	private function getClassType($class, Scope $scope): ?Type
	{
		if ($class instanceof ClassLike) {
			$className = $class->name;
			if ($className === null) {
				return null;
			}

			return new ObjectType($className->name);

		}

		if ($class instanceof Name) {
			return new ObjectType($scope->resolveName($class));
		}

		return $scope->getType($class);
	}

}
