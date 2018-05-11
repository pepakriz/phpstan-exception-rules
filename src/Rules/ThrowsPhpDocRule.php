<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use Pepakriz\PHPStanExceptionRules\Node\ClassMethodEnd;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use ReflectionMethod;
use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use function count;
use function is_a;
use function is_string;
use function sprintf;

class ThrowsPhpDocRule
{

	/**
	 * @var string[]
	 */
	private $checkedExceptions;

	/**
	 * @var string[]
	 */
	private $uncheckedExceptions;

	/**
	 * @var mixed[]
	 */
	private static $catches = [];

	/**
	 * @var mixed[]
	 */
	private static $usedThrows = [];

	/**
	 * @var Broker
	 */
	private $broker;

	/**
	 * @param string[] $checkedExceptions
	 * @param string[] $uncheckedExceptions
	 */
	public function __construct(
		array $checkedExceptions,
		array $uncheckedExceptions,
		Broker $broker
	)
	{
		$this->checkedExceptions = $checkedExceptions;
		$this->uncheckedExceptions = $uncheckedExceptions;
		$this->broker = $broker;
	}

	public function enableTryCatchCrawler(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
			}

			public function getNodeType(): string
			{
				return TryCatch::class;
			}

			/**
			 * @param TryCatch $node
			 * @return string[]
			 */
			public function processNode(Node $node, Scope $scope): array
			{
				return $this->throwsRule->processTryCatchCrawler($node, $scope);
			}

		};
	}

	public function enableThrowsPhpDocChecker(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
			}

			public function getNodeType(): string
			{
				return Throw_::class;
			}

			/**
			 * @param Throw_ $node
			 * @return string[]
			 */
			public function processNode(Node $node, Scope $scope): array
			{
				return $this->throwsRule->processThrowsPhpDocChecker($node, $scope);
			}

		};
	}

	public function enableCallPropagation(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
			}

			public function getNodeType(): string
			{
				return MethodCall::class;
			}

			/**
			 * @param MethodCall $node
			 * @return string[]
			 */
			public function processNode(Node $node, Scope $scope): array
			{
				return $this->throwsRule->processCallPropagation($node, $scope);
			}

		};
	}

	public function enableStaticCallPropagation(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
			}

			public function getNodeType(): string
			{
				return StaticCall::class;
			}

			/**
			 * @param StaticCall $node
			 * @return string[]
			 */
			public function processNode(Node $node, Scope $scope): array
			{
				return $this->throwsRule->processStaticCallPropagation($node, $scope);
			}

		};
	}

	public function enableMethodDeclaration(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
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
				$node->stmts[] = new ClassMethodEnd($node);

				return [];
			}

		};
	}

	public function enableMethodEnd(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsPhpDocRule
			 */
			private $throwsRule;

			public function __construct(ThrowsPhpDocRule $throwsRule)
			{
				$this->throwsRule = $throwsRule;
			}

			public function getNodeType(): string
			{
				return ClassMethodEnd::class;
			}

			/**
			 * @param ClassMethodEnd $node
			 * @return string[]
			 */
			public function processNode(Node $node, Scope $scope): array
			{
				return $this->throwsRule->processUnusedThrows($node, $scope);
			}

		};
	}

	/**
	 * @return string[]
	 */
	public function processTryCatchCrawler(TryCatch $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		$methodReflection = $scope->getFunction();
		if ($classReflection === null || $methodReflection === null) {
			return [];
		}

		$className = $classReflection->getName();
		$functionName = $methodReflection->getName();

		foreach ($node->catches as $catch) {
			if (!isset(self::$catches[$className][$functionName][$node->getLine()][$catch->getLine()])) {
				self::$catches[$className][$functionName][$node->getLine()][$catch->getLine()] = [];
			}

			foreach ($catch->types as $catchType) {
				self::$catches[$className][$functionName][$node->getLine()][$catch->getLine()][] = (string) $catchType;
			}
		}

		return [];
	}

	/**
	 * @return string[]
	 */
	public function processThrowsPhpDocChecker(Throw_ $node, Scope $scope): array
	{
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
		if (!$this->isExceptionClassChecked($exceptionClassName)) {
			return [];
		}

		if (!$methodReflection instanceof ThrowableReflection) {
			return [];
		}

		$className = $classReflection->getName();
		$functionName = $methodReflection->getName();
		if ($this->isCaught($className, $functionName, $node->getLine(), $exceptionClassName)) {
			return [];
		}

		$throwType = $methodReflection->getThrowType();
		$targetExceptionClasses = $this->getClassNamesByType($exceptionType);
		$targetExceptionClasses = $this->filterCheckedClasses($targetExceptionClasses);

		if ($this->isExceptionClassAnnotated($className, $functionName, $throwType, $targetExceptionClasses)) {
			return [];
		}

		return [
			sprintf('Missing @throws %s annotation', $exceptionClassName),
		];
	}

	/**
	 * @return string[]
	 */
	public function processCallPropagation(MethodCall $node, Scope $scope): array
	{
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
		if (!is_string($methodName)) {
			return [];
		}

		$targetClassReflection = $this->broker->getClass($targetType->getClassName());
		$targetMethodReflection = $targetClassReflection->getMethod($methodName, $scope);

		if (!$targetMethodReflection instanceof ThrowableReflection) {
			return [];
		}

		return $this->processThrowsTypes(
			$classReflection->getName(),
			$methodReflection->getName(),
			$methodReflection->getThrowType(),
			$targetMethodReflection->getThrowType()
		);
	}

	/**
	 * @return string[]
	 */
	public function processStaticCallPropagation(StaticCall $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		$methodReflection = $scope->getFunction();
		if ($classReflection === null || $methodReflection === null) {
			return [];
		}

		if (!$methodReflection instanceof ThrowableReflection) {
			return [];
		}

		if (!is_string($node->name)) {
			return [];
		}

		$targetMethodReflection = $this->getMethod($node->class, $node->name, $scope);
		if (!$targetMethodReflection instanceof ThrowableReflection) {
			return [];
		}

		return $this->processThrowsTypes(
			$classReflection->getName(),
			$methodReflection->getName(),
			$methodReflection->getThrowType(),
			$targetMethodReflection->getThrowType()
		);
	}

	/**
	 * @return string[]
	 */
	public function processUnusedThrows(ClassMethodEnd $node, Scope $scope): array
	{
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

		$declaredThrows = $this->getClassNamesByType($throwType);
		$userThrows = array_unique(self::$usedThrows[$className][$functionName] ?? []);

		$messages = [];
		$diff = array_diff($declaredThrows, $userThrows);
		foreach ($diff as $unusedClass) {
			$messages[] = sprintf('Unused @throws %s annotation', $unusedClass);
		}

		return $messages;
	}

	/**
	 * @return string[]
	 */
	private function processThrowsTypes(string $className, string $functionName, ?Type $throwType, ?Type $targetThrowType): array
	{
		if ($targetThrowType === null) {
			return [];
		}

		$targetExceptionClasses = $this->getClassNamesByType($targetThrowType);
		$targetExceptionClasses = $this->filterCheckedClasses($targetExceptionClasses);

		if ($this->isExceptionClassAnnotated($className, $functionName, $throwType, $targetExceptionClasses)) {
			return [];
		}

		return [
			sprintf('Missing @throws %s annotation', $targetThrowType->describe(VerbosityLevel::typeOnly())),
		];
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

		$throwsExceptionClasses = $this->getClassNamesByType($throwType);
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
	 * @return string[]
	 */
	private function getClassNamesByType(Type $type): array
	{
		if ($type instanceof UnionType) {
			return $type->getReferencedClasses();
		}

		if ($type instanceof TypeWithClassName) {
			return [$type->getClassName()];
		}

		throw new ShouldNotHappenException();
	}

	/**
	 * @param string[] $classes
	 * @return string[]
	 */
	private function filterCheckedClasses(array $classes): array
	{
		return array_filter($classes, function (string $class): bool {
			return $this->isExceptionClassChecked($class);
		});
	}

	/**
	 * @param Name|Expr $class
	 */
	private function getMethod(
		$class,
		string $methodName,
		Scope $scope
	): MethodReflection
	{
		if ($class instanceof Name) {
			$calledOnType = new ObjectType($scope->resolveName($class));
		} else {
			$calledOnType = $scope->getType($class);
		}

		if (!$calledOnType->hasMethod($methodName)) {
			throw new ShouldNotHappenException();
		}

		return $calledOnType->getMethod($methodName, $scope);
	}

	private function isExceptionClassChecked(string $exceptionClassName): bool
	{
		foreach ($this->checkedExceptions as $checkedException) {
			if (is_a($exceptionClassName, $checkedException, true)) {
				foreach ($this->uncheckedExceptions as $blacklistedException) {
					if (!is_a($exceptionClassName, $blacklistedException, true)) {
						continue;
					}

					if (is_a($blacklistedException, $checkedException, true)) {
						continue 2;
					}
				}

				return true;
			}
		}

		return false;
	}

	private function isCaught(string $className, string $functionName, int $line, string $exceptionClassName): bool
	{
		$catches = $this->getCatches($className, $functionName, $line);

		foreach ($catches as $catch) {
			if (is_a($exceptionClassName, $catch, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	private function getCatches(string $className, string $functionName, int $line): array
	{
		if (!isset(self::$catches[$className][$functionName])) {
			return [];
		}

		$result = [];
		foreach (self::$catches[$className][$functionName] as $fromLine => $lines) {
			if ($fromLine > $line) {
				continue;
			}

			foreach ($lines as $toLine => $classNames) {
				if ($toLine < $line) {
					continue;
				}

				$result = array_merge($result, $classNames);
			}
		}

		return $result;
	}

}
