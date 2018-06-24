<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class BaseRule implements Rule
{

	/**
	 * @var string
	 */
	private $nodeClassName;

	/**
	 * @var callable
	 */
	private $callback;

	private function __construct(string $nodeClassName, callable $callback)
	{
		$this->nodeClassName = $nodeClassName;
		$this->callback = $callback;
	}

	public function getNodeType(): string
	{
		return $this->nodeClassName;
	}

	/**
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$callback = $this->callback;
		return $callback($node, $scope);
	}

	public static function createRule(string $nodeClassName, callable $callback): self
	{
		return new self($nodeClassName, $callback);
	}

}
