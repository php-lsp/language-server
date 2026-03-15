<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\VariableCompletionContributor;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class VariableCompletionContributorTest extends TestCase
{
    #[TestDox('completes variable names from function scope')]
    public function testCompletesVariableNames(): void
    {
        $contributor = new VariableCompletionContributor();
        $psiFile = PsiFileFactory::fromCode('<?php function foo(string $name) { $n; }');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 36),
        );

        $this->assertNotEmpty($results);
        $this->assertSame(CompletionItemKind::VariableKind, $results[0]->kind);
        $this->assertSame('$name', $results[0]->label);
    }

    #[TestDox('returns empty when not in a variable context')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $contributor = new VariableCompletionContributor();
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 5),
        );

        $this->assertEmpty($results);
    }
}
