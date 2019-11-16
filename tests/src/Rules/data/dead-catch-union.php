<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion;

use LogicException;
use RuntimeException;

class FooException extends RuntimeException {}
class BarException extends FooException {}
class BazException extends BarException {}

class DeadCatchUnion
{

	public function correctUnion(): void
	{
		try {
		} catch (RuntimeException | LogicException $e) {

		}
	}

    public function theSameUnion(): void
    {
        try {
        } catch (FooException | FooException $e) { // error: Type Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\FooException is caught twice

        }
    }

    public function deadUnion(): void
    {
        try {
        } catch (FooException | BarException $e) { // error: Type Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\BarException is already caught by Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\FooException

        }
    }

	public function multiDeadUnion(): void
	{
		try {
		} catch (FooException | BarException | BazException $e) { // error: Type Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\BarException is already caught by Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\FooException; Type Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\BazException is already caught by Pepakriz\PHPStanExceptionRules\Rules\DeadCatchUnion\FooException

		}
	}

}
