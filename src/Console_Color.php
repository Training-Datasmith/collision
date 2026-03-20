<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

use InvalidArgumentException;
use Nuno_Maduro\Collision\Exceptions\Invalid_Style_Exception;
use Nuno_Maduro\Collision\Exceptions\Should_Not_Happen;
/**
 * @internal
 *
 * @final
 */
class Console_Color
{
    public const FOREGROUND = 38;
    public const BACKGROUND = 48;
    public const COLOR256_REGEXP = '~^(bg_)?color_(\d{1,3})$~';
    public const RESET_STYLE = 0;
    private bool $force_style = false;
    /** @var array */
    private const STYLES = ['none' => null, 'bold' => '1', 'dark' => '2', 'italic' => '3', 'underline' => '4', 'blink' => '5', 'reverse' => '7', 'concealed' => '8', 'default' => '39', 'black' => '30', 'red' => '31', 'green' => '32', 'yellow' => '33', 'blue' => '34', 'magenta' => '35', 'cyan' => '36', 'light_gray' => '37', 'dark_gray' => '90', 'light_red' => '91', 'light_green' => '92', 'light_yellow' => '93', 'light_blue' => '94', 'light_magenta' => '95', 'light_cyan' => '96', 'white' => '97', 'bg_default' => '49', 'bg_black' => '40', 'bg_red' => '41', 'bg_green' => '42', 'bg_yellow' => '43', 'bg_blue' => '44', 'bg_magenta' => '45', 'bg_cyan' => '46', 'bg_light_gray' => '47', 'bg_dark_gray' => '100', 'bg_light_red' => '101', 'bg_light_green' => '102', 'bg_light_yellow' => '103', 'bg_light_blue' => '104', 'bg_light_magenta' => '105', 'bg_light_cyan' => '106', 'bg_white' => '107'];
    private array $themes = [];
    /**
     * @throws InvalidStyleException
     * @throws InvalidArgumentException
     */
    public function apply(array|string $style, string $text): string
    {
        if (!$this->is_style_forced() && !$this->is_supported()) {
            return $text;
        }
        if (is_string($style)) {
            $style = [$style];
        }
        if (!is_array($style)) {
            throw new InvalidArgumentException('Style must be string or array.');
        }
        $sequences = [];
        foreach ($style as $s) {
            // @phpstan-ignore-next-line
            if (isset($this->themes[$s])) {
                $sequences = array_merge($sequences, $this->theme_sequence($s));
            } elseif ($this->is_valid_style($s)) {
                $sequences[] = $this->style_sequence($s);
            } else {
                throw new Should_Not_Happen();
            }
        }
        $sequences = array_filter($sequences, fn($val) => $val !== null);
        if (empty($sequences)) {
            return $text;
        }
        return $this->esc_sequence(implode(';', $sequences)) . $text . $this->esc_sequence(self::RESET_STYLE);
    }
    public function set_force_style(bool $force_style): void
    {
        $this->force_style = $force_style;
    }
    public function is_style_forced(): bool
    {
        return $this->force_style;
    }
    public function set_themes(array $themes): void
    {
        $this->themes = [];
        foreach ($themes as $name => $styles) {
            $this->add_theme($name, $styles);
        }
    }
    public function add_theme(string $name, array|string $styles): void
    {
        if (is_string($styles)) {
            $styles = [$styles];
        }
        if (!is_array($styles)) {
            throw new InvalidArgumentException('Style must be string or array.');
        }
        foreach ($styles as $style) {
            if (!$this->is_valid_style($style)) {
                throw new Invalid_Style_Exception($style);
            }
        }
        $this->themes[$name] = $styles;
    }
    public function get_themes(): array
    {
        return $this->themes;
    }
    public function has_theme(string $name): bool
    {
        return isset($this->themes[$name]);
    }
    public function remove_theme(string $name): void
    {
        unset($this->themes[$name]);
    }
    public function is_supported(): bool
    {
        // The COLLISION_FORCE_COLORS variable is for internal purposes only
        if (getenv('COLLISION_FORCE_COLORS') !== false) {
            return true;
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON';
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
    public function are256colors_supported(): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT);
        }
        return str_contains((string) getenv('TERM'), '256color');
    }
    public function get_possible_styles(): array
    {
        return array_keys(self::STYLES);
    }
    private function theme_sequence(string $name): array
    {
        $sequences = [];
        foreach ($this->themes[$name] as $style) {
            $sequences[] = $this->style_sequence($style);
        }
        return $sequences;
    }
    private function style_sequence(string $style): ?string
    {
        if (array_key_exists($style, self::STYLES)) {
            return self::STYLES[$style];
        }
        if (!$this->are256colors_supported()) {
            return null;
        }
        preg_match(self::COLOR256_REGEXP, $style, $matches);
        $type = $matches[1] === 'bg_' ? self::BACKGROUND : self::FOREGROUND;
        $value = $matches[2];
        return "{$type};5;{$value}";
    }
    private function is_valid_style(string $style): bool
    {
        return array_key_exists($style, self::STYLES) || preg_match(self::COLOR256_REGEXP, $style);
    }
    private function esc_sequence(string|int $value): string
    {
        return "\x1b[{$value}m";
    }
}