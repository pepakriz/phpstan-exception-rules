<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules;

use PHPStan\DependencyInjection\Container;

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
		return new DynamicThrowTypeService(
			$this->container->getServicesByTag(self::TAG_DYNAMIC_METHOD_THROW_TYPE),
			$this->container->getServicesByTag(self::TAG_DYNAMIC_STATIC_METHOD_THROW_TYPE),
			$this->container->getServicesByTag(self::TAG_DYNAMIC_CONSTRUCTOR_THROW_TYPE),
			$this->container->getServicesByTag(self::TAG_DYNAMIC_FUNCTION_THROW_TYPE)
		);
	}

}
