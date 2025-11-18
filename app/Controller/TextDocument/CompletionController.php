<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\Completion\FunctionCompletionContributor;
use App\Core\Completion\SuperglobalsCompletionContributor;
use App\Core\Contracts\CompletionConsumer;
use App\Core\Contracts\CompletionContext;
use App\Core\Contracts\CompletionContributor;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use PhpParser\Node\Stmt\ClassMethod;

#[AsController, Route('textDocument/completion')]
final class CompletionController
{
    public function __construct(
//        private InMemoryFileFactory $fileFactory,
        private InMemoryPsiFileManager $fileManager,
    )
    {
    }

    public function __invoke(EditorInterface $editor, CompletionParams $request)
    {
        dump('CompletionParams: ', $request);


        $this->fileManager->commit($editor, $request->textDocument);
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

        /**
         * @var class-string<CompletionContributor>[] $contributors
         */
        $contributors = [
            SuperglobalsCompletionContributor::class,
            FunctionCompletionContributor::class,
        ];

        $context = new CompletionContext();
        $consumer = new CompletionConsumer();

        foreach ($contributors as $contributor) {
            $contributor = new $contributor();
            $contributor->contribute($context, $consumer);
        }

        return $consumer->results;
    }
}
