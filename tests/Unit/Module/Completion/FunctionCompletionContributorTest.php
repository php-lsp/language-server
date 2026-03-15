<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\FunctionCompletionContributor;
use App\Module\Indexing\Data\FunctionData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class FunctionCompletionContributorTest extends TestCase
{
    #[TestDox('completes function names from index')]
    public function testCompletesFunctionNames(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.functions.fqn' => [
                'file:///test.php' => ['myFunc' => new FunctionData('myFunc', 0, 30, null, [])],
            ],
        ]);
        $contributor = new FunctionCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php myFu');

        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 10),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::FunctionKind, $results[0]->kind);
        $this->assertSame('myFunc', $results[0]->label);
    }
}
