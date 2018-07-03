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
use PHPStan\PhpDoc\PhpDocStringResolver;
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

abstract class RuleTestCase extends TestCase
{

	/**
	 * @var Analyser
	 */
	private $analyser;

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
			$this->analyser = new Analyser(
				$this->createScopeFactory($broker, $typeSpecifier),
				$this->getParser(),
				$registry,
				new NodeScopeResolver(
					$broker,
					$this->getParser(),
					new FileTypeMapper($this->getParser(), self::getContainer()->getByType(PhpDocStringResolver::class), $this->createMock(Cache::class), new AnonymousClassNameHelper(new FileHelper($this->getCurrentWorkingDirectory()))),
					$fileHelper,
					$typeSpecifier,
					$this->shouldPolluteScopeWithLoopInitialAssignments(),
					$this->shouldPolluteCatchScopeWithTryAssignments(),
					[]
				),
				$fileHelper,
				[],
				null,
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

	public function analyse(string $file): void
	{
		$file = $this->getFileHelper()->normalizePath($file);
		$expectedErrors = $this->parseExpectedErrors($file);
		$actualErrors = $this->getAnalyser()->analyse([$file], false);

		$strictlyTypedSprintf = function (int $line, string $message): string {
			return sprintf('%02d: %s', $line, $message);
		};

		$expectedErrors = array_map(
			function (array $error) use ($strictlyTypedSprintf): string {
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
			function (Error $error): string {
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

			$expectedErrors[] = [
				trim($matches[1]),
				$line + 1,
			];
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

}
