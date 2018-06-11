<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;

class ClassMethodEnd extends Nop
{

	public function __construct(ClassMethod $node)
	{
		parent::__construct([
			'startLine' => $node->getLine(),
		]);
	}

}
