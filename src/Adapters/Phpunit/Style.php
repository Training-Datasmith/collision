<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit;

use Closure;
use Nuno_Maduro\Collision\Adapters\Phpunit\Printers\Default_Printer;
use Nuno_Maduro\Collision\Adapters\Phpunit\Support\Result_Reflection;
use Nuno_Maduro\Collision\Exceptions\Should_Not_Happen;
use Nuno_Maduro\Collision\Exceptions\Test_Exception;
use Nuno_Maduro\Collision\Exceptions\Test_Outcome;
use Nuno_Maduro\Collision\Writer;
use Pest\Expectation;
use Php_Unit\Event\Code\Throwable;
use Php_Unit\Event\Telemetry\Info;
use Php_Unit\Framework\Expectation_Failed_Exception;
use Php_Unit\Framework\Incomplete_Test_Error;
use Php_Unit\Framework\Skipped_With_Message_Exception;
use Php_Unit\Runner\Test_Suite_Sorter;
use Php_Unit\Test_Runner\Test_Result\Test_Result as PHPUnitTestResult;
use Php_Unit\Text_Ui\Configuration\Registry;
use ReflectionClass;
use ReflectionFunction;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Console_Output_Interface;
use function Termwind\render;
use function Termwind\Render_Using;
use Termwind\Terminal;
use function Termwind\terminal;
use Whoops\Exception\Frame;
use Whoops\Exception\Inspector;
/**
 * @internal
 */
final class Style
{
    private int $compact_processed = 0;
    private int $compact_symbols_per_line = 0;
    private readonly Terminal $terminal;
    private readonly Console_Output $output;
    /**
     * @var string[]
     */
    private const TYPES = [Test_Result::DEPRECATED, Test_Result::FAIL, Test_Result::WARN, Test_Result::RISKY, Test_Result::INCOMPLETE, Test_Result::NOTICE, Test_Result::TODO, Test_Result::SKIPPED, Test_Result::PASS];
    /**
     * Style constructor.
     */
    public function __construct(Console_Output_Interface $output)
    {
        if (!$output instanceof Console_Output) {
            throw new Should_Not_Happen();
        }
        $this->terminal = terminal();
        $this->output = $output;
        $this->compact_symbols_per_line = $this->terminal->width() - 4;
    }
    /**
     * Prints the content similar too:.
     *
     * ```
     *    WARN  Your XML configuration validates against a deprecated schema...
     * ```
     */
    public function write_warning(string $message): void
    {
        $this->output->writeln(['', '  <fg=black;bg=yellow;options=bold> WARN </> ' . $message]);
    }
    /**
     * Prints the content similar too:.
     *
     * ```
     *    WARN  Your XML configuration validates against a deprecated schema...
     * ```
     */
    public function write_throwable(\Throwable $throwable): void
    {
        $this->output->writeln(['', '  <fg=white;bg=red;options=bold> ERROR </> ' . $throwable->get_message()]);
    }
    /**
     * Prints the content similar too:.
     *
     * ```
     *    PASS  Unit\ExampleTest
     *    ✓ basic test
     * ```
     */
    public function write_current_test_case_summary(State $state): void
    {
        if ($state->test_case_tests_count() === 0 || is_null($state->test_case_name)) {
            return;
        }
        if (!$state->header_printed && !Default_Printer::compact()) {
            $this->output->writeln($this->title_line_from($state->get_test_case_font_color(), $state->get_test_case_title_color(), $state->get_test_case_title(), $state->test_case_name, $state->todos_count()));
            $state->header_printed = true;
        }
        $state->each_test_case_tests(function (Test_Result $test_result): void {
            if ($test_result->description !== '') {
                if (Default_Printer::compact()) {
                    $this->write_compact_description_line($test_result);
                } else {
                    $this->write_description_line($test_result);
                }
            }
        });
    }
    /**
     * Prints the content similar too:.
     *
     * ```
     *    PASS  Unit\ExampleTest
     *    ✓ basic test
     * ```
     */
    public function write_errors_summary(State $state): void
    {
        $configuration = Registry::get();
        $fail_types = [Test_Result::FAIL];
        if ($configuration->display_details_on_tests_that_trigger_notices()) {
            $fail_types[] = Test_Result::NOTICE;
        }
        if ($configuration->display_details_on_tests_that_trigger_deprecations()) {
            $fail_types[] = Test_Result::DEPRECATED;
        }
        if ($configuration->fail_on_warning() || $configuration->display_details_on_tests_that_trigger_warnings()) {
            $fail_types[] = Test_Result::WARN;
        }
        if ($configuration->fail_on_risky()) {
            $fail_types[] = Test_Result::RISKY;
        }
        if ($configuration->fail_on_incomplete() || $configuration->display_details_on_incomplete_tests()) {
            $fail_types[] = Test_Result::INCOMPLETE;
        }
        if ($configuration->fail_on_skipped() || $configuration->display_details_on_skipped_tests()) {
            $fail_types[] = Test_Result::SKIPPED;
        }
        $fail_types = array_unique($fail_types);
        $errors = array_values(array_filter($state->suite_tests, fn(Test_Result $test_result): bool => in_array($test_result->type, $fail_types, true)));
        array_map(function (Test_Result $test_result): void {
            if (!$test_result->throwable instanceof Throwable) {
                throw new Should_Not_Happen();
            }
            render_using($this->output);
            render(<<<'HTML'
                <div class="mx-2 text-red">
                    <hr/>
                </div>
            HTML);
            $test_case_name = $test_result->test_case_name;
            $description = $test_result->description;
            /** @var class-string $throwableClassName */
            $throwable_class_name = $test_result->throwable->class_name();
            $throwable_class_name = !in_array($throwable_class_name, [Expectation_Failed_Exception::class, Incomplete_Test_Error::class, Skipped_With_Message_Exception::class, Test_Outcome::class], true) ? sprintf('<span class="px-1 bg-red font-bold">%s</span>', (new ReflectionClass($throwable_class_name))->get_short_name()) : '';
            $truncate_classes = $this->output->is_verbose() ? '' : 'flex-1 truncate';
            render_using($this->output);
            render(sprintf(<<<'HTML'
                <div class="flex justify-between mx-2">
                    <span class="%s">
                        <span class="px-1 bg-%s %s font-bold uppercase">%s</span> <span class="font-bold">%s</span><span class="text-gray mx-1">></span><span>%s</span>
                    </span>
                    <span class="ml-1">
                        %s
                    </span>
                </div>
            HTML, $truncate_classes, $test_result->color === 'yellow' ? 'yellow-400' : $test_result->color, $test_result->color === 'yellow' ? 'text-black' : '', $test_result->type, $test_case_name, $description, $throwable_class_name));
            $this->write_error($test_result->throwable);
        }, $errors);
    }
    /**
     * Writes the final recap.
     */
    public function write_recap(State $state, Info $telemetry, Php_Unit_Test_Result $result): void
    {
        $tests = [];
        foreach (self::TYPES as $type) {
            if (($count_tests = $state->count_tests_in_test_suite_by($type)) !== 0) {
                $color = Test_Result::make_color($type);
                if ($type === Test_Result::WARN && $count_tests < 2) {
                    $type = 'warning';
                }
                if ($type === Test_Result::NOTICE && $count_tests > 1) {
                    $type = 'notices';
                }
                if ($type === Test_Result::TODO && $count_tests > 1) {
                    $type = 'todos';
                }
                $tests[] = "<fg={$color};options=bold>{$count_tests} {$type}</>";
            }
        }
        $pending = Result_Reflection::number_of_tests($result) - $result->number_of_tests_run();
        if ($pending > 0) {
            $tests[] = "\x1b[2m{$pending} pending\x1b[22m";
        }
        $time_elapsed = number_format($telemetry->duration_since_start()->as_float(), 2, '.', '');
        $this->output->writeln(['']);
        if (!empty($tests)) {
            $this->output->writeln([sprintf('  <fg=gray>Tests:</>    <fg=default>%s</><fg=gray> (%s assertions)</>', implode('<fg=gray>,</> ', $tests), $result->number_of_assertions())]);
        }
        $this->output->writeln([sprintf('  <fg=gray>Duration:</> <fg=default>%ss</>', $time_elapsed)]);
        $configuration = Registry::get();
        if ($configuration->execution_order() === Test_Suite_Sorter::ORDER_RANDOMIZED) {
            $this->output->writeln([sprintf('  <fg=gray>Random Order Seed:</> <fg=default>%s</>', $configuration->random_order_seed())]);
        }
        $this->output->writeln('');
    }
    /**
     * @param  array<int, TestResult>  $slowTests
     */
    public function write_slow_tests(array $slow_tests, Info $telemetry): void
    {
        $this->output->writeln('  <fg=gray>Top 10 slowest tests:</>');
        $time_elapsed = $telemetry->duration_since_start()->as_float();
        foreach ($slow_tests as $test_result) {
            $seconds = number_format($test_result->duration / 1000, 2, '.', '');
            $color = $test_result->duration / 1000 > $time_elapsed * 0.25 ? 'red' : ($test_result->duration > $time_elapsed * 0.1 ? 'yellow' : 'gray');
            render_using($this->output);
            render(sprintf(<<<'HTML'
                <div class="flex justify-between space-x-1 mx-2">
                    <span class="flex-1">
                        <span class="font-bold">%s</span><span class="text-gray mx-1">></span><span class="text-gray">%s</span>
                    </span>
                    <span class="ml-1 font-bold text-%s">
                        %ss
                    </span>
                </div>
            HTML, $test_result->test_case_name, $test_result->description, $color, $seconds));
        }
        $time_elapsed_in_slow_tests = array_sum(array_map(fn(Test_Result $test_result): float => $test_result->duration / 1000, $slow_tests));
        $time_elapsed_as_string = number_format($time_elapsed, 2, '.', '');
        $percentage_in_slow_tests_as_string = number_format($time_elapsed_in_slow_tests * 100 / $time_elapsed, 2, '.', '');
        $time_elapsed_in_slow_tests_as_string = number_format($time_elapsed_in_slow_tests, 2, '.', '');
        render_using($this->output);
        render(sprintf(<<<'HTML'
            <div class="mx-2 mb-1 flex">
                <div class="text-gray">
                    <hr/>
                </div>
                <div class="flex space-x-1 justify-between">
                    <span>
                    </span>
                    <span>
                        <span class="text-gray">(%s%% of %ss)</span>
                        <span class="ml-1 font-bold">%ss</span>
                    </span>
                </div>
            </div>
        HTML, $percentage_in_slow_tests_as_string, $time_elapsed_as_string, $time_elapsed_in_slow_tests_as_string));
    }
    /**
     * Displays the error using Collision's writer and terminates with exit code === 1.
     */
    public function write_error(Throwable $throwable): void
    {
        $writer = (new Writer())->set_output($this->output);
        $throwable = new Test_Exception($throwable, $this->output->is_verbose());
        $writer->show_title(false);
        $writer->ignore_files_in(['/vendor\/nunomaduro\/collision/', '/vendor\/bin\/pest/', '/bin\/pest/', '/vendor\/brianium\/paratest/', '/vendor\/pestphp\/pest/', '/vendor\/pestphp\/pest-plugin-arch/', '/vendor\/pestphp\/pest-plugin-browser/', '/vendor\/phpspec\/prophecy-phpunit/', '/vendor\/phpspec\/prophecy/', '/vendor\/phpunit\/phpunit\/src/', '/vendor\/mockery\/mockery/', '/vendor\/laravel\/dusk/', '/Illuminate\/Testing/', '/Illuminate\/Foundation\/Testing/', '/Illuminate\/Foundation\/Bootstrap\/HandleExceptions/', '/vendor\/symfony\/framework-bundle\/Test/', '/vendor\/symfony\/phpunit-bridge/', '/vendor\/symfony\/dom-crawler/', '/vendor\/symfony\/browser-kit/', '/vendor\/symfony\/css-selector/', '/vendor\/bin\/.phpunit/', '/bin\/.phpunit/', '/vendor\/bin\/simple-phpunit/', '/bin\/phpunit/', '/vendor\/coduo\/php-matcher\/src\/PHPUnit/', '/vendor\/sulu\/sulu\/src\/Sulu\/Bundle\/TestBundle\/Testing/', '/vendor\/webmozart\/assert/', $this->ignore_pest_pipes(...), $this->ignore_pest_extends(...), $this->ignore_pest_interceptors(...)]);
        /** @var \Throwable $throwable */
        $inspector = new Inspector($throwable);
        $writer->write($inspector);
    }
    /**
     * Returns the title contents.
     */
    private function title_line_from(string $fg, string $bg, string $title, string $test_case_name, int $todos): string
    {
        return sprintf("\n  <fg=%s;bg=%s;options=bold> %s </><fg=default> %s</>%s", $fg, $bg, $title, $test_case_name, $todos > 0 ? sprintf('<fg=gray> - %s todo%s</>', $todos, $todos > 1 ? 's' : '') : '');
    }
    /**
     * Writes a description line.
     */
    private function write_compact_description_line(Test_Result $result): void
    {
        $symbols_on_current_line = $this->compact_processed % $this->compact_symbols_per_line;
        if ($symbols_on_current_line >= $this->terminal->width() - 4) {
            $symbols_on_current_line = 0;
        }
        if ($symbols_on_current_line === 0) {
            $this->output->writeln('');
            $this->output->write('  ');
        }
        $this->output->write(sprintf('<fg=%s;options=bold>%s</>', $result->compact_color, $result->compact_icon));
        $this->compact_processed++;
    }
    /**
     * Writes a description line.
     */
    private function write_description_line(Test_Result $result): void
    {
        if (!empty($warning = $result->warning)) {
            if (!str_contains($warning, "\n")) {
                $warning = sprintf(' → %s', $warning);
            } else {
                $warning_lines = explode("\n", $warning);
                $warning = '';
                foreach ($warning_lines as $w) {
                    $warning .= sprintf("\n    <fg=yellow;options=bold>⇂ %s</>", trim($w));
                }
            }
        }
        $seconds = '';
        if ($result->duration / 1000 > 0.0) {
            $seconds = number_format($result->duration / 1000, 2, '.', '');
            $seconds = $seconds !== '0.00' ? sprintf('<span class="text-gray mr-2">%ss</span>', $seconds) : '';
        }
        if (isset($_SERVER['REBUILD_SNAPSHOTS']) || isset($_SERVER['COLLISION_IGNORE_DURATION']) && $_SERVER['COLLISION_IGNORE_DURATION'] === 'true') {
            $seconds = '';
        }
        $truncate_classes = $this->output->is_verbose() ? '' : 'flex-1 truncate';
        if ($warning !== '') {
            $warning = sprintf('<span class="ml-1 text-yellow">%s</span>', $warning);
            if (!empty($result->warning_source)) {
                $warning .= ' // ' . $result->warning_source;
            }
        }
        $description = $result->description;
        /** @var string $description */
        $description = preg_replace('/`([^`]+)`/', '<span class="text-white">$1</span>', $description);
        if (class_exists(\Pest\Collision\Events::class)) {
            $description = \Pest\Collision\Events::before_test_method_description($result, $description);
        }
        render_using($this->output);
        render(sprintf(<<<'HTML'
            <div class="%s ml-2">
                <span class="%s text-gray">
                    <span class="text-%s font-bold">%s</span><span class="ml-1 text-gray">%s</span>%s
                </span>%s
            </div>
        HTML, $seconds === '' ? '' : 'flex space-x-1 justify-between', $truncate_classes, $result->color, $result->icon, $description, $warning, $seconds));
        class_exists(\Pest\Collision\Events::class) && \Pest\Collision\Events::after_test_method_description($result);
    }
    /**
     * @param  Frame  $frame
     */
    private function ignore_pest_pipes($frame): bool
    {
        if (class_exists(Expectation::class)) {
            $reflection = new ReflectionClass(Expectation::class);
            /** @var array<string, array<Closure(Closure, mixed ...$arguments): void>> $expectationPipes */
            $expectation_pipes = $reflection->get_static_property_value('pipes', []);
            foreach ($expectation_pipes as $pipes) {
                foreach ($pipes as $pipe_closure) {
                    if ($this->is_frame_in_closure($frame, $pipe_closure)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    /**
     * @param  Frame  $frame
     */
    private function ignore_pest_extends($frame): bool
    {
        if (class_exists(Expectation::class)) {
            $reflection = new ReflectionClass(Expectation::class);
            /** @var array<string, Closure> $extends */
            $extends = $reflection->get_static_property_value('extends', []);
            foreach ($extends as $extend_closure) {
                if ($this->is_frame_in_closure($frame, $extend_closure)) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * @param  Frame  $frame
     */
    private function ignore_pest_interceptors($frame): bool
    {
        if (class_exists(Expectation::class)) {
            $reflection = new ReflectionClass(Expectation::class);
            /** @var array<string, array<Closure(Closure, mixed ...$arguments): void>> $expectationInterceptors */
            $expectation_interceptors = $reflection->get_static_property_value('interceptors', []);
            foreach ($expectation_interceptors as $pipes) {
                foreach ($pipes as $pipe_closure) {
                    if ($this->is_frame_in_closure($frame, $pipe_closure)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
    /**
     * @param  Frame  $frame
     */
    private function is_frame_in_closure($frame, Closure $closure): bool
    {
        $reflection = new ReflectionFunction($closure);
        $sanitized_path = str_replace('\\', '/', (string) $frame->get_file());
        /** @phpstan-ignore-next-line */
        $sanitized_closure_path = str_replace('\\', '/', $reflection->get_file_name());
        if ($sanitized_path !== $sanitized_closure_path) {
            return false;
        }
        if ($reflection->get_start_line() <= $frame->get_line() && $frame->get_line() <= $reflection->get_end_line()) {
            return true;
        }
        return false;
    }
}