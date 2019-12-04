<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use InvalidArgumentException;
use LogicException;
use Nette\Utils\Strings;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Broker\AnonymousClassNameHelper;
use PHPStan\Cache\Cache;
use PHPStan\File\FileHelper;
use PHPStan\File\FuzzyRelativePathHelper;
use PHPStan\PhpDoc\PhpDocNodeResolver;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\Rules\Registry;
use PHPStan\Rules\Rule;
use PHPStan\Testing\TestCase;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use function array_map;
use function explode;
use function file_get_contents;
use function implode;
use function sprintf;
use function trim;
use const DIRECTORY_SEPARATOR;

/**
 * @template TRule of \PHPStan\Rules\Rule
 */
abstract class RuleTestCase extends TestCase
{

	/**
	 * @var Analyser
	 */
	private $analyser;

	/**
	 * @phpstan-return TRule
	 */
	abstract protected function getRule(): Rule;

	protected function getTypeSpecifier(): TypeSpecifier
	{
		return $this->createTypeSpecifier(
			new Standard(),
			$this->createBroker(),
			$this->getMethodTypeSpecifyingExtensions(),
			$this->getStaticMethodTypeSpecifyingExtensions()
		);
	}

	protected function createThrowsAnnotationReader(): ThrowsAnnotationReader
	{
		return new ThrowsAnnotationReader(
			$this->getParser(),
			self::getContainer()->getByType(Lexer::class),
			self::getContainer()->getByType(PhpDocParser::class)
		);
	}

	private function getAnalyser(): Analyser
	{
		if ($this->analyser === null) {
			$registry = new Registry([$this->getRule()]);

			$broker = $this->createBroker();
			$printer = new Standard();
			$fileHelper = $this->getFileHelper();
			$typeSpecifier = $this->createTypeSpecifier(
				$printer,
				$broker,
				$this->getMethodTypeSpecifyingExtensions(),
				$this->getStaticMethodTypeSpecifyingExtensions()
			);
			$currentWorkingDirectory = $this->getCurrentWorkingDirectory();
			$this->analyser = new Analyser(
				$this->createScopeFactory($broker, $typeSpecifier),
				$this->getParser(),
				$registry,
				new NodeScopeResolver(
					$broker,
					$this->getParser(),
					new FileTypeMapper($this->getParser(), self::getContainer()->getByType(PhpDocStringResolver::class), self::getContainer()->getByType(PhpDocNodeResolver::class), $this->createMock(Cache::class), new AnonymousClassNameHelper(new FileHelper($currentWorkingDirectory), new FuzzyRelativePathHelper($currentWorkingDirectory, DIRECTORY_SEPARATOR, []))),
					$fileHelper,
					$typeSpecifier,
					$this->shouldPolluteScopeWithLoopInitialAssignments(),
					$this->shouldPolluteCatchScopeWithTryAssignments(),
					$this->shouldPolluteScopeWithAlwaysIterableForeach(),
					[],
					[]
				),
				$fileHelper,
				[],
				true,
				50
			);
		}

		return $this->analyser;
	}

	/**
	 * @return MethodTypeSpecifyingExtension[]
	 */
	protected function getMethodTypeSpecifyingExtensions(): array
	{
		return [];
	}

	/**
	 * @return StaticMethodTypeSpecifyingExtension[]
	 */
	protected function getStaticMethodTypeSpecifyingExtensions(): array
	{
		return [];
	}

	/**
	 * @return TypeNodeResolverExtension[]
	 */
	protected function getTypeNodeResolverExtensions(): array
	{
		return [];
	}

	public function analyse(string $file): void
	{
		$file = $this->getFileHelper()->normalizePath($file);
		$expectedErrors = $this->parseExpectedErrors($file);
		$actualErrors = $this->getAnalyser()->analyse([$file], false);

		$strictlyTypedSprintf = static function (int $line, string $message): string {
			return sprintf('%02d: %s', $line, $message);
		};

		$expectedErrors = array_map(
			static function (array $error) use ($strictlyTypedSprintf): string {
				if (!isset($error[0])) {
					throw new InvalidArgumentException('Missing expected error message.');
				}
				if (!isset($error[1])) {
					throw new InvalidArgumentException('Missing expected file line.');
				}
				return $strictlyTypedSprintf($error[1], $error[0]);
			},
			$expectedErrors
		);

		$actualErrors = array_map(
			static function (Error $error): string {
				return sprintf('%02d: %s', $error->getLine(), $error->getMessage());
			},
			$actualErrors
		);

		self::assertSame(implode("\n", $expectedErrors), implode("\n", $actualErrors));
	}

	/**
	 * @return mixed[]
	 */
	private function parseExpectedErrors(string $file): array
	{
		$fileData = file_get_contents($file);
		if ($fileData === false) {
			throw new LogicException('Error while reading data from ' . $file);
		}
		$fileData = explode("\n", $fileData);

		$expectedErrors = [];
		foreach ($fileData as $line => $row) {
			$matches = Strings::match($row, '#// error:([^$]+)#');
			if ($matches === null) {
				continue;
			}

			foreach (explode(';', $matches[1]) as $error) {
				$expectedErrors[] = [
					trim($error),
					$line + 1,
				];
			}
		}

		return $expectedErrors;
	}

	protected function shouldPolluteScopeWithLoopInitialAssignments(): bool
	{
		return false;
	}

	protected function shouldPolluteCatchScopeWithTryAssignments(): bool
	{
		return false;
	}

	protected function shouldPolluteScopeWithAlwaysIterableForeach(): bool
	{
		return false;
	}

}
