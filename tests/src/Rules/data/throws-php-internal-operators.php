<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\PhpInternalOperators;

use function rand;

class Example
{

	public function test(): void
	{
		$a = 5;
		/** @var int $b */
		$b = rand(0, 99999);

		echo $a / 1;
		echo $a / 0; // error: Missing @throws DivisionByZeroError annotation
		echo $a / (rand(0, 1) === 0 ? 0 : 1); // error: Missing @throws DivisionByZeroError annotation
		echo $a / $b; // error: Missing @throws ArithmeticError annotation
		echo (rand(0, 1) === 0 ? 20 : 10) / (rand(0, 1) === 0 ? 5 : 10);

		echo $a /= 1;
		echo $a /= 0; // error: Missing @throws DivisionByZeroError annotation
		echo $a /= (rand(0, 1) === 0 ? 0 : 1); // error: Missing @throws DivisionByZeroError annotation
		echo $a /= $b; // error: Missing @throws ArithmeticError annotation
		echo $a /= (rand(0, 1) === 0 ? 5 : 10);

		echo $a % 1;
		echo $a % 0; // error: Missing @throws DivisionByZeroError annotation
		echo $a % (rand(0, 1) === 0 ? 0 : 1); // error: Missing @throws DivisionByZeroError annotation
		echo $a % $b; // error: Missing @throws ArithmeticError annotation
		echo (rand(0, 1) === 0 ? 20 : 10) % (rand(0, 1) === 0 ? 5 : 10);

		echo $a %= 1;
		echo $a %= 0; // error: Missing @throws DivisionByZeroError annotation
		echo $a %= (rand(0, 1) === 0 ? 0 : 1); // error: Missing @throws DivisionByZeroError annotation
		echo $a %= $b; // error: Missing @throws ArithmeticError annotation
		echo $a %= (rand(0, 1) === 0 ? 5 : 10);

		echo $a << 0;
		echo $a << -3; // error: Missing @throws ArithmeticError annotation
		echo $a << (rand(0, 1) === 0 ? 0 : -1); // error: Missing @throws ArithmeticError annotation
		echo $a << $b; // error: Missing @throws ArithmeticError annotation
		echo $a << (rand(0, 1) === 0 ? 0 : 1);

		echo $a <<= 0;
		echo $a <<= -3; // error: Missing @throws ArithmeticError annotation
		echo $a <<= (rand(0, 1) === 0 ? 0 : -1); // error: Missing @throws ArithmeticError annotation
		echo $a <<= $b; // error: Missing @throws ArithmeticError annotation
		echo $a <<= (rand(0, 1) === 0 ? 0 : 1);

		echo $a >> 0;
		echo $a >> -3; // error: Missing @throws ArithmeticError annotation
		echo $a >> (rand(0, 1) === 0 ? 0 : -1); // error: Missing @throws ArithmeticError annotation
		echo $a >> $b; // error: Missing @throws ArithmeticError annotation
		echo $a >> (rand(0, 1) === 0 ? 0 : 1);

		echo $a >>= 0;
		echo $a >>= -3; // error: Missing @throws ArithmeticError annotation
		echo $a >>= (rand(0, 1) === 0 ? 0 : -1); // error: Missing @throws ArithmeticError annotation
		echo $a >>= $b; // error: Missing @throws ArithmeticError annotation
		echo $a >>= (rand(0, 1) === 0 ? 0 : 1);
	}

}
