<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Exceptions;

use Php_Unit\Event\Code\Throwable;
use Php_Unit\Framework\Expectation_Failed_Exception;
use ReflectionClass;
/**
 * @internal
 */
final readonly class Test_Exception implements \Stringable
{
    private const DIFF_SEPARATOR = '--- Expected' . PHP_EOL . '+++ Actual' . PHP_EOL . '@@ @@' . PHP_EOL;
    /**
     * Creates a new Exception instance.
     */
    public function __construct(private Throwable $throwable, private bool $is_verbose)
    {
    }
    public function get_throwable(): Throwable
    {
        return $this->throwable;
    }
    /**
     * @return class-string
     */
    public function get_class_name(): string
    {
        return $this->throwable->class_name();
    }
    public function get_message(): string
    {
        if ($this->throwable->class_name() === Expectation_Failed_Exception::class) {
            $message = $this->throwable->description();
        } else {
            $message = $this->throwable->message();
        }
        $regexes = ['To contain' => '/Failed asserting that \'(.*)\' \[[\w-]+\]\(length: [\d]+\) contains "(.*)"/s', 'Not to contain' => '/Failed asserting that \'(.*)\' \[[\w-]+\]\(length: [\d]+\) does not contain "(.*)"/s'];
        foreach ($regexes as $key => $pattern) {
            preg_match($pattern, $message, $matches, PREG_OFFSET_CAPTURE, 0);
            if (count($matches) === 3) {
                $message = $this->shorten_message($matches, $key);
                break;
            }
        }
        // Diffs...
        if (str_contains($message, self::DIFF_SEPARATOR)) {
            $diff = '';
            $lines = explode(PHP_EOL, explode(self::DIFF_SEPARATOR, $message)[1]);
            foreach ($lines as $line) {
                $diff .= $this->colorize_line($line, str_starts_with($line, '-') ? 'red' : 'green') . PHP_EOL;
            }
            $message = str_replace(explode(self::DIFF_SEPARATOR, $message)[1], $diff, $message);
            $message = str_replace(self::DIFF_SEPARATOR, '', $message);
        }
        return $message;
    }
    private function shorten_message(array $matches, string $key): string
    {
        $actual = $matches[1][0];
        $expected = $matches[2][0];
        $actual_exploded = explode(PHP_EOL, (string) $actual);
        $expected_exploded = explode(PHP_EOL, (string) $expected);
        if (($count_actual = count($actual_exploded)) > 4 && !$this->is_verbose) {
            $actual_exploded = array_slice($actual_exploded, 0, 3);
        }
        if (($count_expected = count($expected_exploded)) > 4 && !$this->is_verbose) {
            $expected_exploded = array_slice($expected_exploded, 0, 3);
        }
        $actual_as_string = '';
        $expected_as_string = '';
        foreach ($actual_exploded as $line) {
            $actual_as_string .= PHP_EOL . $this->colorize_line($line, 'red');
        }
        foreach ($expected_exploded as $line) {
            $expected_as_string .= PHP_EOL . $this->colorize_line($line, 'green');
        }
        if ($count_actual > 4 && !$this->is_verbose) {
            $actual_as_string .= PHP_EOL . $this->colorize_line(sprintf('... (%s more lines)', $count_actual - 3), 'gray');
        }
        if ($count_expected > 4 && !$this->is_verbose) {
            $expected_as_string .= PHP_EOL . $this->colorize_line(sprintf('... (%s more lines)', $count_expected - 3), 'gray');
        }
        return implode(PHP_EOL, ['Expected: ' . ltrim($actual_as_string, PHP_EOL . '  '), '', '  ' . $key . ': ' . ltrim($expected_as_string, PHP_EOL . '  '), '']);
    }
    public function get_code(): int
    {
        return 0;
    }
    /**
     * @throws \ReflectionException
     */
    public function get_file(): string
    {
        if (!isset($this->get_trace()[0])) {
            return (string) (new ReflectionClass($this->get_class_name()))->get_file_name();
        }
        return $this->get_trace()[0]['file'];
    }
    public function get_line(): int
    {
        if (!isset($this->get_trace()[0])) {
            return 0;
        }
        return (int) $this->get_trace()[0]['line'];
    }
    public function get_trace(): array
    {
        $frames = explode("\n", $this->get_trace_as_string());
        $frames = array_filter($frames, fn($trace): bool => $trace !== '');
        return array_map(function ($trace): ?array {
            if (trim($trace) === '') {
                return null;
            }
            $parts = explode(':', $trace);
            $line = array_pop($parts);
            $file = implode(':', $parts);
            return ['file' => $file, 'line' => $line];
        }, $frames);
    }
    public function get_trace_as_string(): string
    {
        return $this->throwable->stack_trace();
    }
    public function get_previous(): ?self
    {
        if ($this->throwable->has_previous()) {
            return new self($this->throwable->previous(), $this->is_verbose);
        }
        return null;
    }
    public function __toString(): string
    {
        return $this->get_message();
    }
    private function colorize_line(string $line, string $color): string
    {
        return sprintf('  <fg=%s>%s</>', $color, $line);
    }
}