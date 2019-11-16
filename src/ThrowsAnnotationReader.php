<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Reflection\MethodReflection;
use ReflectionException;
use ReflectionFunction;
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
	public function read(Scope $scope): array
	{
		$reflection = $scope->getFunction();

		if ($reflection === null) {
			return [];
		}

		return $this->readByReflection($reflection, $scope);
	}

	/**
	 * @param \PHPStan\Reflection\FunctionReflection|\PHPStan\Reflection\MethodReflection $reflection
	 *
	 * @return string[][]
	 */
	public function readByReflection($reflection, Scope $scope): array
	{
		$namespace = $scope->getNamespace();
		$sourceFile = $scope->getFile();

		$key = $namespace . '::' . $sourceFile . '::';

		$classReflection = $scope->getClassReflection();

		if ($classReflection !== null) {
			$key .= $classReflection->getName();
		}

		$key .= $reflection->getName();

		if (!isset($this->annotations[$key])) {
			$this->annotations[$key] = $this->parse($reflection, $sourceFile, $namespace);
		}

		return $this->annotations[$key];
	}

	/**
	 * @param \PHPStan\Reflection\FunctionReflection|\PHPStan\Reflection\MethodReflection $reflection
	 *
	 * @return string[][]
	 */
	private function parse($reflection, string $sourceFile, ?string $namespace = null): array
	{
		try {
			$docBlock = $this->getDocblock($reflection);
		} catch (ReflectionException $exception) {
			return [];
		}

		if ($docBlock === null) {
			return [];
		}

		$tokens = new TokenIterator($this->phpDocLexer->tokenize($docBlock));
		$phpDocNode = $this->phpDocParser->parse($tokens);
		$nameScope = $this->createNameScope($sourceFile, $namespace);

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

	/**
	 * @param \PHPStan\Reflection\FunctionReflection|\PHPStan\Reflection\MethodReflection $reflection
	 *
	 * @throws ReflectionException
	 */
	private function getDocblock($reflection): ?string
	{
		if ($reflection instanceof MethodReflection) {
			$declaringClass = $reflection->getDeclaringClass();
			$classReflection = $declaringClass->getNativeReflection();
			$methodReflection = $classReflection->getMethod($reflection->getName());
			$docBlock = $methodReflection->getDocComment();

			while ($docBlock === false) {
				try {
					$methodReflection = $methodReflection->getPrototype();
				} catch (ReflectionException $exception) {
					return null;
				}

				$docBlock = $methodReflection->getDocComment();
			}

			return $docBlock !== false ? $docBlock : null;
		}

		$functionReflection = new ReflectionFunction($reflection->getName());
		$docBlock = $functionReflection->getDocComment();

		return $docBlock !== false ? $docBlock : null;
	}

	private function createNameScope(string $sourceFile, ?string $namespace = null): NameScope
	{
		return new NameScope($namespace, $this->getUsesMap($sourceFile, (string) $namespace));
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
	private function createUsesMap(string $sourceFile): array
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
		$traverser->traverse($this->phpParser->parseFile($sourceFile));

		return $visitor->uses;
	}

}
