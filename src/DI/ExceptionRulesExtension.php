<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\DI;

use Nette\DI\CompilerExtension;
use Nette\DI\ServiceDefinition;
use Pepakriz\PHPStanExceptionRules\DynamicThrowTypeService;
use function array_keys;
use function array_map;

class ExceptionRulesExtension extends CompilerExtension
{

	private const TAG_DYNAMIC_METHOD_THROW_TYPE = 'exceptionRules.dynamicMethodThrowTypeExtension';
	private const TAG_DYNAMIC_STATIC_METHOD_THROW_TYPE = 'exceptionRules.dynamicStaticMethodThrowTypeExtension';
	private const TAG_DYNAMIC_CONSTRUCTOR_THROW_TYPE = 'exceptionRules.dynamicConstructorThrowTypeExtension';
	private const TAG_DYNAMIC_FUNCTION_THROW_TYPE = 'exceptionRules.dynamicFunctionThrowTypeExtension';

	public function beforeCompile(): void
	{
		$containerBuilder = $this->getContainerBuilder();
		$containerBuilder->addDefinition($this->prefix('dynamicThrowTypeService'))
			->setFactory(DynamicThrowTypeService::class)
			->setArguments([
				$this->getServicesByNames(self::TAG_DYNAMIC_METHOD_THROW_TYPE),
				$this->getServicesByNames(self::TAG_DYNAMIC_STATIC_METHOD_THROW_TYPE),
				$this->getServicesByNames(self::TAG_DYNAMIC_CONSTRUCTOR_THROW_TYPE),
				$this->getServicesByNames(self::TAG_DYNAMIC_FUNCTION_THROW_TYPE),
			]);
	}

	/**
	 * @return ServiceDefinition[]
	 */
	private function getServicesByNames(string $tag): array
	{
		$containerBuilder = $this->getContainerBuilder();
		$names = array_keys($containerBuilder->findByTag($tag));

		return array_map(static function (string $name) use ($containerBuilder): ServiceDefinition {
			return $containerBuilder->getDefinition($name);
		}, $names);
	}

}
