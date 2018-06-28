<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TryCatch;

class TryCatchTryEnd extends Nop
{

	public function __construct(TryCatch $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
	}

}
