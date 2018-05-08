<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\Node\Stmt\TryCatch;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ThrowableReflection;
use PHPStan\Rules\Rule;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VerbosityLevel;
use function array_merge;
use function is_a;
use function is_string;
use function sprintf;

class ThrowsRuleFactory
{

	/**
	 * @var string[]
	 */
	private $exceptionWhiteList;

	/**
	 * @var string[]
	 */
	private $exceptionBlackList;

	/**
	 * @var mixed[]
	 */
	private static $catches = [];

	/**
	 * @var Broker
	 */
	private $broker;

	/**
	 * @param string[] $exceptionWhiteList
	 * @param string[] $exceptionBlackList
	 */
	public function __construct(
		array $exceptionWhiteList,
		array $exceptionBlackList,
		Broker $broker
	)
	{
		$this->exceptionWhiteList = $exceptionWhiteList;
		$this->exceptionBlackList = $exceptionBlackList;
		$this->broker = $broker;
	}

	public function createTryCatch(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsRuleFactory
			 */
			private $throwsRule;

			public function __construct(ThrowsRuleFactory $throwsRule)
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
				return $this->throwsRule->processTryCatch($node, $scope);
			}

		};
	}

	public function createThrow(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsRuleFactory
			 */
			private $throwsRule;

			public function __construct(ThrowsRuleFactory $throwsRule)
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
				return $this->throwsRule->processThrow($node, $scope);
			}

		};
	}

	public function createMethodCall(): Rule
	{
		return new class ($this) implements Rule {

			/**
			 * @var ThrowsRuleFactory
			 */
			private $throwsRule;

			public function __construct(ThrowsRuleFactory $throwsRule)
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
				return $this->throwsRule->processMethodCall($node, $scope);
			}

		};
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
	public function processThrow(Throw_ $node, Scope $scope): array
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
		if (!$this->isExceptionClassWhitelisted($exceptionClassName)) {
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
		if ($throwType !== null && $throwType->accepts($exceptionType)) {
			return [];
		}

		return [
			sprintf('Missing @throws %s annotation', $exceptionClassName),
		];
	}

	/**
	 * @return string[]
	 */
	public function processMethodCall(MethodCall $node, Scope $scope): array
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

		$targetThrowType = $targetMethodReflection->getThrowType();
		if ($targetThrowType === null) {
			return [];
		}

		$throwType = $methodReflection->getThrowType();
		if ($throwType !== null && $throwType->accepts($targetThrowType)) {
			return [];
		}

		return [
			sprintf('Missing @throws %s annotation', $targetThrowType->describe(VerbosityLevel::typeOnly())),
		];
	}

	private function isExceptionClassWhitelisted(string $exceptionClassName): bool
	{
		foreach ($this->exceptionWhiteList as $whitelistedException) {
			if (is_a($exceptionClassName, $whitelistedException, true)) {
				foreach ($this->exceptionBlackList as $blacklistedException) {
					if (!is_a($exceptionClassName, $blacklistedException, true)) {
						continue;
					}

					if (is_a($blacklistedException, $whitelistedException, true)) {
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
