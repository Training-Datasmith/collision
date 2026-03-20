<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

/**
 * @internal
 */
final class Highlighter
{
    public const TOKEN_DEFAULT = 'token_default';
    public const TOKEN_COMMENT = 'token_comment';
    public const TOKEN_STRING = 'token_string';
    public const TOKEN_HTML = 'token_html';
    public const TOKEN_KEYWORD = 'token_keyword';
    public const ACTUAL_LINE_MARK = 'actual_line_mark';
    public const LINE_NUMBER = 'line_number';
    private const ARROW_SYMBOL = '>';
    private const DELIMITER = '|';
    private const ARROW_SYMBOL_UTF8 = '➜';
    private const DELIMITER_UTF8 = '▕';
    // '▶';
    private const LINE_NUMBER_DIVIDER = 'line_divider';
    private const MARKED_LINE_NUMBER = 'marked_line';
    private const WIDTH = 3;
    /**
     * Holds the theme.
     */
    private const THEME = [self::TOKEN_STRING => ['light_gray'], self::TOKEN_COMMENT => ['dark_gray', 'italic'], self::TOKEN_KEYWORD => ['magenta', 'bold'], self::TOKEN_DEFAULT => ['default', 'bold'], self::TOKEN_HTML => ['blue', 'bold'], self::ACTUAL_LINE_MARK => ['red', 'bold'], self::LINE_NUMBER => ['dark_gray'], self::MARKED_LINE_NUMBER => ['italic', 'bold'], self::LINE_NUMBER_DIVIDER => ['dark_gray']];
    private readonly Console_Color $color;
    private const DEFAULT_THEME = [self::TOKEN_STRING => 'red', self::TOKEN_COMMENT => 'yellow', self::TOKEN_KEYWORD => 'green', self::TOKEN_DEFAULT => 'default', self::TOKEN_HTML => 'cyan', self::ACTUAL_LINE_MARK => 'dark_gray', self::LINE_NUMBER => 'dark_gray', self::MARKED_LINE_NUMBER => 'dark_gray', self::LINE_NUMBER_DIVIDER => 'dark_gray'];
    private string $delimiter = self::DELIMITER_UTF8;
    private string $arrow = self::ARROW_SYMBOL_UTF8;
    private const NO_MARK = '    ';
    /**
     * Creates an instance of the Highlighter.
     */
    public function __construct(?Console_Color $color = null, bool $UTF8 = true)
    {
        $this->color = $color ?: new Console_Color();
        foreach (self::DEFAULT_THEME as $name => $styles) {
            if (!$this->color->has_theme($name)) {
                $this->color->add_theme($name, $styles);
            }
        }
        foreach (self::THEME as $name => $styles) {
            $this->color->add_theme($name, $styles);
        }
        if (!$UTF8) {
            $this->delimiter = self::DELIMITER;
            $this->arrow = self::ARROW_SYMBOL;
        }
        $this->delimiter .= ' ';
    }
    /**
     * Highlights the provided content.
     */
    public function highlight(string $content, int $line): string
    {
        return rtrim($this->get_code_snippet($content, $line, 4, 4));
    }
    /**
     * Highlights the provided content.
     */
    public function get_code_snippet(string $source, int $line_number, int $lines_before = 2, int $lines_after = 2): string
    {
        $token_lines = $this->get_highlighted_lines($source);
        $offset = $line_number - $lines_before - 1;
        $offset = max($offset, 0);
        $length = $lines_after + $lines_before + 1;
        $token_lines = array_slice($token_lines, $offset, $length, $preserve_keys = true);
        $lines = $this->color_lines($token_lines);
        return $this->line_numbers($lines, $line_number);
    }
    private function get_highlighted_lines(string $source): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $tokens = $this->tokenize($source);
        return $this->split_to_lines($tokens);
    }
    private function tokenize(string $source): array
    {
        $tokens = token_get_all($source);
        $output = [];
        $current_type = null;
        $buffer = '';
        $new_type = null;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_WHITESPACE:
                        break;
                    case T_OPEN_TAG:
                    case T_OPEN_TAG_WITH_ECHO:
                    case T_CLOSE_TAG:
                    case T_STRING:
                    case T_VARIABLE:
                    // Constants
                    case T_DIR:
                    case T_FILE:
                    case T_METHOD_C:
                    case T_DNUMBER:
                    case T_LNUMBER:
                    case T_NS_C:
                    case T_LINE:
                    case T_CLASS_C:
                    case T_FUNC_C:
                    case T_TRAIT_C:
                        $new_type = self::TOKEN_DEFAULT;
                        break;
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        $new_type = self::TOKEN_COMMENT;
                        break;
                    case T_ENCAPSED_AND_WHITESPACE:
                    case T_CONSTANT_ENCAPSED_STRING:
                        $new_type = self::TOKEN_STRING;
                        break;
                    case T_INLINE_HTML:
                        $new_type = self::TOKEN_HTML;
                        break;
                    default:
                        $new_type = self::TOKEN_KEYWORD;
                }
            } else {
                $new_type = $token === '"' ? self::TOKEN_STRING : self::TOKEN_KEYWORD;
            }
            if ($current_type === null) {
                $current_type = $new_type;
            }
            if ($current_type !== $new_type) {
                $output[] = [$current_type, $buffer];
                $buffer = '';
                $current_type = $new_type;
            }
            $buffer .= is_array($token) ? $token[1] : $token;
        }
        if (isset($new_type)) {
            $output[] = [$new_type, $buffer];
        }
        return $output;
    }
    private function split_to_lines(array $tokens): array
    {
        $lines = [];
        $line = [];
        foreach ($tokens as $token) {
            foreach (explode("\n", (string) $token[1]) as $count => $token_line) {
                if ($count > 0) {
                    $lines[] = $line;
                    $line = [];
                }
                if ($token_line === '') {
                    continue;
                }
                $line[] = [$token[0], $token_line];
            }
        }
        $lines[] = $line;
        return $lines;
    }
    private function color_lines(array $token_lines): array
    {
        $lines = [];
        foreach ($token_lines as $line_count => $token_line) {
            $line = '';
            foreach ($token_line as $token) {
                [$token_type, $token_value] = $token;
                if ($this->color->has_theme($token_type)) {
                    $line .= $this->color->apply($token_type, $token_value);
                } else {
                    $line .= $token_value;
                }
            }
            $lines[$line_count] = $line;
        }
        return $lines;
    }
    private function line_numbers(array $lines, ?int $mark_line = null): string
    {
        $line_strlen = strlen((string) ((int) array_key_last($lines) + 1));
        $line_strlen = $line_strlen < self::WIDTH ? self::WIDTH : $line_strlen;
        $snippet = '';
        $mark = '  ' . $this->arrow . ' ';
        foreach ($lines as $i => $line) {
            $colored_line_number = $this->colored_line_number(self::LINE_NUMBER, $i, $line_strlen);
            if ($mark_line !== null) {
                $snippet .= $mark_line === $i + 1 ? $this->color->apply(self::ACTUAL_LINE_MARK, $mark) : self::NO_MARK;
                $colored_line_number = $mark_line === $i + 1 ? $this->colored_line_number(self::MARKED_LINE_NUMBER, $i, $line_strlen) : $colored_line_number;
            }
            $snippet .= $colored_line_number;
            $snippet .= $this->color->apply(self::LINE_NUMBER_DIVIDER, $this->delimiter);
            $snippet .= $line . PHP_EOL;
        }
        return $snippet;
    }
    private function colored_line_number(string $style, int $i, int $length): string
    {
        return $this->color->apply($style, str_pad((string) ($i + 1), $length, ' ', STR_PAD_LEFT));
    }
}