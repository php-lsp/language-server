<?php
declare(strict_types=1);

namespace App\Module\Completion;

enum KeywordCategory: string
{
    case CONTROL_FLOW = 'control_flow';
    case DECLARATION = 'declaration';
    case MODIFIER = 'modifier';
    case TYPE = 'type';
    case LANGUAGE_CONSTRUCT = 'language_construct';
    case OPERATOR = 'operator';
    case MAGIC_CONSTANT = 'magic_constant';
}
