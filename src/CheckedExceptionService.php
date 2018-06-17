<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use function is_a;

class CheckedExceptionService
{

	/**
	 * @var string[]
	 */
	private $exceptionWhiteList;

	/**
	 * @var string[]
	 */
	private $exceptionBlackList;

	/**
	 * @param string[] $exceptionWhiteList
	 * @param string[] $exceptionBlackList
	 */
	public function __construct(
		array $exceptionWhiteList,
		array $exceptionBlackList
	)
	{
		$this->exceptionWhiteList = $exceptionWhiteList;
		$this->exceptionBlackList = $exceptionBlackList;
	}

	public function isExceptionClassWhitelisted(string $exceptionClassName): bool
	{
		foreach ($this->exceptionWhiteList as $whitelistedException) {
			if (is_a($exceptionClassName, $whitelistedException, true)) {
				foreach ($this->exceptionBlackList as $blacklistedException) {
					if (!is_a($exceptionClassName, $blacklistedException, true)) {
						continue;
					}

					if (is_a($blacklistedException, $whitelistedException, true)) {
						continue 2;
					}
				}

				return true;
			}
		}

		return false;
	}

}
