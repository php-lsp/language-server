<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Indexer;

use App\Module\Indexing\Indexer\AbstractPhpIndexer;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\PHPPsiFile;
use App\Tests\Support\MockHelper;
use App\Tests\Support\PsiFileFactory;
use App\Tests\Support\VirtualFileStub;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class AbstractPhpIndexerTest extends TestCase
{
    #[TestDox('supports returns true for php files')]
    public function testSupportsPhpFile(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $indexer = new class($fileManager) extends AbstractPhpIndexer {
            public function __construct(InMemoryPsiFileManager $fm) { parent::__construct($fm); }
            public static function getKey(): string { return 'test'; }
            protected function indexInternal(PHPPsiFile $phpFile): array { return []; }
        };

        $file = VirtualFileStub::create('test.php');

        $this->assertTrue($indexer->supports($file));
    }

    #[TestDox('supports returns false for non-php files')]
    public function testDoesNotSupportNonPhp(): void
    {
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $indexer = new class($fileManager) extends AbstractPhpIndexer {
            public function __construct(InMemoryPsiFileManager $fm) { parent::__construct($fm); }
            public static function getKey(): string { return 'test'; }
            protected function indexInternal(PHPPsiFile $phpFile): array { return []; }
        };

        $file = VirtualFileStub::create('test.js');

        $this->assertFalse($indexer->supports($file));
    }

    #[TestDox('index delegates to findPsiFileByUri and indexInternal')]
    public function testIndexDelegates(): void
    {
        $psiFile = PsiFileFactory::fromCode('<?php class Foo {}');
        $fileManager = MockHelper::mock(InMemoryPsiFileManager::class);
        $fileManager->method('findPsiFileByUri')->willReturn($psiFile);

        $indexer = new class($fileManager) extends AbstractPhpIndexer {
            public function __construct(InMemoryPsiFileManager $fm) { parent::__construct($fm); }
            public static function getKey(): string { return 'test'; }
            protected function indexInternal(PHPPsiFile $phpFile): array { return ['result']; }
        };

        $file = VirtualFileStub::create('test.php');
        $result = $indexer->index($file);

        $this->assertSame(['result'], [...$result]);
    }
}
