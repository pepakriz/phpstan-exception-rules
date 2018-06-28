<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\CheckedExceptionService;
use Pepakriz\PHPStanExceptionRules\Node\CatchEnd;
use Pepakriz\PHPStanExceptionRules\Node\ClassMethodEnd;
use Pepakriz\PHPStanExceptionRules\Node\TryCatchTryEnd;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
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
use function sprintf;

class ThrowsPhpDocRule
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

	public function enableTryCatchCrawler(): Rule
	{
		return BaseRule::createRule(TryCatch::class, function (TryCatch $node, Scope $scope): array {
			$classReflection = $scope->getClassReflection();
			$methodReflection = $scope->getFunction();
			if ($classReflection === null || $methodReflection === null) {
				return [];
			}

			$this->throwsScope->enterToTryCatch($node);
			$node->stmts[] = new TryCatchTryEnd($node);

			return [];
		});
	}

	public function enableTryEndCatchCrawler(): Rule
	{
		return BaseRule::createRule(TryCatchTryEnd::class, function (): array {
			$this->throwsScope->exitFromTry();

			return [];
		});
	}

	public function enableThrowsPhpDocChecker(): Rule
	{
		return BaseRule::createRule(Throw_::class, function (Throw_ $node, Scope $scope): array {
			$messageClasses = $this->throwsScope->detectCaughtCheckedExceptionClasses($node);

			$exceptionType = $scope->getType($node->expr);
			if (!$exceptionType instanceof TypeWithClassName) {
				return $this->formatMissingThrowsByClasses($messageClasses);
			}

			$exceptionClassName = $exceptionType->getClassName();
			if (!$this->checkedExceptionService->isExceptionClassWhitelisted($exceptionClassName)) {
				return $this->formatMissingThrowsByClasses($messageClasses);
			}

			if ($this->throwsScope->isExceptionCaught($exceptionClassName)) {
				return $this->formatMissingThrowsByClasses($messageClasses);
			}

			$messageClasses[] = $exceptionClassName;

			return $this->formatMissingThrowsByClasses($messageClasses);
		});
	}

	public function enableCallPropagation(): Rule
	{
		return BaseRule::createRule(MethodCall::class, function (MethodCall $node, Scope $scope): array {
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
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableStaticCallPropagation(): Rule
	{
		return BaseRule::createRule(StaticCall::class, function (StaticCall $node, Scope $scope): array {
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
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableCallConstructorPropagation(): Rule
	{
		return BaseRule::createRule(New_::class, function (New_ $node, Scope $scope): array {
			$targetMethodReflection = $this->getMethod($node->class, '__construct', $scope);
			if (!$targetMethodReflection instanceof ThrowableReflection) {
				return [];
			}

			return $this->processThrowsTypes(
				$targetMethodReflection->getThrowType()
			);
		});
	}

	public function enableMethodDeclaration(): Rule
	{
		return BaseRule::createRule(ClassMethod::class, function (ClassMethod $node, Scope $scope): array {
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
		});
	}

	public function enableMethodEnd(): Rule
	{
		return BaseRule::createRule(ClassMethodEnd::class, function (ClassMethodEnd $node, Scope $scope): array {
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
		});
	}

	public function enableCatchValidation(): Rule
	{
		return BaseRule::createRule(Catch_::class, function (Catch_ $node): array {
			$this->throwsScope->enterToCatch($node);
			$node->stmts[] = new CatchEnd($node);

			$messages = [];

			foreach ($node->types as $type) {
				$caughtCheckedExceptions = $this->throwsScope->getCaughtExceptions($type);
				if (count($caughtCheckedExceptions) > 0) {
					continue;
				}

				$exceptionClass = $type->toString();
				if (!$this->checkedExceptionService->isExceptionClassWhitelisted($exceptionClass)) {
					continue;
				}

				$messages[] = sprintf('%s is never thrown in the corresponding try block', $exceptionClass);
			}

			return $messages;
		});
	}

	public function enableCatchEnd(): Rule
	{
		return BaseRule::createRule(CatchEnd::class, function (): array {
			$this->throwsScope->exitFromCatch();

			return [];
		});
	}

	public function enableReassignException(): Rule
	{
		return BaseRule::createRule(Assign::class, function (Assign $node): array {
			$currentCatch = $this->throwsScope->findCurrentCatch();
			if ($currentCatch === null) {
				return [];
			}

			$var = $node->var;
			if (!$var instanceof Expr\Variable) {
				return [];
			}

			$varName = $var->name;
			$currentCatchVarName = $currentCatch->var->name;
			if (is_string($currentCatchVarName) && is_string($varName) && $currentCatchVarName === $varName) {
				return [sprintf('Reassigning variable $%s is prohibited in catch block', $varName)];
			}

			return [];
		});
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
		$targetExceptionClasses = $this->filterClassesByWhitelist($targetExceptionClasses);
		$targetExceptionClasses = array_filter($targetExceptionClasses, function (string $targetExceptionClass): bool {
			return $this->throwsScope->isExceptionCaught($targetExceptionClass) === false;
		});

		return array_map(function (string $targetExceptionClass): string {
			return sprintf('Missing @throws %s annotation', $targetExceptionClass);
		}, $targetExceptionClasses);
	}

	/**
	 * @param string[] $messageClasses
	 * @return string[]
	 */
	private function formatMissingThrowsByClasses(array $messageClasses): array
	{
		$messageClasses = array_unique($messageClasses);

		$uniqueMessageClasses = [];
		foreach ($messageClasses as $messageClass) {
			foreach ($uniqueMessageClasses as $uniqueMessageKey => $uniqueMessageClass) {
				if (is_a($uniqueMessageClass, $messageClass, true)) {
					$uniqueMessageClasses[$uniqueMessageKey] = $messageClass;
					continue 2;
				}
			}
			$uniqueMessageClasses[] = $messageClass;
		}

		return array_map(function (string $messageClass): string {
			return sprintf('Missing @throws %s annotation', $messageClass);
		}, $uniqueMessageClasses);
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

}
