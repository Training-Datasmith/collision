<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Laravel\Commands;

use Dotenv\Exception\Invalid_Path_Exception;
use Dotenv\Parser\Parser;
use Dotenv\Store\Store_Builder;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Str;
use Nuno_Maduro\Collision\Adapters\Laravel\Exceptions\Requirements_Exception;
use Nuno_Maduro\Collision\Coverage;
use Para_Test\Options;
use RuntimeException;
use Sebastian_Bergmann\Environment\Console;
use Symfony\Component\Console\Input\Argv_Input;
use Symfony\Component\Console\Input\Input_Definition;
use Symfony\Component\Process\Exception\Process_Signaled_Exception;
use Symfony\Component\Process\Process;
/**
 * @internal
 *
 * @final
 */
class Test_Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test
        {--without-tty : Disable output to TTY}
        {--compact : Indicates whether the compact printer should be used}
        {--coverage : Indicates whether code coverage information should be collected}
        {--min= : Indicates the minimum threshold enforcement for code coverage}
        {--p|parallel : Indicates if the tests should run in parallel}
        {--profile : Lists top 10 slowest tests}
        {--recreate-databases : Indicates if the test databases should be re-created}
        {--drop-databases : Indicates if the test databases should be dropped}
        {--without-databases : Indicates if database configuration should be performed}
    ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the application tests';
    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->ignore_validation_errors();
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('coverage') && !Coverage::is_available()) {
            $this->output->writeln(sprintf("\n  <fg=white;bg=red;options=bold> ERROR </> Code coverage driver not available.%s</>", Coverage::using_xdebug() ? " Did you set <href=https://xdebug.org/docs/code_coverage#mode>Xdebug's coverage mode</>?" : ' Did you install <href=https://xdebug.org/>Xdebug</> or <href=https://github.com/krakjoe/pcov>PCOV</>?'));
            $this->new_line();
            return 1;
        }
        /** @var bool $usesParallel */
        $uses_parallel = $this->option('parallel');
        if ($uses_parallel && !$this->is_parallel_dependencies_installed()) {
            throw new Requirements_Exception('Running Collision 8.x artisan test command in parallel requires at least ParaTest (brianium/paratest) 7.x.');
        }
        $options = array_slice($_SERVER['argv'], $this->option('without-tty') ? 3 : 2);
        $this->clear_env();
        $parallel = $this->option('parallel');
        $process = (new Process(
            array_merge(
                // Binary ...
                $this->binary(),
                // Arguments ...
                $parallel ? $this->paratest_arguments($options) : $this->phpunit_arguments($options)
            ),
            null,
            // Envs ...
            $parallel ? $this->paratest_environment_variables() : $this->phpunit_environment_variables()
        ))->set_timeout(null);
        try {
            $process->set_tty(!$this->option('without-tty'));
        } catch (RuntimeException $e) {
            // $this->output->writeln('Warning: '.$e->getMessage());
        }
        $exit_code = 1;
        try {
            $exit_code = $process->run(function ($type, $line): void {
                $this->output->write($line);
            });
        } catch (Process_Signaled_Exception $e) {
            if (extension_loaded('pcntl') && $e->get_signal() !== SIGINT) {
                throw $e;
            }
        }
        if ($exit_code === 0 && $this->option('coverage')) {
            if (!$this->using_pest() && $this->option('parallel')) {
                $this->new_line();
            }
            $hide_full_coverage = (bool) $this->option('compact');
            $coverage = Coverage::report($this->output, $hide_full_coverage);
            $exit_code = (int) ($coverage < $this->option('min'));
            if ($exit_code === 1) {
                $this->output->writeln(sprintf("\n  <fg=white;bg=red;options=bold> FAIL </> Code coverage below expected:<fg=red;options=bold> %s %%</>. Minimum:<fg=white;options=bold> %s %%</>.", number_format($coverage, 1), number_format((float) $this->option('min'), 1)));
            }
        }
        return $exit_code;
    }
    /**
     * Get the PHP binary to execute.
     *
     * @return array
     */
    protected function binary()
    {
        if ($this->using_pest()) {
            $command = $this->option('parallel') ? ['vendor/pestphp/pest/bin/pest', '--parallel'] : ['vendor/pestphp/pest/bin/pest'];
        } else {
            $command = $this->option('parallel') ? ['vendor/brianium/paratest/bin/paratest'] : ['vendor/phpunit/phpunit/phpunit'];
        }
        if ('phpdbg' === PHP_SAPI) {
            return array_merge([PHP_BINARY, '-qrr'], $command);
        }
        return array_merge([PHP_BINARY], $command);
    }
    /**
     * Gets the common arguments of PHPUnit and Pest.
     *
     * @return array
     */
    protected function common_arguments()
    {
        $arguments = [];
        if ($this->option('coverage')) {
            $arguments[] = '--coverage-php';
            $arguments[] = Coverage::get_path();
        }
        if ($this->option('ansi')) {
            $arguments[] = '--colors=always';
        } elseif ($this->option('no-ansi')) {
            $arguments[] = '--colors=never';
        } elseif ((new Console())->has_color_support()) {
            $arguments[] = '--colors=always';
        }
        return $arguments;
    }
    /**
     * Determines if Pest is being used.
     *
     * @return bool
     */
    protected function using_pest()
    {
        return function_exists('\Pest\version');
    }
    /**
     * Get the array of arguments for running PHPUnit.
     *
     * @param  array  $options
     * @return array
     */
    protected function phpunit_arguments($options)
    {
        $options = array_merge(['--no-output'], $options);
        $options = array_values(array_filter($options, fn($option) => !Str::starts_with($option, '--env=') && $option != '-q' && $option != '--quiet' && $option != '--coverage' && $option != '--compact' && $option != '--profile' && $option != '--ansi' && $option != '--no-ansi' && !Str::starts_with($option, '--min')));
        return array_merge($this->common_arguments(), ['--configuration=' . $this->get_configuration_file()], $options);
    }
    /**
     * Get the configuration file.
     *
     * @return string
     */
    protected function get_configuration_file()
    {
        if (!file_exists($file = base_path('phpunit.xml'))) {
            return base_path('phpunit.xml.dist');
        }
        return $file;
    }
    /**
     * Get the array of arguments for running Paratest.
     *
     * @param  array  $options
     * @return array
     */
    protected function paratest_arguments($options)
    {
        $options = array_values(array_filter($options, fn($option) => !Str::starts_with($option, '--env=') && $option != '--coverage' && $option != '-q' && $option != '--quiet' && $option != '--ansi' && $option != '--no-ansi' && !Str::starts_with($option, '--min') && !Str::starts_with($option, '-p') && !Str::starts_with($option, '--compact') && !Str::starts_with($option, '--parallel') && !Str::starts_with($option, '--recreate-databases') && !Str::starts_with($option, '--drop-databases') && !Str::starts_with($option, '--without-databases')));
        $options = array_merge($this->common_arguments(), ['--configuration=' . $this->get_configuration_file(), "--runner=\\Illuminate\\Testing\\ParallelRunner"], $options);
        $input_definition = new Input_Definition();
        Options::set_input_definition($input_definition);
        $input = new Argv_Input($options, $input_definition);
        /** @var non-empty-string $basePath */
        $base_path = base_path();
        $para_test_options = Options::from_console_input($input, $base_path);
        if (!$para_test_options->configuration->has_coverage_cache_directory()) {
            $cache_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '__laravel_test_cache_directory';
            $options[] = '--cache-directory';
            $options[] = $cache_directory;
        }
        return $options;
    }
    /**
     * Get the array of environment variables for running PHPUnit.
     *
     * @return array
     */
    protected function phpunit_environment_variables()
    {
        $variables = ['COLLISION_PRINTER' => 'DefaultPrinter'];
        if ($this->option('compact')) {
            $variables['COLLISION_PRINTER_COMPACT'] = 'true';
        }
        if ($this->option('profile')) {
            $variables['COLLISION_PRINTER_PROFILE'] = 'true';
        }
        return $variables;
    }
    /**
     * Get the array of environment variables for running Paratest.
     *
     * @return array
     */
    protected function paratest_environment_variables()
    {
        return ['LARAVEL_PARALLEL_TESTING' => 1, 'LARAVEL_PARALLEL_TESTING_RECREATE_DATABASES' => $this->option('recreate-databases'), 'LARAVEL_PARALLEL_TESTING_DROP_DATABASES' => $this->option('drop-databases'), 'LARAVEL_PARALLEL_TESTING_WITHOUT_DATABASES' => $this->option('without-databases')];
    }
    /**
     * Clears any set Environment variables set by Laravel if the --env option is empty.
     *
     * @return void
     */
    protected function clear_env()
    {
        if (!$this->option('env')) {
            $vars = self::get_environment_variables($this->laravel->environment_path(), $this->laravel->environment_file());
            $repository = Env::get_repository();
            foreach ($vars as $name) {
                $repository->clear($name);
            }
        }
    }
    /**
     * @param  string  $path
     * @param  string  $file
     * @return array
     */
    protected static function get_environment_variables($path, $file)
    {
        try {
            $content = Store_Builder::create_with_no_names()->add_path($path)->add_name($file)->make()->read();
        } catch (Invalid_Path_Exception) {
            return [];
        }
        $vars = [];
        foreach ((new Parser())->parse($content) as $entry) {
            $vars[] = $entry->get_name();
        }
        return $vars;
    }
    /**
     * Check if the parallel dependencies are installed.
     *
     * @return bool
     */
    protected function is_parallel_dependencies_installed()
    {
        return class_exists(\Para_Test\Para_Test_Command::class);
    }
}