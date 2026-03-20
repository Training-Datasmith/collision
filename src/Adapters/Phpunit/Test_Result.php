<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit;

use Nuno_Maduro\Collision\Contracts\Adapters\Phpunit\Has_Printable_Test_Case_Name;
use Nuno_Maduro\Collision\Exceptions\Should_Not_Happen;
use Php_Unit\Event\Code\Test;
use Php_Unit\Event\Code\Test_Method;
use Php_Unit\Event\Code\Throwable;
use Php_Unit\Event\Test\Before_First_Test_Method_Errored;
/**
 * @internal
 */
final class Test_Result
{
    public const FAIL = 'failed';
    public const SKIPPED = 'skipped';
    public const INCOMPLETE = 'incomplete';
    public const TODO = 'todo';
    public const RISKY = 'risky';
    public const DEPRECATED = 'deprecated';
    public const NOTICE = 'notice';
    public const WARN = 'warnings';
    public const RUNS = 'pending';
    public const PASS = 'passed';
    public float $duration;
    public string $warning = '';
    public string $warning_source = '';
    /**
     * Creates a new TestResult instance.
     */
    private function __construct(public string $id, public string $test_case_name, public string $description, public string $type, public string $icon, public string $compact_icon, public string $color, public string $compact_color, public array $context, public ?Throwable $throwable = null)
    {
        $this->duration = 0.0;
        $as_warning = $this->type === Test_Result::WARN || $this->type === Test_Result::RISKY || $this->type === Test_Result::SKIPPED || $this->type === Test_Result::DEPRECATED || $this->type === Test_Result::NOTICE || $this->type === Test_Result::INCOMPLETE;
        if ($this->throwable instanceof Throwable && $as_warning) {
            if (in_array($this->type, [Test_Result::DEPRECATED, Test_Result::NOTICE])) {
                foreach (explode("\n", $this->throwable->stack_trace()) as $line) {
                    if (!str_contains($line, 'vendor/nunomaduro/collision')) {
                        $this->warning_source = str_replace(getcwd() . '/', '', $line);
                        break;
                    }
                }
            }
            $this->warning .= trim((string) preg_replace("/\r|\n/", ' ', $this->throwable->message()));
            // pest specific
            $this->warning = str_replace('__pest_evaluable_', '', $this->warning);
            $this->warning = str_replace('This test depends on "P\\', 'This test depends on "', $this->warning);
        }
    }
    /**
     * Sets the telemetry information.
     */
    public function set_duration(float $duration): void
    {
        $this->duration = $duration;
    }
    /**
     * Creates a new test from the given test case.
     */
    public static function from_test_case(Test $test, string $type, ?Throwable $throwable = null): self
    {
        if (!$test instanceof Test_Method) {
            throw new Should_Not_Happen();
        }
        if (is_subclass_of($test->class_name(), Has_Printable_Test_Case_Name::class)) {
            $test_case_name = $test->class_name()::get_printable_test_case_name();
            $context = method_exists($test->class_name(), 'getPrintableContext') ? $test->class_name()::get_printable_context() : [];
        } else {
            $test_case_name = $test->class_name();
            $context = [];
        }
        $description = self::make_description($test);
        $icon = self::make_icon($type);
        $compact_icon = self::make_compact_icon($type);
        $color = self::make_color($type);
        $compact_color = self::make_compact_color($type);
        return new self($test->id(), $test_case_name, $description, $type, $icon, $compact_icon, $color, $compact_color, $context, $throwable);
    }
    /**
     * Creates a new test from the given Pest Parallel Test Case.
     */
    public static function from_pest_parallel_test_case(Test $test, string $type, ?Throwable $throwable = null): self
    {
        if (!$test instanceof Test_Method) {
            throw new Should_Not_Happen();
        }
        if (is_subclass_of($test->class_name(), Has_Printable_Test_Case_Name::class)) {
            $test_case_name = $test->class_name()::get_printable_test_case_name();
            $description = $test->test_dox()->prettified_method_name();
        } else {
            $test_case_name = $test->class_name();
            $description = self::make_description($test);
        }
        $icon = self::make_icon($type);
        $compact_icon = self::make_compact_icon($type);
        $color = self::make_color($type);
        $compact_color = self::make_compact_color($type);
        return new self($test->id(), $test_case_name, $description, $type, $icon, $compact_icon, $color, $compact_color, [], $throwable);
    }
    /**
     * Creates a new test from the given test case.
     */
    public static function from_before_first_test_method_errored(Before_First_Test_Method_Errored $event): self
    {
        if (is_subclass_of($event->test_class_name(), Has_Printable_Test_Case_Name::class)) {
            $test_case_name = $event->test_class_name()::get_printable_test_case_name();
        } else {
            $test_case_name = $event->test_class_name();
        }
        $description = '';
        $icon = self::make_icon(self::FAIL);
        $compact_icon = self::make_compact_icon(self::FAIL);
        $color = self::make_color(self::FAIL);
        $compact_color = self::make_compact_color(self::FAIL);
        return new self($test_case_name, $test_case_name, $description, self::FAIL, $icon, $compact_icon, $color, $compact_color, [], $event->throwable());
    }
    /**
     * Get the test case description.
     */
    public static function make_description(Test_Method $test): string
    {
        if (is_subclass_of($test->class_name(), Has_Printable_Test_Case_Name::class)) {
            return $test->class_name()::get_latest_printable_test_case_method_name();
        }
        $name = $test->name();
        // First, lets replace underscore by spaces.
        $name = str_replace('_', ' ', $name);
        // Then, replace upper cases by spaces.
        $name = (string) preg_replace('/([A-Z])/', ' $1', $name);
        // Finally, if it starts with `test`, we remove it.
        $name = (string) preg_replace('/^test/', '', $name);
        // Removes spaces
        $name = trim($name);
        // Lower case everything
        $name = mb_strtolower($name);
        return $name;
    }
    /**
     * Get the test case icon.
     */
    public static function make_icon(string $type): string
    {
        return match ($type) {
            self::FAIL => '⨯',
            self::SKIPPED => '-',
            self::DEPRECATED, self::WARN, self::RISKY, self::NOTICE => '!',
            self::INCOMPLETE => '…',
            self::TODO => '↓',
            self::RUNS => '•',
            default => '✓',
        };
    }
    /**
     * Get the test case compact icon.
     */
    public static function make_compact_icon(string $type): string
    {
        return match ($type) {
            self::FAIL => '⨯',
            self::SKIPPED => 's',
            self::DEPRECATED, self::NOTICE, self::WARN, self::RISKY => '!',
            self::INCOMPLETE => 'i',
            self::TODO => 't',
            self::RUNS => '•',
            default => '.',
        };
    }
    /**
     * Get the test case compact color.
     */
    public static function make_compact_color(string $type): string
    {
        return match ($type) {
            self::FAIL => 'red',
            self::DEPRECATED, self::NOTICE, self::SKIPPED, self::INCOMPLETE, self::RISKY, self::WARN, self::RUNS => 'yellow',
            self::TODO => 'cyan',
            default => 'gray',
        };
    }
    /**
     * Get the test case color.
     */
    public static function make_color(string $type): string
    {
        return match ($type) {
            self::TODO => 'cyan',
            self::FAIL => 'red',
            self::DEPRECATED, self::NOTICE, self::SKIPPED, self::INCOMPLETE, self::RISKY, self::WARN, self::RUNS => 'yellow',
            default => 'green',
        };
    }
}