<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Extension;

use DOMDocument;
use ErrorException;
use Pepakriz\PHPStanExceptionRules\DynamicMethodThrowTypeExtension;
use Pepakriz\PHPStanExceptionRules\UnsupportedClassException;
use Pepakriz\PHPStanExceptionRules\UnsupportedFunctionException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;
use function is_a;

class DOMDocumentExtension implements DynamicMethodThrowTypeExtension
{

	/**
	 * @throws UnsupportedClassException
	 * @throws UnsupportedFunctionException
	 */
	public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		if (!is_a($methodReflection->getDeclaringClass()->getName(), DOMDocument::class, true)) {
			throw new UnsupportedClassException();
		}

		if ($methodReflection->getName() === 'load' || $methodReflection->getName() === 'loadHTMLFile') {
			return new ObjectType(ErrorException::class);
		}

		if ($methodReflection->getName() === 'loadXML' || $methodReflection->getName() === 'loadHTML') {
			return $this->resolveLoadSourceType($methodCall, $scope);
		}

		throw new UnsupportedFunctionException();
	}

	private function resolveLoadSourceType(MethodCall $methodCall, Scope $scope): Type
	{
		$valueType = $scope->getType($methodCall->getArgs()[0]->value);
		$exceptionType = new ObjectType(ErrorException::class);

		foreach (TypeUtils::getConstantStrings($valueType) as $constantString) {
			if ($constantString->getValue() === '') {
				return $exceptionType;
			}

			$valueType = TypeCombinator::remove($valueType, $constantString);
		}

		if (!$valueType instanceof NeverType) {
			return $exceptionType;
		}

		return new VoidType();
	}

}
