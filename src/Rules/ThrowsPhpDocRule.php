<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\Node\ClassMethodEnd;
use Pepakriz\PHPStanExceptionRules\Node\TryCatchTryEnd;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
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
	 * @var Broker
	 */
	private $broker;

	/**
	 * @var ThrowsScope
	 */
	private $throwsScope;

	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		Broker $broker
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
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
	public function processStaticCall(StaticCall $node, Scope $scope): array
	{
		$methodName = $node->name;
		if ($methodName instanceof Identifier) {
			$methodName = $methodName->toString();
		}

		if (!is_string($methodName)) {
			return [];
		}

		return $this->processThrowTypesOnMethod($node->class, $methodName, $scope);
	}

	/**
	 * @return string[]
	 */
	public function processNew(New_ $node, Scope $scope): array
	{
		return $this->processThrowTypesOnMethod($node->class, '__construct', $scope);
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
	 * @param Name|Expr|ClassLike $class
	 * @return string[]
	 */
	private function processThrowTypesOnMethod($class, string $method, Scope $scope): array
	{
		$throwTypes = [];
		$targetMethodReflections = $this->getMethodReflections($class, $method, $scope);
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
	 * @return MethodReflection[]
	 */
	private function getMethodReflections(
		$class,
		string $methodName,
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
				$methodReflections[] = $this->broker->getClass($className)->getMethod($methodName, $scope);
			} catch (ClassNotFoundException | MissingMethodFromReflectionException $e) {
				continue;
			}
		}

		return $methodReflections;
	}

}
