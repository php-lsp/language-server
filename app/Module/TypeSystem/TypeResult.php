<?php

declare(strict_types=1);

namespace App\Module\TypeSystem;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

final readonly class TypeResult
{
    public function __construct(
        public Type $type,
        public Scope $scope,
        public ?Node $node = null,
    ) {}

    public function describe(?VerbosityLevel $level = null): string
    {
        return $this->type->describe($level ?? VerbosityLevel::precise());
    }

    public function describeShort(): string
    {
        return $this->type->describe(VerbosityLevel::typeOnly());
    }
}
