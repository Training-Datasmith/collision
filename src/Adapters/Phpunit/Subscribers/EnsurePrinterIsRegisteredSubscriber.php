<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit\Subscribers;

use Nuno_Maduro\Collision\Adapters\Phpunit\Printers\Default_Printer;
use Nuno_Maduro\Collision\Adapters\Phpunit\Printers\Reportable_Printer;
use Php_Unit\Event\Application\Started;
use Php_Unit\Event\Application\Started_Subscriber;
use Php_Unit\Event\Facade;
use Php_Unit\Event\Test\Before_First_Test_Method_Errored;
use Php_Unit\Event\Test\Before_First_Test_Method_Errored_Subscriber;
use Php_Unit\Event\Test\Considered_Risky;
use Php_Unit\Event\Test\Considered_Risky_Subscriber;
use Php_Unit\Event\Test\Deprecation_Triggered;
use Php_Unit\Event\Test\Deprecation_Triggered_Subscriber;
use Php_Unit\Event\Test\Errored;
use Php_Unit\Event\Test\Errored_Subscriber;
use Php_Unit\Event\Test\Failed;
use Php_Unit\Event\Test\Failed_Subscriber;
use Php_Unit\Event\Test\Finished;
use Php_Unit\Event\Test\Finished_Subscriber;
use Php_Unit\Event\Test\Marked_Incomplete;
use Php_Unit\Event\Test\Marked_Incomplete_Subscriber;
use Php_Unit\Event\Test\Notice_Triggered;
use Php_Unit\Event\Test\Notice_Triggered_Subscriber;
use Php_Unit\Event\Test\Passed;
use Php_Unit\Event\Test\Passed_Subscriber;
use Php_Unit\Event\Test\Php_Deprecation_Triggered;
use Php_Unit\Event\Test\Php_Deprecation_Triggered_Subscriber;
use Php_Unit\Event\Test\Php_Notice_Triggered;
use Php_Unit\Event\Test\Php_Notice_Triggered_Subscriber;
use Php_Unit\Event\Test\Phpunit_Deprecation_Triggered;
use Php_Unit\Event\Test\Phpunit_Deprecation_Triggered_Subscriber;
use Php_Unit\Event\Test\Phpunit_Error_Triggered;
use Php_Unit\Event\Test\Phpunit_Error_Triggered_Subscriber;
use Php_Unit\Event\Test\Phpunit_Warning_Triggered;
use Php_Unit\Event\Test\Phpunit_Warning_Triggered_Subscriber;
use Php_Unit\Event\Test\Php_Warning_Triggered;
use Php_Unit\Event\Test\Php_Warning_Triggered_Subscriber;
use Php_Unit\Event\Test\Preparation_Started;
use Php_Unit\Event\Test\Preparation_Started_Subscriber;
use Php_Unit\Event\Test\Printed_Unexpected_Output;
use Php_Unit\Event\Test\Printed_Unexpected_Output_Subscriber;
use Php_Unit\Event\Test\Skipped;
use Php_Unit\Event\Test\Skipped_Subscriber;
use Php_Unit\Event\Test\Warning_Triggered;
use Php_Unit\Event\Test\Warning_Triggered_Subscriber;
use Php_Unit\Event\Test_Runner\Configured;
use Php_Unit\Event\Test_Runner\Configured_Subscriber;
use Php_Unit\Event\Test_Runner\Deprecation_Triggered as TestRunnerDeprecationTriggered;
use Php_Unit\Event\Test_Runner\Deprecation_Triggered_Subscriber as TestRunnerDeprecationTriggeredSubscriber;
use Php_Unit\Event\Test_Runner\Execution_Finished;
use Php_Unit\Event\Test_Runner\Execution_Finished_Subscriber;
use Php_Unit\Event\Test_Runner\Execution_Started;
use Php_Unit\Event\Test_Runner\Execution_Started_Subscriber;
use Php_Unit\Event\Test_Runner\Warning_Triggered as TestRunnerWarningTriggered;
use Php_Unit\Event\Test_Runner\Warning_Triggered_Subscriber as TestRunnerWarningTriggeredSubscriber;
use Php_Unit\Runner\Version;
if (class_exists(Version::class) && (int) Version::series() >= 10) {
    /**
     * @internal
     */
    final class Ensure_Printer_Is_Registered_Subscriber implements Started_Subscriber
    {
        /**
         * If this subscriber has been registered on PHPUnit's facade.
         */
        private static bool $registered = false;
        /**
         * Runs the subscriber.
         */
        public function notify(Started $event): void
        {
            $printer = new Reportable_Printer(new Default_Printer(true));
            if (isset($_SERVER['COLLISION_PRINTER_COMPACT'])) {
                Default_Printer::compact(true);
            }
            if (isset($_SERVER['COLLISION_PRINTER_PROFILE'])) {
                Default_Printer::profile(true);
            }
            $subscribers = [
                // Configured
                new class($printer) extends Subscriber implements Configured_Subscriber
                {
                    public function notify(Configured $event): void
                    {
                        $this->printer()->set_decorated($event->configuration()->colors());
                    }
                },
                // Test
                new class($printer) extends Subscriber implements Printed_Unexpected_Output_Subscriber
                {
                    public function notify(Printed_Unexpected_Output $event): void
                    {
                        $this->printer()->test_printed_unexpected_output($event);
                    }
                },
                // Test Runner
                new class($printer) extends Subscriber implements Execution_Started_Subscriber
                {
                    public function notify(Execution_Started $event): void
                    {
                        $this->printer()->test_runner_execution_started($event);
                    }
                },
                new class($printer) extends Subscriber implements Execution_Finished_Subscriber
                {
                    public function notify(Execution_Finished $event): void
                    {
                        $this->printer()->test_runner_execution_finished($event);
                    }
                },
                // Test > Hook Methods
                new class($printer) extends Subscriber implements Before_First_Test_Method_Errored_Subscriber
                {
                    public function notify(Before_First_Test_Method_Errored $event): void
                    {
                        $this->printer()->test_before_first_test_method_errored($event);
                    }
                },
                // Test > Lifecycle ...
                new class($printer) extends Subscriber implements Finished_Subscriber
                {
                    public function notify(Finished $event): void
                    {
                        $this->printer()->test_finished($event);
                    }
                },
                new class($printer) extends Subscriber implements Preparation_Started_Subscriber
                {
                    public function notify(Preparation_Started $event): void
                    {
                        $this->printer()->test_preparation_started($event);
                    }
                },
                // Test > Issues ...
                new class($printer) extends Subscriber implements Considered_Risky_Subscriber
                {
                    public function notify(Considered_Risky $event): void
                    {
                        $this->printer()->test_considered_risky($event);
                    }
                },
                new class($printer) extends Subscriber implements Deprecation_Triggered_Subscriber
                {
                    public function notify(Deprecation_Triggered $event): void
                    {
                        $this->printer()->test_deprecation_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Test_Runner_Deprecation_Triggered_Subscriber
                {
                    public function notify(Test_Runner_Deprecation_Triggered $event): void
                    {
                        $this->printer()->test_runner_deprecation_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Test_Runner_Warning_Triggered_Subscriber
                {
                    public function notify(Test_Runner_Warning_Triggered $event): void
                    {
                        $this->printer()->test_runner_warning_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Php_Deprecation_Triggered_Subscriber
                {
                    public function notify(Php_Deprecation_Triggered $event): void
                    {
                        $this->printer()->test_php_deprecation_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Phpunit_Deprecation_Triggered_Subscriber
                {
                    public function notify(Phpunit_Deprecation_Triggered $event): void
                    {
                        $this->printer()->test_phpunit_deprecation_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Php_Notice_Triggered_Subscriber
                {
                    public function notify(Php_Notice_Triggered $event): void
                    {
                        $this->printer()->test_php_notice_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Php_Warning_Triggered_Subscriber
                {
                    public function notify(Php_Warning_Triggered $event): void
                    {
                        $this->printer()->test_php_warning_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Phpunit_Warning_Triggered_Subscriber
                {
                    public function notify(Phpunit_Warning_Triggered $event): void
                    {
                        $this->printer()->test_phpunit_warning_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Phpunit_Error_Triggered_Subscriber
                {
                    public function notify(Phpunit_Error_Triggered $event): void
                    {
                        $this->printer()->test_phpunit_error_triggered($event);
                    }
                },
                // Test > Outcome ...
                new class($printer) extends Subscriber implements Errored_Subscriber
                {
                    public function notify(Errored $event): void
                    {
                        $this->printer()->test_errored($event);
                    }
                },
                new class($printer) extends Subscriber implements Failed_Subscriber
                {
                    public function notify(Failed $event): void
                    {
                        $this->printer()->test_failed($event);
                    }
                },
                new class($printer) extends Subscriber implements Marked_Incomplete_Subscriber
                {
                    public function notify(Marked_Incomplete $event): void
                    {
                        $this->printer()->test_marked_incomplete($event);
                    }
                },
                new class($printer) extends Subscriber implements Notice_Triggered_Subscriber
                {
                    public function notify(Notice_Triggered $event): void
                    {
                        $this->printer()->test_notice_triggered($event);
                    }
                },
                new class($printer) extends Subscriber implements Passed_Subscriber
                {
                    public function notify(Passed $event): void
                    {
                        $this->printer()->test_passed($event);
                    }
                },
                new class($printer) extends Subscriber implements Skipped_Subscriber
                {
                    public function notify(Skipped $event): void
                    {
                        $this->printer()->test_skipped($event);
                    }
                },
                new class($printer) extends Subscriber implements Warning_Triggered_Subscriber
                {
                    public function notify(Warning_Triggered $event): void
                    {
                        $this->printer()->test_warning_triggered($event);
                    }
                },
            ];
            Facade::instance()->register_subscribers(...$subscribers);
        }
        /**
         * Registers the subscriber on PHPUnit's facade.
         */
        public static function register(): void
        {
            $should_register = self::$registered === false && isset($_SERVER['COLLISION_PRINTER']);
            if ($should_register) {
                self::$registered = true;
                Facade::instance()->register_subscriber(new self());
            }
        }
    }
}