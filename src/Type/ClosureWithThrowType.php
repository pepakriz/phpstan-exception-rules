<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Type;

use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Type\ClosureType;
use PHPStan\Type\Type;

class ClosureWithThrowType extends ClosureType
{

	/**
	 * @var Type
	 */
	private $throwType;

	/**
	 * @param NativeParameterReflection[] $parameters
	 */
	public function __construct(array $parameters, Type $returnType, bool $variadic, Type $throwType)
	{
		parent::__construct($parameters, $returnType, $variadic);
		$this->throwType = $throwType;
	}

	public function getThrowType(): Type
	{
		return $this->throwType;
	}

}
