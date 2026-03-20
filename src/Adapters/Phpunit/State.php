<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit;

use Nuno_Maduro\Collision\Contracts\Adapters\Phpunit\Has_Printable_Test_Case_Name;
use Php_Unit\Event\Code\Test;
use Php_Unit\Event\Code\Test_Method;
/**
 * @internal
 */
final class State
{
    /**
     * The complete test suite tests.
     *
     * @var array<string, TestResult>
     */
    public array $suite_tests = [];
    /**
     * The current test case class.
     */
    public ?string $test_case_name;
    /**
     * The current test case tests.
     *
     * @var array<string, TestResult>
     */
    public array $test_case_tests = [];
    /**
     * The current test case tests.
     *
     * @var array<string, TestResult>
     */
    public array $to_be_printed_case_tests = [];
    /**
     * Header printed.
     */
    public bool $header_printed = false;
    /**
     * The state constructor.
     */
    public function __construct()
    {
        $this->test_case_name = '';
    }
    /**
     * Checks if the given test already contains a result.
     */
    public function exists_in_test_case(Test $test): bool
    {
        return isset($this->test_case_tests[$test->id()]);
    }
    /**
     * Adds the given test to the State.
     */
    public function add(Test_Result $test): void
    {
        $this->test_case_name = $test->test_case_name;
        $levels = array_flip([Test_Result::PASS, Test_Result::RUNS, Test_Result::TODO, Test_Result::SKIPPED, Test_Result::WARN, Test_Result::NOTICE, Test_Result::DEPRECATED, Test_Result::RISKY, Test_Result::INCOMPLETE, Test_Result::FAIL]);
        if (isset($this->test_case_tests[$test->id])) {
            $existing = $this->test_case_tests[$test->id];
            if ($levels[$existing->type] >= $levels[$test->type]) {
                return;
            }
        }
        $this->test_case_tests[$test->id] = $test;
        $this->to_be_printed_case_tests[$test->id] = $test;
        $this->suite_tests[$test->id] = $test;
    }
    /**
     * Sets the duration of the given test, and returns the test result.
     */
    public function set_duration(Test $test, float $duration): Test_Result
    {
        $result = $this->test_case_tests[$test->id()];
        $result->set_duration($duration);
        return $result;
    }
    /**
     * Gets the test case title.
     */
    public function get_test_case_title(): string
    {
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::FAIL) {
                return 'FAIL';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type !== Test_Result::PASS && $test->type !== Test_Result::TODO && $test->type !== Test_Result::DEPRECATED && $test->type !== Test_Result::NOTICE) {
                return 'WARN';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::NOTICE) {
                return 'NOTI';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::DEPRECATED) {
                return 'DEPR';
            }
        }
        if ($this->todos_count() > 0 && count($this->test_case_tests) === $this->todos_count()) {
            return 'TODO';
        }
        return 'PASS';
    }
    /**
     * Gets the number of tests that are todos.
     */
    public function todos_count(): int
    {
        return count(array_values(array_filter($this->test_case_tests, fn(Test_Result $test): bool => $test->type === Test_Result::TODO)));
    }
    /**
     * Gets the test case title color.
     */
    public function get_test_case_font_color(): string
    {
        if ($this->get_test_case_title_color() === 'blue') {
            return 'white';
        }
        return $this->get_test_case_title() === 'FAIL' ? 'default' : 'black';
    }
    /**
     * Gets the test case title color.
     */
    public function get_test_case_title_color(): string
    {
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::FAIL) {
                return 'red';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type !== Test_Result::PASS && $test->type !== Test_Result::TODO && $test->type !== Test_Result::DEPRECATED) {
                return 'yellow';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::DEPRECATED) {
                return 'yellow';
            }
        }
        foreach ($this->test_case_tests as $test) {
            if ($test->type === Test_Result::TODO) {
                return 'blue';
            }
        }
        return 'green';
    }
    /**
     * Returns the number of tests on the current test case.
     */
    public function test_case_tests_count(): int
    {
        return count($this->test_case_tests);
    }
    /**
     * Returns the number of tests on the complete test suite.
     */
    public function test_suite_tests_count(): int
    {
        return count($this->suite_tests);
    }
    /**
     * Checks if the given test case is different from the current one.
     */
    public function test_case_has_changed(Test_Method $test): bool
    {
        return self::get_printable_test_case_name($test) !== $this->test_case_name;
    }
    /**
     * Moves the an new test case.
     */
    public function move_to(Test_Method $test): void
    {
        $this->test_case_name = self::get_printable_test_case_name($test);
        $this->test_case_tests = [];
        $this->header_printed = false;
    }
    /**
     * Foreach test in the test case.
     */
    public function each_test_case_tests(callable $callback): void
    {
        foreach ($this->to_be_printed_case_tests as $test) {
            $callback($test);
        }
        $this->to_be_printed_case_tests = [];
    }
    public function count_tests_in_test_suite_by(string $type): int
    {
        return count(array_filter($this->suite_tests, fn(Test_Result $test_result) => $test_result->type === $type));
    }
    /**
     * Returns the printable test case name from the given `TestCase`.
     */
    public static function get_printable_test_case_name(Test_Method $test): string
    {
        $class_name = explode('::', $test->id())[0];
        if (is_subclass_of($class_name, Has_Printable_Test_Case_Name::class)) {
            return $class_name::get_printable_test_case_name();
        }
        return $class_name;
    }
}