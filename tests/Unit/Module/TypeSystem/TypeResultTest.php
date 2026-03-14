<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\TypeSystem;

use App\Module\TypeSystem\TypeResult;
use App\Tests\TestCase;
use PHPStan\Analyser\Scope;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class TypeResultTest extends TestCase
{
    #[TestDox('describe returns precise type description by default')]
    public function testDescribeReturnsPreciseType(): void
    {
        $type = new StringType();
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertSame('string', $result->describe());
    }

    #[TestDox('describe accepts custom verbosity level')]
    public function testDescribeWithCustomLevel(): void
    {
        $type = new IntegerType();
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertSame('int', $result->describe(VerbosityLevel::typeOnly()));
    }

    #[TestDox('describeShort returns type-only description')]
    public function testDescribeShort(): void
    {
        $type = new UnionType([new StringType(), new IntegerType()]);
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertSame('int|string', $result->describeShort());
    }

    #[TestDox('node is null by default')]
    public function testNodeIsNullByDefault(): void
    {
        $type = new StringType();
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertNull($result->node);
    }

    #[TestDox('stores scope for further queries')]
    public function testStoresScope(): void
    {
        $type = new StringType();
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertSame($scope, $result->scope);
    }

    #[TestDox('stores type for direct access')]
    public function testStoresType(): void
    {
        $type = new IntegerType();
        $scope = $this->createMock(Scope::class);
        $result = new TypeResult($type, $scope);

        $this->assertSame($type, $result->type);
    }
}
