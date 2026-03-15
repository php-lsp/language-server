<?php

declare(strict_types=1);

namespace App\Hydrator;

use TypeLang\Mapper\Runtime\Context;
use TypeLang\Mapper\Type\TypeInterface;

/**
 * Extends the bridge's NonNullCompositeType to return empty stdClass
 * instead of empty array when all fields are null, so that JSON
 * serialization produces {} instead of [].
 */
final class NonNullCompositeType implements TypeInterface
{
    public function __construct(
        private readonly TypeInterface $type,
    ) {}

    public function match(mixed $value, Context $context): bool
    {
        return $this->type->match($value, $context);
    }

    public function cast(mixed $value, Context $context): mixed
    {
        if ($context->isDenormalization()) {
            return $this->type->cast($value, $context);
        }

        $result = $this->type->cast($value, $context);

        if (\is_array($result)) {
            $minified = [];

            foreach ($result as $key => $inner) {
                if ($inner === null) {
                    continue;
                }

                $minified[$key] = $inner;
            }

            // Return stdClass for empty associative arrays so JSON
            // serializes as {} instead of []
            if ($minified === []) {
                return new \stdClass();
            }

            $result = $minified;
        }

        return $result;
    }
}
