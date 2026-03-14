<?php

declare(strict_types=1);

namespace App\Module\Completion;

use Lsp\Protocol\Type\CompletionItemKind;

final class KeywordDefinitions
{
    public const CONTROL_FLOW = [
        'if',
        'else',
        'elseif',
        'endif',
        'switch',
        'endswitch',
        'case',
        'default',
        'while',
        'endwhile',
        'do',
        'for',
        'endfor',
        'foreach',
        'endforeach',
        'break',
        'continue',
        'return',
        'goto',
        'declare',
        'enddeclare',
        'try',
        'catch',
        'finally',
        'throw',
        'yield',
        'yield from',
        'match', // PHP 8.0+
    ];

    public const DECLARATION = [
        'class',
        'interface',
        'trait',
        'enum', // PHP 8.1+
        'function',
        'fn', // PHP 7.4+
        'namespace',
        'use',
        'const',
    ];

    public const MODIFIER = [
        'public',
        'protected',
        'private',
        'static',
        'final',
        'abstract',
        'readonly', // PHP 8.1+
        'var',
    ];

    public const TYPE = [
        'extends',
        'implements',
        'instanceof',
        'insteadof',
        'new',
        'clone',
        'array',
        'callable',
        'as',
        'global',
    ];

    public const LANGUAGE_CONSTRUCT = [
        'die',
        'echo',
        'empty',
        'eval',
        'exit',
        'isset',
        'list',
        'print',
        'unset',
        '__halt_compiler',
    ];

    public const OPERATOR = [
        'and',
        'or',
        'xor',
        'include',
        'include_once',
        'require',
        'require_once',
    ];

    public const MAGIC_CONSTANT = [
        '__CLASS__',
        '__DIR__',
        '__FILE__',
        '__FUNCTION__',
        '__LINE__',
        '__METHOD__',
        '__NAMESPACE__',
        '__TRAIT__',
        '__PROPERTY__', // PHP 8.3+
    ];

    public const array ALL = [
        [KeywordCategory::CONTROL_FLOW, self::CONTROL_FLOW],
        [KeywordCategory::DECLARATION, self::DECLARATION],
        [KeywordCategory::MODIFIER, self::MODIFIER],
        [KeywordCategory::TYPE, self::TYPE],
        [KeywordCategory::LANGUAGE_CONSTRUCT, self::LANGUAGE_CONSTRUCT],
        [KeywordCategory::OPERATOR, self::OPERATOR],
        [KeywordCategory::MAGIC_CONSTANT, self::MAGIC_CONSTANT],
    ];

    public static function getCompletionKind(KeywordCategory $category): CompletionItemKind
    {
        return match ($category) {
            KeywordCategory::CONTROL_FLOW => CompletionItemKind::KeywordKind,
            KeywordCategory::DECLARATION => CompletionItemKind::KeywordKind,
            KeywordCategory::MODIFIER => CompletionItemKind::KeywordKind,
            KeywordCategory::TYPE => CompletionItemKind::KeywordKind,
            KeywordCategory::LANGUAGE_CONSTRUCT => CompletionItemKind::FunctionKind,
            KeywordCategory::OPERATOR => CompletionItemKind::OperatorKind,
            KeywordCategory::MAGIC_CONSTANT => CompletionItemKind::ConstantKind,
        };
    }

    public static function getCompletionSuffix(KeywordCategory $category): string
    {
        return match ($category) {
            KeywordCategory::LANGUAGE_CONSTRUCT => '()',
            default => ' ',
        };
    }
}
