<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

use Closure;
use Nuno_Maduro\Collision\Contracts\Renderable_On_Collision_Editor;
use Nuno_Maduro\Collision\Contracts\Renderless_Editor;
use Nuno_Maduro\Collision\Contracts\Renderless_Trace;
use Nuno_Maduro\Collision\Contracts\Solutions_Repository;
use Nuno_Maduro\Collision\Exceptions\Test_Exception;
use Nuno_Maduro\Collision\Solutions_Repositories\Null_Solutions_Repository;
use Symfony\Component\Console\Output\Console_Output;
use Symfony\Component\Console\Output\Output_Interface;
use Throwable;
use Whoops\Exception\Frame;
use Whoops\Exception\Inspector;
/**
 * @internal
 *
 * @see \Tests\Unit\WriterTest
 */
final class Writer
{
    /**
     * The number of frames if no verbosity is specified.
     */
    public const VERBOSITY_NORMAL_FRAMES = 1;
    /**
     * Holds an instance of the solutions repository.
     */
    private readonly Solutions_Repository $solutions_repository;
    /**
     * Holds an instance of the Output.
     */
    private Output_Interface $output;
    /**
     * Holds an instance of the Argument Formatter.
     */
    private readonly Argument_Formatter $argument_formatter;
    /**
     * Holds an instance of the Highlighter.
     */
    private readonly Highlighter $highlighter;
    /**
     * Ignores traces where the file string matches one
     * of the provided regex expressions.
     *
     * @var array<int, string|Closure>
     */
    private array $ignore = [];
    /**
     * Declares whether or not the trace should appear.
     */
    private bool $show_trace = true;
    /**
     * Declares whether or not the title should appear.
     */
    private bool $show_title = true;
    /**
     * Declares whether the editor should appear.
     */
    private bool $show_editor = true;
    /**
     * Creates an instance of the writer.
     */
    public function __construct(?Solutions_Repository $solutions_repository = null, ?Output_Interface $output = null, ?Argument_Formatter $argument_formatter = null, ?Highlighter $highlighter = null)
    {
        $this->solutions_repository = $solutions_repository ?: new Null_Solutions_Repository();
        $this->output = $output ?: new Console_Output();
        $this->argument_formatter = $argument_formatter ?: new Argument_Formatter();
        $this->highlighter = $highlighter ?: new Highlighter();
    }
    public function write(Inspector $inspector): void
    {
        $this->render_title_and_description($inspector);
        $frames = $this->get_frames($inspector);
        $exception = $inspector->get_exception();
        if ($exception instanceof Renderable_On_Collision_Editor) {
            $editor_frame = $exception->to_collision_editor();
        } else {
            $editor_frame = array_shift($frames);
        }
        if ($this->show_editor && $editor_frame !== null && !$exception instanceof Renderless_Editor) {
            $this->render_editor($editor_frame);
        }
        $this->render_solution($inspector);
        if ($this->show_trace && !empty($frames) && !$exception instanceof Renderless_Trace) {
            $this->render_trace($frames);
        } elseif (!$exception instanceof Renderless_Editor) {
            $this->output->writeln('');
        }
    }
    public function ignore_files_in(array $ignore): self
    {
        $this->ignore = $ignore;
        return $this;
    }
    public function show_trace(bool $show): self
    {
        $this->show_trace = $show;
        return $this;
    }
    public function show_title(bool $show): self
    {
        $this->show_title = $show;
        return $this;
    }
    public function show_editor(bool $show): self
    {
        $this->show_editor = $show;
        return $this;
    }
    public function set_output(Output_Interface $output): self
    {
        $this->output = $output;
        return $this;
    }
    public function get_output(): Output_Interface
    {
        return $this->output;
    }
    /**
     * Returns pertinent frames.
     *
     * @return array<int, Frame>
     */
    private function get_frames(Inspector $inspector): array
    {
        return $inspector->get_frames()->filter(function ($frame): bool {
            // If we are in verbose mode, we always
            // display the full stack trace.
            if ($this->output->get_verbosity() >= Output_Interface::VERBOSITY_VERBOSE) {
                return true;
            }
            foreach ($this->ignore as $ignore) {
                if (is_string($ignore)) {
                    // Ensure paths are linux-style (like the ones on $this->ignore)
                    $sanitized_path = (string) str_replace('\\', '/', $frame->get_file());
                    if (preg_match($ignore, $sanitized_path)) {
                        return false;
                    }
                }
                if ($ignore instanceof Closure) {
                    if ($ignore($frame)) {
                        return false;
                    }
                }
            }
            return true;
        })->get_array();
    }
    /**
     * Renders the title of the exception.
     */
    private function render_title_and_description(Inspector $inspector): self
    {
        /** @var Throwable|TestException $exception */
        $exception = $inspector->get_exception();
        $message = rtrim($exception->get_message());
        $class = $exception instanceof Test_Exception ? $exception->get_class_name() : $inspector->get_exception_name();
        if ($this->show_title) {
            $this->render("<bg=red;options=bold> {$class} </>");
            $this->output->writeln('');
        }
        $this->output->writeln("<fg=default;options=bold>  {$message}</>");
        return $this;
    }
    /**
     * Renders the solution of the exception, if any.
     */
    private function render_solution(Inspector $inspector): self
    {
        $throwable = $inspector->get_exception();
        $solutions = $throwable instanceof Throwable ? $this->solutions_repository->get_from_throwable($throwable) : [];
        foreach ($solutions as $solution) {
            /** @var \Spatie\Ignition\Contracts\Solution $solution */
            $title = $solution->get_solution_title();
            // @phpstan-ignore-line
            $description = $solution->get_solution_description();
            // @phpstan-ignore-line
            $links = $solution->get_documentation_links();
            // @phpstan-ignore-line
            $description = trim((string) preg_replace("/\n/", "\n    ", (string) $description));
            $this->render(sprintf('<fg=cyan;options=bold>i</>   <fg=default;options=bold>%s</>: %s %s', rtrim((string) $title, '.'), $description, implode(', ', array_map(fn(string $link) => sprintf("\n      <fg=gray>%s</>", $link), $links))));
        }
        return $this;
    }
    /**
     * Renders the editor containing the code that was the
     * origin of the exception.
     */
    private function render_editor(Frame $frame): self
    {
        if ($frame->get_file() !== 'Unknown') {
            $file = $this->get_file_relative_path((string) $frame->get_file());
            // getLine() might return null so cast to int to get 0 instead
            $line = (int) $frame->get_line();
            $this->render('at <fg=green>' . $file . '</>' . ':<fg=green>' . $line . '</>');
            $content = $this->highlighter->highlight((string) $frame->get_file_contents(), (int) $frame->get_line());
            $this->output->writeln($content);
        }
        return $this;
    }
    /**
     * Renders the trace of the exception.
     */
    private function render_trace(array $frames): self
    {
        $vendor_frames = 0;
        $user_frames = 0;
        if (!empty($frames)) {
            $this->output->writeln(['']);
        }
        foreach ($frames as $i => $frame) {
            if ($this->output->get_verbosity() < Output_Interface::VERBOSITY_VERBOSE && str_contains((string) $frame->get_file(), '/vendor/')) {
                $vendor_frames++;
                continue;
            }
            if ($user_frames > self::VERBOSITY_NORMAL_FRAMES && $this->output->get_verbosity() < Output_Interface::VERBOSITY_VERBOSE) {
                break;
            }
            $user_frames++;
            $file = $this->get_file_relative_path($frame->get_file());
            $line = $frame->get_line();
            $class = empty($frame->get_class()) ? '' : $frame->get_class() . '::';
            $function = $frame->get_function();
            $args = $this->argument_formatter->format($frame->get_args());
            $pos = str_pad((string) ((int) $i + 1), 4, ' ');
            if ($vendor_frames > 0) {
                $this->output->writeln(sprintf("      \x1b[2m+%s vendor frames \x1b[22m", $vendor_frames));
                $vendor_frames = 0;
            }
            $this->render("<fg=yellow>{$pos}</><fg=default;options=bold>{$file}</>:<fg=default;options=bold>{$line}</>", (bool) $class && $i > 0);
            if ($class) {
                $this->render("<fg=gray>    {$class}{$function}({$args})</>", false);
            }
        }
        if (!empty($frames)) {
            $this->output->writeln(['']);
        }
        return $this;
    }
    /**
     * Renders a message into the console.
     */
    private function render(string $message, bool $break = true): self
    {
        if ($break) {
            $this->output->writeln('');
        }
        $this->output->writeln("  {$message}");
        return $this;
    }
    /**
     * Returns the relative path of the given file path.
     */
    private function get_file_relative_path(string $file_path): string
    {
        $cwd = (string) getcwd();
        if (!empty($cwd)) {
            return str_replace("{$cwd}" . DIRECTORY_SEPARATOR, '', $file_path);
        }
        return $file_path;
    }
}