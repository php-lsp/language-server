<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\FunctionCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionCompletionContributorTest extends TestCase
{
    #[TestDox('throws TypeError because index stores arrays but matcher expects string')]
    public function testThrowsTypeErrorDueToArrayValue(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => ['myFunc', 6]],
            ],
        ]);
        $contributor = new FunctionCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php myFu');

        $this->expectException(\TypeError::class);
        CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 10),
        );
    }
}
