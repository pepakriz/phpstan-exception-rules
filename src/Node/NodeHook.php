<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node;
use PhpParser\Node\Stmt\Nop;

class NodeHook extends Nop
{

	/**
	 * @var Node
	 */
	private $node;

	public function __construct(Node $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
		$this->node = $node;
	}

	public function getNode(): Node
	{
		return $this->node;
	}

}
