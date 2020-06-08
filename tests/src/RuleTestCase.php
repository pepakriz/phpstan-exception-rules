<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use LogicException;
use Nette\Utils\Strings;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\Testing\RuleTestCase as BaseRuleTestCase;
use function explode;
use function file_get_contents;
use function trim;

/**
 * @template TRule of \PHPStan\Rules\Rule
 * @extends BaseRuleTestCase<TRule>
 */
abstract class RuleTestCase extends BaseRuleTestCase
{

	protected function analyseFile(string $file): void
	{
		$file = $this->getFileHelper()->normalizePath($file);
		$this->analyse([$file], $this->parseExpectedErrors($file));
	}

	protected function createThrowsAnnotationReader(): ThrowsAnnotationReader
	{
		return new ThrowsAnnotationReader(
			$this->getParser(),
			self::getContainer()->getByType(Lexer::class),
			self::getContainer()->getByType(PhpDocParser::class)
		);
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

}
