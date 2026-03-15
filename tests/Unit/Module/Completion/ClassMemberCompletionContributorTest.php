<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Completion;

use App\Module\Completion\ClassMemberCompletionContributor;
use App\Module\Indexing\Storage\IndexData\MethodData;
use App\Module\Indexing\Storage\IndexData\PropertyData;
use App\Tests\Support\CompletionTestHelper;
use App\Tests\Support\IndexTestHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\ProtocolFactory;
use App\Tests\TestCase;
use Lsp\Protocol\Type\CompletionItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ClassMemberCompletionContributorTest extends TestCase
{
    #[TestDox('completes class members via $this->')]
    public function testCompletesClassMembers(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo::bar' => new MethodData('bar', 'Foo', 0, 10, 'public', false, false, [], 'void')],
            ],
            'php.properties.fqn' => [
                'file:///test.php' => ['Foo::$baz' => new PropertyData('baz', 'Foo', 0, 10, 'public', 'string', false, false)],
            ],
        ]);
        $contributor = new ClassMemberCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php class Foo { public function bar() { $this->b; } }');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 50),
        );

        $this->assertNotEmpty($results);
        $kinds = array_map(fn ($r) => $r->kind, $results);
        $this->assertContains(CompletionItemKind::MethodKind, $kinds);
        $this->assertContains(CompletionItemKind::PropertyKind, $kinds);
    }

    #[TestDox('returns empty when not in class member context')]
    public function testReturnsEmptyWhenNoMatch(): void
    {
        $lookup = IndexTestHelper::createLookup([
            'php.classMethods.fqn' => [
                'file:///test.php' => ['Foo::bar' => new MethodData('bar', 'Foo', 0, 10, 'public', false, false, [], 'void')],
            ],
            'php.properties.fqn' => [
                'file:///test.php' => ['Foo::$baz' => new PropertyData('baz', 'Foo', 0, 10, 'public', 'string', false, false)],
            ],
        ]);
        $contributor = new ClassMemberCompletionContributor($lookup);
        $psiFile = PsiFileFactory::fromCode('<?php echo 1;');
        $results = CompletionTestHelper::contribute(
            $contributor,
            $psiFile,
            ProtocolFactory::position(0, 5),
        );

        $this->assertEmpty($results);
    }
}
