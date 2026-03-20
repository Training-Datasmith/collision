<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit\Printers;

use Nuno_Maduro\Collision\Adapters\Phpunit\Configure_Io;
use Nuno_Maduro\Collision\Adapters\Phpunit\State;
use Nuno_Maduro\Collision\Adapters\Phpunit\Style;
use Nuno_Maduro\Collision\Adapters\Phpunit\Support\Result_Reflection;
use Nuno_Maduro\Collision\Adapters\Phpunit\Test_Result;
use Nuno_Maduro\Collision\Exceptions\Should_Not_Happen;
use Nuno_Maduro\Collision\Exceptions\Test_Outcome;
use Pest\Result;
use Php_Unit\Event\Code\Test_Method;
use Php_Unit\Event\Code\Throwable_Builder;
use Php_Unit\Event\Test\Before_First_Test_Method_Errored;
use Php_Unit\Event\Test\Considered_Risky;
use Php_Unit\Event\Test\Deprecation_Triggered;
use Php_Unit\Event\Test\Errored;
use Php_Unit\Event\Test\Failed;
use Php_Unit\Event\Test\Finished;
use Php_Unit\Event\Test\Marked_Incomplete;
use Php_Unit\Event\Test\Notice_Triggered;
use Php_Unit\Event\Test\Passed;
use Php_Unit\Event\Test\Php_Deprecation_Triggered;
use Php_Unit\Event\Test\Php_Notice_Triggered;
use Php_Unit\Event\Test\Phpunit_Deprecation_Triggered;
use Php_Unit\Event\Test\Phpunit_Error_Triggered;
use Php_Unit\Event\Test\Phpunit_Warning_Triggered;
use Php_Unit\Event\Test\Php_Warning_Triggered;
use Php_Unit\Event\Test\Preparation_Started;
use Php_Unit\Event\Test\Printed_Unexpected_Output;
use Php_Unit\Event\Test\Skipped;
use Php_Unit\Event\Test\Warning_Triggered;
use Php_Unit\Event\Test_Runner\Deprecation_Triggered as TestRunnerDeprecationTriggered;
use Php_Unit\Event\Test_Runner\Execution_Finished;
use Php_Unit\Event\Test_Runner\Warning_Triggered as TestRunnerWarningTriggered;
use Php_Unit\Framework\Incomplete_Test_Error;
use Php_Unit\Framework\Skipped_With_Message_Exception;
use Php_Unit\Test_Runner\Test_Result\Facade;
use Php_Unit\Text_Ui\Configuration\Registry;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Throwable;
/**
 * @internal
 */
final class Default_Printer
{
    /**
     * The output instance.
     */
    private readonly Console_Output $output;
    /**
     * The state instance.
     */
    private readonly State $state;
    /**
     * The style instance.
     */
    private readonly Style $style;
    /**
     * If the printer should be compact.
     */
    private static bool $compact = false;
    /**
     * If the printer should profile.
     */
    private static bool $profile = false;
    /**
     * When profiling, holds a list of slow tests.
     */
    private array $profile_slow_tests = [];
    /**
     * The test started at in microseconds.
     */
    private float $test_started_at = 0.0;
    /**
     * If the printer should be verbose.
     */
    private static bool $verbose = false;
    /**
     * Creates a new Printer instance.
     */
    public function __construct(bool $colors)
    {
        $this->output = new Console_Output(Output_Interface::VERBOSITY_NORMAL, $colors);
        Configure_Io::of(new Argv_Input(), $this->output);
        class_exists(\Pest\Collision\Events::class) && \Pest\Collision\Events::set_output($this->output);
        self::$verbose = $this->output->is_verbose();
        $this->style = new Style($this->output);
        $this->state = new State();
    }
    /**
     * If the printer instances should be compact.
     */
    public static function compact(?bool $value = null): bool
    {
        if (!is_null($value)) {
            self::$compact = $value;
        }
        return !self::$verbose && self::$compact;
    }
    /**
     * If the printer instances should profile.
     */
    public static function profile(?bool $value = null): bool
    {
        if (!is_null($value)) {
            self::$profile = $value;
        }
        return self::$profile;
    }
    /**
     * Defines if the output should be decorated or not.
     */
    public function set_decorated(bool $decorated): void
    {
        $this->output->set_decorated($decorated);
    }
    /**
     * Listen to the runner execution started event.
     */
    public function test_printed_unexpected_output(Printed_Unexpected_Output $printed_unexpected_output): void
    {
        $this->output->write($printed_unexpected_output->output());
    }
    /**
     * Listen to the runner execution started event.
     */
    public function test_runner_execution_started(): void
    {
        // ..
    }
    /**
     * Listen to the test finished event.
     */
    public function test_finished(Finished $event): void
    {
        $duration = (hrtime(true) - $this->test_started_at) / 1000000;
        $test = $event->test();
        if (!$test instanceof Test_Method) {
            throw new Should_Not_Happen();
        }
        if (!$this->state->exists_in_test_case($event->test())) {
            $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::PASS));
        }
        $result = $this->state->set_duration($test, $duration);
        if (self::$profile) {
            $this->profile_slow_tests[$event->test()->id()] = $result;
            // Sort the slow tests by time, and keep only 10 of them.
            uasort($this->profile_slow_tests, static fn(Test_Result $a, Test_Result $b) => $b->duration <=> $a->duration);
            $this->profile_slow_tests = array_slice($this->profile_slow_tests, 0, 10);
        }
    }
    /**
     * Listen to the test prepared event.
     */
    public function test_preparation_started(Preparation_Started $event): void
    {
        $this->test_started_at = hrtime(true);
        $test = $event->test();
        if (!$test instanceof Test_Method) {
            throw new Should_Not_Happen();
        }
        if ($this->state->test_case_has_changed($test)) {
            $this->style->write_current_test_case_summary($this->state);
            $this->state->move_to($test);
        }
    }
    /**
     * Listen to the test errored event.
     */
    public function test_before_first_test_method_errored(Before_First_Test_Method_Errored $event): void
    {
        $this->state->add(Test_Result::from_before_first_test_method_errored($event));
    }
    /**
     * Listen to the test errored event.
     */
    public function test_errored(Errored $event): void
    {
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::FAIL, $event->throwable()));
    }
    /**
     * Listen to the test failed event.
     */
    public function test_failed(Failed $event): void
    {
        $throwable = $event->throwable();
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::FAIL, $throwable));
    }
    /**
     * Listen to the test marked incomplete event.
     */
    public function test_marked_incomplete(Marked_Incomplete $event): void
    {
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::INCOMPLETE, $event->throwable()));
    }
    /**
     * Listen to the test considered risky event.
     */
    public function test_considered_risky(Considered_Risky $event): void
    {
        $throwable = Throwable_Builder::from(new Incomplete_Test_Error($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::RISKY, $throwable));
    }
    /**
     * Listen to the test runner deprecation triggered.
     */
    public function test_runner_deprecation_triggered(Test_Runner_Deprecation_Triggered $event): void
    {
        $this->style->write_warning($event->message());
    }
    /**
     * Listen to the test runner warning triggered.
     */
    public function test_runner_warning_triggered(Test_Runner_Warning_Triggered $event): void
    {
        if (!str_starts_with($event->message(), 'No tests found in class')) {
            $this->style->write_warning($event->message());
        }
    }
    /**
     * Listen to the test runner warning triggered.
     */
    public function test_php_deprecation_triggered(Php_Deprecation_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::DEPRECATED, $throwable));
    }
    /**
     * Listen to the test runner notice triggered.
     */
    public function test_php_notice_triggered(Php_Notice_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::NOTICE, $throwable));
    }
    /**
     * Listen to the test php warning triggered event.
     */
    public function test_php_warning_triggered(Php_Warning_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::WARN, $throwable));
    }
    /**
     * Listen to the test runner warning triggered.
     */
    public function test_phpunit_warning_triggered(Phpunit_Warning_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::WARN, $throwable));
    }
    /**
     * Listen to the test deprecation triggered event.
     */
    public function test_deprecation_triggered(Deprecation_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::DEPRECATED, $throwable));
    }
    /**
     * Listen to the test phpunit deprecation triggered event.
     */
    public function test_phpunit_deprecation_triggered(Phpunit_Deprecation_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::DEPRECATED, $throwable));
    }
    /**
     * Listen to the test phpunit error triggered event.
     */
    public function test_phpunit_error_triggered(Phpunit_Error_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::FAIL, $throwable));
    }
    /**
     * Listen to the test warning triggered event.
     */
    public function test_notice_triggered(Notice_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::NOTICE, $throwable));
    }
    /**
     * Listen to the test warning triggered event.
     */
    public function test_warning_triggered(Warning_Triggered $event): void
    {
        $throwable = Throwable_Builder::from(new Test_Outcome($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::WARN, $throwable));
    }
    /**
     * Listen to the test skipped event.
     */
    public function test_skipped(Skipped $event): void
    {
        if ($event->message() === '__TODO__') {
            $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::TODO));
            return;
        }
        $throwable = Throwable_Builder::from(new Skipped_With_Message_Exception($event->message()));
        $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::SKIPPED, $throwable));
    }
    /**
     * Listen to the test finished event.
     */
    public function test_passed(Passed $event): void
    {
        if (!$this->state->exists_in_test_case($event->test())) {
            $this->state->add(Test_Result::from_test_case($event->test(), Test_Result::PASS));
        }
    }
    /**
     * Listen to the runner execution finished event.
     */
    public function test_runner_execution_finished(Execution_Finished $event): void
    {
        $result = Facade::result();
        if (Result_Reflection::number_of_tests(Facade::result()) === 0) {
            $this->output->writeln(['', '  <fg=white;options=bold;bg=blue> INFO </> No tests found.', '']);
            return;
        }
        $this->style->write_current_test_case_summary($this->state);
        if (self::$compact) {
            $this->output->writeln(['']);
        }
        if (class_exists(Result::class)) {
            $failed = Result::failed(Registry::get(), Facade::result());
        } else {
            $failed = !Facade::result()->was_successful();
        }
        $this->style->write_errors_summary($this->state);
        $this->style->write_recap($this->state, $event->telemetry_info(), $result);
        if (!$failed && count($this->profile_slow_tests) > 0) {
            $this->style->write_slow_tests($this->profile_slow_tests, $event->telemetry_info());
        }
    }
    /**
     * Reports the given throwable.
     */
    public function report(Throwable $throwable): void
    {
        $this->style->write_error(Throwable_Builder::from($throwable));
    }
}