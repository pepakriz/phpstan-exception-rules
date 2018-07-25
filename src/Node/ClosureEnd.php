<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Nop;

class ClosureEnd extends Nop
{

	/**
	 * @var Closure
	 */
	private $closure;

	public function __construct(Closure $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
		$this->closure = $node;
	}

	public function getClosure(): Closure
	{
		return $this->closure;
	}

}
