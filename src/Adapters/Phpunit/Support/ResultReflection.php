<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit\Support;

use Php_Unit\Test_Runner\Test_Result\Test_Result;
/**
 * @internal
 */
final class Result_Reflection
{
    /**
     * The number of processed tests.
     */
    public static function number_of_tests(Test_Result $test_result): int
    {
        return (fn() => $this->number_of_tests)->call($test_result);
    }
}