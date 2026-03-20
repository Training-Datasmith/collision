<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

use Sebastian_Bergmann\Code_Coverage\Code_Coverage;
use Sebastian_Bergmann\Code_Coverage\Node\Directory;
use Sebastian_Bergmann\Code_Coverage\Node\File;
use Sebastian_Bergmann\Environment\Runtime;
use Symfony\Component\Console\Output\Output_Interface;
use function Termwind\render;
use function Termwind\Render_Using;
use function Termwind\terminal;
/**
 * @internal
 */
final class Coverage
{
    /**
     * Returns the coverage path.
     */
    public static function get_path(): string
    {
        return implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), '.temp', 'coverage']);
    }
    /**
     * Runs true there is any code coverage driver available.
     */
    public static function is_available(): bool
    {
        $runtime = new Runtime();
        if (!$runtime->can_collect_code_coverage()) {
            return false;
        }
        if ($runtime->has_pcov() || $runtime->has_phpdbg_code_coverage()) {
            return true;
        }
        if (self::using_xdebug()) {
            $mode = getenv('XDEBUG_MODE') ?: ini_get('xdebug.mode');
            return $mode && in_array('coverage', explode(',', $mode), true);
        }
        return true;
    }
    /**
     * If the user is using Xdebug.
     */
    public static function using_xdebug(): bool
    {
        return (new Runtime())->has_xdebug();
    }
    /**
     * Reports the code coverage report to the
     * console and returns the result in float.
     */
    public static function report(Output_Interface $output, bool $hide_full_coverage = false): float
    {
        if (!file_exists($report_path = self::get_path())) {
            if (self::using_xdebug()) {
                $output->writeln("  <fg=black;bg=yellow;options=bold> WARN </> Unable to get coverage using Xdebug. Did you set <href=https://xdebug.org/docs/code_coverage#mode>Xdebug's coverage mode</>?</>");
                return 0.0;
            }
            $output->writeln('  <fg=black;bg=yellow;options=bold> WARN </> No coverage driver detected.</> Did you install <href=https://xdebug.org/>Xdebug</> or <href=https://github.com/krakjoe/pcov>PCOV</>?');
            return 0.0;
        }
        /** @var CodeCoverage $codeCoverage */
        $code_coverage = require $report_path;
        unlink($report_path);
        $total_coverage = $code_coverage->get_report()->percentage_of_executed_lines();
        /** @var Directory<File|Directory> $report */
        $report = $code_coverage->get_report();
        foreach ($report->getIterator() as $file) {
            if (!$file instanceof File) {
                continue;
            }
            $dirname = dirname($file->id());
            $basename = basename($file->id(), '.php');
            $name = $dirname === '.' ? $basename : implode(DIRECTORY_SEPARATOR, [$dirname, $basename]);
            $percentage = $file->number_of_executable_lines() === 0 ? '100.0' : number_format($file->percentage_of_executed_lines()->as_float(), 1, '.', '');
            if ($percentage === '100.0' && $hide_full_coverage) {
                continue;
            }
            $uncovered_lines = '';
            $percentage_of_executed_lines_as_string = $file->percentage_of_executed_lines()->as_string();
            if (!in_array($percentage_of_executed_lines_as_string, ['0.00%', '100.00%', '100.0%', ''], true)) {
                $uncovered_lines = trim(implode(', ', self::get_missing_coverage($file)));
                $uncovered_lines = sprintf('<span>%s</span>', $uncovered_lines) . ' <span class="text-gray"> / </span>';
            }
            $color = $percentage === '100.0' ? 'green' : ($percentage === '0.0' ? 'red' : 'yellow');
            $truncate_at = max(1, terminal()->width() - 12);
            render_using($output);
            render(<<<HTML
                <div class="flex mx-2">
                    <span class="truncate-{$truncate_at}">{$name}</span>
                    <span class="flex-1 content-repeat-[.] text-gray mx-1"></span>
                    <span class="text-{$color}">{$uncovered_lines} {$percentage}%</span>
                </div>
            HTML);
        }
        $total_coverage_as_string = $total_coverage->as_float() === 0.0 ? '0.0' : number_format($total_coverage->as_float(), 1, '.', '');
        render_using($output);
        render(<<<HTML
            <div class="mx-2">
                <hr class="text-gray" />
                <div class="w-full text-right">
                    <span class="ml-1 font-bold">Total: {$total_coverage_as_string} %</span>
                </div>
            </div>
        HTML);
        return $total_coverage->as_float();
    }
    /**
     * Generates an array of missing coverage on the following format:.
     *
     * ```
     * ['11', '20..25', '50', '60..80'];
     * ```
     *
     * @param  File  $file
     * @return array<int, string>
     */
    public static function get_missing_coverage($file): array
    {
        $should_be_new_line = true;
        $each_line = function (array $array, array $tests, int $line) use (&$should_be_new_line): array {
            if ($tests !== []) {
                $should_be_new_line = true;
                return $array;
            }
            if ($should_be_new_line) {
                $array[] = (string) $line;
                $should_be_new_line = false;
                return $array;
            }
            $last_key = count($array) - 1;
            if (array_key_exists($last_key, $array) && str_contains((string) $array[$last_key], '..')) {
                [$from] = explode('..', (string) $array[$last_key]);
                $array[$last_key] = $line > $from ? sprintf('%s..%s', $from, $line) : sprintf('%s..%s', $line, $from);
                return $array;
            }
            $array[$last_key] = sprintf('%s..%s', $array[$last_key], $line);
            return $array;
        };
        $array = [];
        foreach (array_filter($file->line_coverage_data(), is_array(...)) as $line => $tests) {
            $array = $each_line($array, $tests, $line);
        }
        return $array;
    }
}