<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Contracts\Adapters\Phpunit;

/**
 * @internal
 */
interface Has_Printable_Test_Case_Name
{
    /**
     * The printable test case name.
     */
    public static function get_printable_test_case_name(): string;
    /**
     * The printable test case method name.
     */
    public function get_printable_test_case_method_name(): string;
    /**
     * The "latest" printable test case method name.
     */
    public static function get_latest_printable_test_case_method_name(): string;
}