<?php

declare(strict_types=1);

namespace App\Hydrator;

use Override;
use TypeLang\Mapper\Runtime\Parser\TypeParserInterface;
use TypeLang\Mapper\Runtime\Repository\TypeRepositoryInterface;
use TypeLang\Mapper\Type\Builder\TypeBuilderInterface;
use TypeLang\Parser\Node\Literal\StringLiteralNode;
use TypeLang\Parser\Node\Stmt\TypeStatement;

/**
 * @template-implements TypeBuilderInterface<StringLiteralNode, StringLiteralType>
 */
final class StringLiteralTypeBuilder implements TypeBuilderInterface
{
    #[Override]
    public function isSupported(TypeStatement $statement): bool
    {
        return $statement instanceof StringLiteralNode;
    }

    #[Override]
    public function build(
        TypeStatement $statement,
        TypeRepositoryInterface $types,
        TypeParserInterface $parser,
    ): StringLiteralType {
        return new StringLiteralType($statement->value);
    }
}
