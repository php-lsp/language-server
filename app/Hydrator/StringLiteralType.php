<?php

declare(strict_types=1);

namespace App\Hydrator;

use Override;
use TypeLang\Mapper\Exception\Mapping\InvalidValueException;
use TypeLang\Mapper\Runtime\Context;
use TypeLang\Mapper\Type\TypeInterface;

final class StringLiteralType implements TypeInterface
{
    public function __construct(
        private readonly string $value,
    ) {}

    #[Override]
    public function match(mixed $value, Context $context): bool
    {
        return $value === $this->value;
    }

    #[Override]
    public function cast(mixed $value, Context $context): string
    {
        if (\is_string($value)) {
            return $value;
        }

        throw InvalidValueException::createFromContext(
            value: $value,
            context: $context,
        );
    }
}
