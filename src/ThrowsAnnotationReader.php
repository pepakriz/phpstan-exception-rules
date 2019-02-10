<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\NameScope;
use PHPStan\Parser\Parser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use function sprintf;
use function strtolower;

/**
 * @internal
 */
class ThrowsAnnotationReader
{

	/** @var Parser */
	private $phpParser;

	/** @var Lexer */
	private $phpDocLexer;

	/** @var PhpDocParser */
	private $phpDocParser;

	/** @var string[][][] */
	private $annotations = [];

	/** @var string[][][] */
	private $uses = [];

	public function __construct(Parser $phpParser, Lexer $phpDocLexer, PhpDocParser $phpDocParser)
	{
		$this->phpParser = $phpParser;
		$this->phpDocLexer = $phpDocLexer;
		$this->phpDocParser = $phpDocParser;
	}

	/**
	 * @return string[][]
	 */
	public function read(ReflectionFunctionAbstract $reflection): array
	{
		$functionName = $reflection->getName();

		if (!isset($this->annotations[$functionName])) {
			$this->annotations[$functionName] = $this->parse($reflection);
		}

		return $this->annotations[$functionName];
	}

	/**
	 * @return string[][]
	 */
	private function parse(ReflectionFunctionAbstract $reflection): array
	{
		$docComment = $reflection->getDocComment();

		if ($docComment === false) {
			return [];
		}

		$tokens = new TokenIterator($this->phpDocLexer->tokenize($docComment));
		$phpDocNode = $this->phpDocParser->parse($tokens);
		$nameScope = $this->createNameScope($reflection);

		$annotations = [];
		foreach ($phpDocNode->getThrowsTagValues() as $tagValue) {
			$type = $nameScope->resolveStringName((string) $tagValue->type);

			if (!isset($annotations[$type])) {
				$annotations[$type] = [];
			}

			$annotations[$type][] = $tagValue->description;
		}

		return $annotations;
	}

	private function createNameScope(ReflectionFunctionAbstract $reflection): NameScope
	{
		$namespace = $reflection instanceof ReflectionMethod ?
			$reflection->getDeclaringClass()->getNamespaceName() :
			$reflection->getNamespaceName();

		/** @var string $fileName */
		$fileName = $reflection->getFileName();
		$usesMap = $this->getUsesMap($fileName, $namespace);

		return new NameScope($namespace, $usesMap);
	}

	/**
	 * @return string[]
	 */
	private function getUsesMap(string $fileName, string $namespace): array
	{
		if (!isset($this->uses[$fileName])) {
			$this->uses[$fileName] = $this->createUsesMap($fileName);
		}

		return $this->uses[$fileName][$namespace] ?? [];
	}

	/**
	 * @return string[][]
	 */
	private function createUsesMap(string $fileName): array
	{
		$visitor = new class extends NodeVisitorAbstract {

			/** @var string[][] */
			public $uses = [];

			/** @var string */
			private $namespace = '';

			public function enterNode(Node $node): ?Node
			{
				if ($node instanceof Node\Stmt\Namespace_) {
					$this->namespace = (string) $node->name;

					return null;
				}

				if ($node instanceof Node\Stmt\Use_ && $node->type === Node\Stmt\Use_::TYPE_NORMAL) {
					foreach ($node->uses as $use) {
						$this->addUse($use->getAlias()->name, (string) $use->name);
					}

					return null;
				}

				if ($node instanceof Node\Stmt\GroupUse) {
					$prefix = (string) $node->prefix;

					foreach ($node->uses as $use) {
						if ($node->type !== Node\Stmt\Use_::TYPE_NORMAL && $use->type !== Node\Stmt\Use_::TYPE_NORMAL) {
							continue;
						}

						$this->addUse($use->getAlias()->name, sprintf('%s\\%s', $prefix, (string) $use->name));
					}

					return null;
				}

				return null;
			}

			private function addUse(string $alias, string $className): void
			{
				if (!isset($this->uses[$this->namespace])) {
					$this->uses[$this->namespace] = [];
				}

				$this->uses[$this->namespace][strtolower($alias)] = $className;
			}

		};

		$traverser = new NodeTraverser();
		$traverser->addVisitor($visitor);
		$traverser->traverse($this->phpParser->parseFile($fileName));

		return $visitor->uses;
	}

}
