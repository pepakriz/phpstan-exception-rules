<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Node;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeAbstract;

class ClassMethodEnd extends NodeAbstract
{

	public function __construct(ClassMethod $node)
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
		return [];
	}

	public function getType(): string
	{
		return 'ClassMethodEnd';
	}

}
