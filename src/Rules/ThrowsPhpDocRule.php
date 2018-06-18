<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\Node\ClassMethodEnd;
use Pepakriz\PHPStanExceptionRules\Node\TryCatchTryEnd;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use ReflectionMethod;
use function array_diff;
use function array_filter;
use function array_map;
use function array_unique;
use function count;
use function is_a;
use function is_string;
use function spl_object_hash;
use function sprintf;

class ThrowsPhpDocRule
{

	/**
	 * @var string[][]
	 */
	private static $catches = [];

	/**
	 * @var mixed[]
	 */
	private static $usedThrows = [];

	/**
	 * @var CheckedExceptionService
	 */
	private $checkedExceptionService;

	/**
	 * @var Broker
	 */
	private $broker;

	public function __construct(
		CheckedExceptionService $checkedExceptionService,
		Broker $broker
	)
	{
		$this->checkedExceptionService = $checkedExceptionService;
		$this->broker = $broker;
	}

	public function enableTryCatchCrawler(): Rule
	{
		return BaseRule::createRule(TryCatch::class, function (TryCatch $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			$nodeId = spl_object_hash($node);
			foreach ($node->catches as $catch) {
				if (!isset(self::$catches[$nodeId])) {
					self::$catches[$nodeId] = [];
				}

				foreach ($catch->types as $catchType) {
					self::$catches[$nodeId][] = (string) $catchType;
				}
			}

			$node->stmts[] = new TryCatchTryEnd($node);

			return [];
		});
	}

	public function enableTryEndCatchCrawler(): Rule
	{
		return BaseRule::createRule(TryCatchTryEnd::class, function (TryCatchTryEnd $node): array {
			$nodeId = spl_object_hash($node->getTryCatchNode());
			unset(self::$catches[$nodeId]);

			return [];
		});
	}

	public function enableThrowsPhpDocChecker(): Rule
	{
		return BaseRule::createRule(Throw_::class, function (Throw_ $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			$exceptionType = $scope->getType($node->expr);
			if (!$exceptionType instanceof TypeWithClassName) {
				return [];
			}

			$exceptionClassName = $exceptionType->getClassName();
			if (!$this->checkedExceptionService->isExceptionClassWhitelisted($exceptionClassName)) {
				return [];
			}

			if (!$methodReflection instanceof ThrowableReflection) {
				return [];
			}

			$className = $classReflection->getName();
			$functionName = $methodReflection->getName();
			if ($this->isCaught($exceptionClassName)) {
				return [];
			}

			$throwType = $methodReflection->getThrowType();
			$targetExceptionClasses = TypeUtils::getDirectClassNames($exceptionType);
			$targetExceptionClasses = $this->filterClassesByWhitelist($targetExceptionClasses);

			if ($this->isExceptionClassAnnotated($className, $functionName, $throwType, $targetExceptionClasses)) {
				return [];
			}

			return [
				sprintf('Missing @throws %s annotation', $exceptionClassName),
			];
		});
	}

	public function enableCallPropagation(): Rule
	{
		return BaseRule::createRule(MethodCall::class, function (MethodCall $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			if (!$methodReflection instanceof ThrowableReflection) {
				return [];
			}

			$targetType = $scope->getType($node->var);
			if (!$targetType instanceof TypeWithClassName) {
				return [];
			}

			$methodName = $node->name;
			if (!$methodName instanceof Identifier) {
				return [];
			}

			$targetClassReflection = $this->broker->getClass($targetType->getClassName());
			if (!$targetClassReflection->hasMethod($methodName->toString())) {
				return [];
			}

			$targetMethodReflection = $targetClassReflection->getMethod($methodName->toString(), $scope);

			if (!$targetMethodReflection instanceof ThrowableReflection) {
				return [];
			}

			return $this->processThrowsTypes(
				$classReflection->getName(),
				$methodReflection->getName(),
				$methodReflection->getThrowType(),
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableStaticCallPropagation(): Rule
	{
		return BaseRule::createRule(StaticCall::class, function (StaticCall $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			if (!$methodReflection instanceof ThrowableReflection) {
				return [];
			}

			$methodName = $node->name;
			if ($methodName instanceof Identifier) {
				$methodName = $methodName->toString();
			}

			if (!is_string($methodName)) {
				return [];
			}

			$targetMethodReflection = $this->getMethod($node->class, $methodName, $scope);
			if (!$targetMethodReflection instanceof ThrowableReflection) {
				return [];
			}

			return $this->processThrowsTypes(
				$classReflection->getName(),
				$methodReflection->getName(),
				$methodReflection->getThrowType(),
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableCallConstructorPropagation(): Rule
	{
		return BaseRule::createRule(New_::class, function (New_ $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			if (!$methodReflection instanceof ThrowableReflection) {
				return [];
			}

			$targetMethodReflection = $this->getMethod($node->class, '__construct', $scope);
			if (!$targetMethodReflection instanceof ThrowableReflection) {
				return [];
			}

			return $this->processThrowsTypes(
				$classReflection->getName(),
				$methodReflection->getName(),
				$methodReflection->getThrowType(),
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableMethodDeclaration(): Rule
	{
		return BaseRule::createRule(ClassMethod::class, function (ClassMethod $node): array {
			if ($node->stmts === null) {
				$node->stmts = [];
			}

			$node->stmts[] = new ClassMethodEnd($node);

			return [];
		});
	}

	public function enableMethodEnd(): Rule
	{
		return BaseRule::createRule(ClassMethodEnd::class, function (ClassMethodEnd $node, Scope $scope): array {
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

			$className = $classReflection->getName();
			$functionName = $methodReflection->getName();

			$declaredThrows = TypeUtils::getDirectClassNames($throwType);
			$userThrows = array_unique(self::$usedThrows[$className][$functionName] ?? []);

			$messages = [];
			$diff = array_diff($declaredThrows, $userThrows);
			foreach ($diff as $unusedClass) {
				$messages[] = sprintf('Unused @throws %s annotation', $unusedClass);
			}

			return $messages;
		});
	}

	/**
	 * @return string[]
	 */
	private function processThrowsTypes(string $className, string $functionName, ?Type $throwType, ?Type $targetThrowType): array
	{
		if ($targetThrowType === null) {
			return [];
		}

		$targetExceptionClasses = TypeUtils::getDirectClassNames($targetThrowType);
		$targetExceptionClasses = array_filter($targetExceptionClasses, function (string $targetExceptionClass): bool {
			return $this->isCaught($targetExceptionClass) === false;
		});

		$targetExceptionClasses = $this->filterClassesByWhitelist($targetExceptionClasses);

		if ($this->isExceptionClassAnnotated($className, $functionName, $throwType, $targetExceptionClasses)) {
			return [];
		}

		return array_map(function (string $targetExceptionClass): string {
			return sprintf('Missing @throws %s annotation', $targetExceptionClass);
		}, $targetExceptionClasses);
	}

	/**
	 * @param string[] $targetExceptionClasses
	 */
	private function isExceptionClassAnnotated(
		string $className,
		string $functionName,
		?Type $throwType,
		array $targetExceptionClasses
	): bool
	{
		if (count($targetExceptionClasses) === 0) {
			return true;
		}

		if ($throwType === null) {
			return false;
		}

		$throwsExceptionClasses = TypeUtils::getDirectClassNames($throwType);
		foreach ($targetExceptionClasses as $targetExceptionClass) {
			foreach ($throwsExceptionClasses as $throwsExceptionClass) {
				if (is_a($targetExceptionClass, $throwsExceptionClass, true)) {
					self::$usedThrows[$className][$functionName][] = $throwsExceptionClass;
					continue 2;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	private function filterClassesByWhitelist(array $classes): array
	{
		return array_filter($classes, function (string $class): bool {
			return $this->checkedExceptionService->isExceptionClassWhitelisted($class);
		});
	}

	/**
	 * @param Name|Expr|ClassLike $class
	 */
	private function getMethod(
		$class,
		string $methodName,
		Scope $scope
	): ?MethodReflection
	{
		if ($class instanceof ClassLike) {
			$className = $class->name;
			if ($className === null) {
				return null;
			}

			$calledOnType = new ObjectType($className->name);

		} elseif ($class instanceof Name) {
			$calledOnType = new ObjectType($scope->resolveName($class));
		} else {
			$calledOnType = $scope->getType($class);
		}

		if (!$calledOnType->hasMethod($methodName)) {
			return null;
		}

		return $calledOnType->getMethod($methodName, $scope);
	}

	private function isCaught(string $exceptionClassName): bool
	{
		foreach (self::$catches as $catches) {
			foreach ($catches as $catch) {
				if (is_a($exceptionClassName, $catch, true)) {
					return true;
				}
			}
		}

		return false;
	}

}
