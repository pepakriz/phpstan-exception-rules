<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use Nette\DI\Container;
use function array_keys;
use function array_map;

class DynamicThrowTypeServiceFactory
{

	private const TAG_DYNAMIC_METHOD_THROW_TYPE = 'exceptionRules.dynamicMethodThrowTypeExtension';
	private const TAG_DYNAMIC_STATIC_METHOD_THROW_TYPE = 'exceptionRules.dynamicStaticMethodThrowTypeExtension';
	private const TAG_DYNAMIC_CONSTRUCTOR_THROW_TYPE = 'exceptionRules.dynamicConstructorThrowTypeExtension';
	private const TAG_DYNAMIC_FUNCTION_THROW_TYPE = 'exceptionRules.dynamicFunctionThrowTypeExtension';

	/**
	 * @var Container
	 */
	private $container;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	public function create(): DynamicThrowTypeService
	{
		$tagToService = function (array $tags) {
			return array_map(function (string $serviceName) {
				return $this->container->getService($serviceName);
			}, array_keys($tags));
		};

		return new DynamicThrowTypeService(
			$tagToService($this->container->findByTag(self::TAG_DYNAMIC_METHOD_THROW_TYPE)),
			$tagToService($this->container->findByTag(self::TAG_DYNAMIC_STATIC_METHOD_THROW_TYPE)),
			$tagToService($this->container->findByTag(self::TAG_DYNAMIC_CONSTRUCTOR_THROW_TYPE)),
			$tagToService($this->container->findByTag(self::TAG_DYNAMIC_FUNCTION_THROW_TYPE))
		);
	}

}
