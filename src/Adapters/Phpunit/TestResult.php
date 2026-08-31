<?php

declare(strict_types=1);

namespace NunoMaduro\Collision\Adapters\Phpunit;

use NunoMaduro\Collision\Contracts\Adapters\Phpunit\HasPrintableTestCaseName;
use NunoMaduro\Collision\Exceptions\ShouldNotHappen;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;

/**
 * @internal
 */
final class TestResult
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

    public string $warningSource = '';

    /**
     * Creates a new TestResult instance.
     */
    private function __construct(public string $id, public string $testCaseName, public string $description, public string $type, public string $icon, public string $compactIcon, public string $color, public string $compactColor, public array $context, public ?Throwable $throwable = null)
    {
        $this->duration = 0.0;

        $asWarning = $this->type === TestResult::WARN
            || $this->type === TestResult::RISKY
            || $this->type === TestResult::SKIPPED
            || $this->type === TestResult::DEPRECATED
            || $this->type === TestResult::NOTICE
            || $this->type === TestResult::INCOMPLETE;

        if ($this->throwable instanceof Throwable && $asWarning) {
            if (in_array($this->type, [TestResult::DEPRECATED, TestResult::NOTICE])) {
                foreach (explode("\n", $this->throwable->stackTrace()) as $line) {
                    if (! str_contains($line, 'vendor/nunomaduro/collision')) {
                        $this->warningSource = str_replace(getcwd().'/', '', $line);

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
    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * Creates a new test from the given test case.
     */
    public static function fromTestCase(Test $test, string $type, ?Throwable $throwable = null): self
    {
        if (! $test instanceof TestMethod) {
            throw new ShouldNotHappen;
        }

        if (is_subclass_of($test->className(), HasPrintableTestCaseName::class)) {
            $testCaseName = $test->className()::getPrintableTestCaseName();
            $context = method_exists($test->className(), 'getPrintableContext') ? $test->className()::getPrintableContext() : [];
        } else {
            $testCaseName = $test->className();
            $context = [];
        }

        $description = self::makeDescription($test);

        $icon = self::makeIcon($type);

        $compactIcon = self::makeCompactIcon($type);

        $color = self::makeColor($type);

        $compactColor = self::makeCompactColor($type);

        return new self($test->id(), $testCaseName, $description, $type, $icon, $compactIcon, $color, $compactColor, $context, $throwable);
    }

    /**
     * Creates a new test from the given Pest Parallel Test Case.
     */
    public static function fromPestParallelTestCase(Test $test, string $type, ?Throwable $throwable = null): self
    {
        if (! $test instanceof TestMethod) {
            throw new ShouldNotHappen;
        }

        if (is_subclass_of($test->className(), HasPrintableTestCaseName::class)) {
            $testCaseName = $test->className()::getPrintableTestCaseName();
            $description = $test->testDox()->prettifiedMethodName();
        } else {
            $testCaseName = $test->className();
            $description = self::makeDescription($test);
        }

        $icon = self::makeIcon($type);

        $compactIcon = self::makeCompactIcon($type);

        $color = self::makeColor($type);

        $compactColor = self::makeCompactColor($type);

        return new self($test->id(), $testCaseName, $description, $type, $icon, $compactIcon, $color, $compactColor, [], $throwable);
    }

    /**
     * Creates a new test from the given test case.
     */
    public static function fromBeforeFirstTestMethodErrored(BeforeFirstTestMethodErrored $event): self
    {
        if (is_subclass_of($event->testClassName(), HasPrintableTestCaseName::class)) {
            $testCaseName = $event->testClassName()::getPrintableTestCaseName();
        } else {
            $testCaseName = $event->testClassName();
        }

        $description = '';

        $icon = self::makeIcon(self::FAIL);

        $compactIcon = self::makeCompactIcon(self::FAIL);

        $color = self::makeColor(self::FAIL);

        $compactColor = self::makeCompactColor(self::FAIL);

        return new self($testCaseName, $testCaseName, $description, self::FAIL, $icon, $compactIcon, $color, $compactColor, [], $event->throwable());
    }

    /**
     * Get the test case description.
     */
    public static function makeDescription(TestMethod $test): string
    {
        if (is_subclass_of($test->className(), HasPrintableTestCaseName::class)) {
            return $test->className()::getLatestPrintableTestCaseMethodName();
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
    public static function makeIcon(string $type): string
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
    public static function makeCompactIcon(string $type): string
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
    public static function makeCompactColor(string $type): string
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
    public static function makeColor(string $type): string
    {
        return match ($type) {
            self::TODO => 'cyan',
            self::FAIL => 'red',
            self::DEPRECATED, self::NOTICE, self::SKIPPED, self::INCOMPLETE, self::RISKY, self::WARN, self::RUNS => 'yellow',
            default => 'green',
        };
    }
}
