<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Nop;

class CatchEnd extends Nop
{

	public function __construct(Catch_ $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
	}

}
