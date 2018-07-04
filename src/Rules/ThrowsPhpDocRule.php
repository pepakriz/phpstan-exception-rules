<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Iterator;
use IteratorAggregate;
use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use Pepakriz\PHPStanExceptionRules\Node\ClassMethodEnd;
use Pepakriz\PHPStanExceptionRules\Node\TryCatchTryEnd;
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
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use ReflectionMethod;
use function array_diff;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function is_string;
use function sprintf;

class ThrowsPhpDocRule implements Rule
{

	/**
	 * @var CheckedExceptionService
	 */
	private $checkedExceptionService;

	/**
	 * @var DynamicThrowTypeService
	 */
	private $dynamicThrowTypeService;

	/**
	 * @var Broker
	 */
	private $broker;

	/**
	 * @var ThrowsScope
	 */
	private $throwsScope;

	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		DynamicThrowTypeService $dynamicThrowTypeService,
		Broker $broker
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
		$this->dynamicThrowTypeService = $dynamicThrowTypeService;
		$this->broker = $broker;
		$this->throwsScope = new ThrowsScope();
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

		if ($node instanceof ClassMethod) {
			return $this->processClassMethod($node, $scope);
		}

		if ($node instanceof ClassMethodEnd) {
			return $this->processClassMethodEnd($scope);
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
	public function processTryCatch(TryCatch $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		$methodReflection = $scope->getFunction();
		if ($classReflection === null || $methodReflection === null) {
			return [];
		}

		$this->throwsScope->enterToTryCatch($node);

		$node->stmts[] = new TryCatchTryEnd($node);

		return [];
	}

	/**
	 * @return string[]
	 */
	public function processTryCatchTryEnd(): array
	{
		$this->throwsScope->exitFromTry();

		return [];
	}

	/**
	 * @return string[]
	 */
	public function processThrow(Throw_ $node, Scope $scope): array
	{
		$exceptionType = $scope->getType($node->expr);
		$exceptionClassNames = TypeUtils::getDirectClassNames($exceptionType);
		$exceptionClassNames = $this->checkedExceptionService->filterCheckedExceptions($exceptionClassNames);
		$exceptionClassNames = $this->throwsScope->filterExceptionsByUncaught($exceptionClassNames);

		return array_map(function (string $exceptionClassName): string {
			return sprintf('Missing @throws %s annotation', $exceptionClassName);
		}, $exceptionClassNames);
	}

	/**
	 * @return string[]
	 */
	public function processMethodCall(MethodCall $node, Scope $scope): array
	{
		$methodName = $node->name;
		if (!$methodName instanceof Identifier) {
			return [];
		}

		$targetType = $scope->getType($node->var);
		$targetClassNames = TypeUtils::getDirectClassNames($targetType);

		$throwTypes = [];
		foreach ($targetClassNames as $targetClassName) {
			$targetClassReflection = $this->broker->getClass($targetClassName);
			if (!$targetClassReflection->hasMethod($methodName->toString())) {
				continue;
			}

			$targetMethodReflection = $targetClassReflection->getMethod($methodName->toString(), $scope);
			if (!$targetMethodReflection instanceof ThrowableReflection) {
				continue;
			}

			$throwType = $this->dynamicThrowTypeService->getMethodThrowType($targetMethodReflection, $node, $scope);
			if ($throwType === null) {
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
	public function processStaticCall(StaticCall $node, Scope $scope): array
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
		foreach ($targetMethodReflections as $targetMethodReflection) {
			$throwType = $this->dynamicThrowTypeService->getStaticMethodThrowType($targetMethodReflection, $node, $scope);
			if ($throwType === null) {
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
	public function processNew(New_ $node, Scope $scope): array
	{
		return $this->processThrowTypesOnMethod($node->class, ['__construct'], $scope);
	}

	/**
	 * @return string[]
	 */
	public function processForeach(Foreach_ $node, Scope $scope): array
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
	public function processClassMethod(ClassMethod $node, Scope $scope): array
	{
		if ($node->stmts === null) {
			$node->stmts = [];
		}

		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			return [];
		}

		$methodReflection = $classReflection->getMethod($node->name->toString(), $scope);
		if ($methodReflection instanceof ThrowableReflection) {
			$this->throwsScope->enterToThrowsAnnotationBlock($methodReflection->getThrowType());
		}

		$node->stmts[] = new ClassMethodEnd($node);

		return [];
	}

	/**
	 * @return string[]
	 */
	public function processClassMethodEnd(Scope $scope): array
	{
		$usedThrowsAnnotations = $this->throwsScope->exitFromThrowsAnnotationBlock();

		$classReflection = $scope->getClassReflection();
		$methodReflection = $scope->getFunction();
		if ($classReflection === null || $methodReflection === null) {
			return [];
		}

		if ($classReflection->isInterface()) {
			return [];
		}

		$nativeMethodReflection = new ReflectionMethod($classReflection->getName(), $methodReflection->getName());
		if ($nativeMethodReflection->isAbstract()) {
			return [];
		}

		if (!$methodReflection instanceof ThrowableReflection) {
			return [];
		}

		$throwType = $methodReflection->getThrowType();
		if ($throwType === null) {
			return [];
		}

		$declaredThrows = TypeUtils::getDirectClassNames($throwType);

		$messages = [];
		$diff = array_diff($declaredThrows, $usedThrowsAnnotations);
		foreach ($diff as $unusedClass) {
			$messages[] = sprintf('Unused @throws %s annotation', $unusedClass);
		}

		return $messages;
	}

	/**
	 * @return string[]
	 */
	public function processCatch(Catch_ $node): array
	{
		$messages = [];

		foreach ($node->types as $type) {
			$caughtCheckedExceptions = $this->throwsScope->getCaughtExceptions($type);
			if (count($caughtCheckedExceptions) > 0) {
				continue;
			}

			$exceptionClass = $type->toString();
			if (!$this->checkedExceptionService->isCheckedException($exceptionClass)) {
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

		if ($functionName === 'json_encode') {
			return $this->processThrowTypesOnMethod($node->args[0]->value, ['jsonSerialize'], $scope);
		}

		try {
			$functionReflection = $this->broker->getFunction($nodeName, $scope);
		} catch (FunctionNotFoundException $e) {
			return [];
		}

		$throwType = $this->dynamicThrowTypeService->getFunctionThrowType($functionReflection, $node, $scope);

		return $this->processThrowsTypes($throwType);
	}

	/**
	 * @param Name|Expr|ClassLike $class
	 * @param string[] $methods
	 * @return string[]
	 */
	private function processThrowTypesOnMethod($class, array $methods, Scope $scope): array
	{
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

		return $this->processThrowsTypes(TypeCombinator::union(...$throwTypes));
	}

	/**
	 * @return string[]
	 */
	private function processThrowsTypes(?Type $targetThrowType): array
	{
		if ($targetThrowType === null) {
			return [];
		}

		$targetExceptionClasses = TypeUtils::getDirectClassNames($targetThrowType);
		$targetExceptionClasses = $this->checkedExceptionService->filterCheckedExceptions($targetExceptionClasses);
		$targetExceptionClasses = $this->throwsScope->filterExceptionsByUncaught($targetExceptionClasses);

		return array_map(function (string $targetExceptionClass): string {
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
