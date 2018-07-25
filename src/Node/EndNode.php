<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node;
use PhpParser\Node\Stmt\Nop;

class EndNode extends Nop
{

	/**
	 * @var NodeHook[]
	 */
	public $stmts = [];

	public function __construct(Node $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
	}

	/**
	 * @return string[]
	 */
	public function getSubNodeNames(): array
	{
		return ['stmts'];
	}

	public function addNodeHook(NodeHook $node): void
	{
		$this->stmts[] = $node;
	}

}
