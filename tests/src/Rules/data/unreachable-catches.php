<?php declare(strict_types = 1);

namespace Pepakriz\PHPStanExceptionRules\Rules\Data\UnreachableCatches;

use BadFunctionCallException;
use Exception;
use LogicException;
use OverflowException;
use RuntimeException;
use Throwable;

try {

} catch (RuntimeException $e) {

} catch (LogicException $e) {

} catch (Throwable $e) {

}

try {

} catch (LogicException | RuntimeException $e) {

} catch (Throwable $e) {

}

try {

} catch (Exception $e) {

} catch (Exception $e) { // error: Superclass of Exception has already been caught

} catch (LogicException $e) { // error: Superclass of LogicException has already been caught

} catch (Throwable $e) {

}

try {

} catch (RuntimeException $e) {

} catch (OverflowException | LogicException $e) { // error: Superclass of OverflowException has already been caught

}

try {

} catch (RuntimeException | LogicException $e) {

} catch (Exception $e) {

} catch (OverflowException $e) { // error: Superclass of OverflowException has already been caught

} catch (BadFunctionCallException $e) { // error: Superclass of BadFunctionCallException has already been caught

} catch (Throwable $e) {

}

try {

} catch (RuntimeException | OverflowException $e) { // error: Superclass of OverflowException has already been caught

}
