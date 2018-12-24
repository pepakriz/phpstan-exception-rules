<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Nop;

class FunctionEnd extends Nop
{

	public function __construct(FunctionLike $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
	}

}
