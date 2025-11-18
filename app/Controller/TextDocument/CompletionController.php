<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;
use App\Core\Contracts\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use PhpParser\Node\Stmt\ClassMethod;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsController, Route('textDocument/completion')]
final class CompletionController
{
    /**
     * @var list<CompletionContributor>
     */
    private array $contributors;

    public function __construct(
        private InMemoryPsiFileManager $fileManager,
        #[AutowireIterator('lsp.completionContributors')]
        iterable $contributors,
    )
    {
        $this->contributors = iterator_to_array($contributors);
    }

    public function __invoke(EditorInterface $editor, CompletionParams $request)
    {
        dump('CompletionParams: ', $request);

        $this->fileManager->refreshFile($editor, $request->textDocument);
        $file = $this->fileManager->findPsiFile($editor, $request->textDocument);
        $position = $request->position;
        $tokens = $file->findAtPosition($position);
//        dump('$tokens', $tokens);

        $token = $file->findLastAtPosition($position);
        $method = Tree::parentOfType($token, ClassMethod::class);
//        dump('$method', $method);

//        $tokenText= $token[0]->getText($file->ast->getFileContents());
//        dump('file', count($internalFunctions), count($userFunctions));
//        dump('counts', count($internalFunctions), count($userFunctions));

        $context = new CompletionContext();
        $consumer = new CompletionConsumer();

        foreach ($this->contributors as $contributor) {
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}
