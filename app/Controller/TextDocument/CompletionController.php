<?php

declare(strict_types=1);

namespace App\Controller\TextDocument;

use App\Core\FunctionCompletionProvider;
use App\Module\PsiFile\InMemoryPsiFileManager;
use App\Module\PsiFile\Tree;
use Lsp\Extension\DocumentManager\Editor\EditorInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Protocol\Type\CompletionItem;
use Lsp\Protocol\Type\CompletionItemKind;
use Lsp\Protocol\Type\CompletionParams;
use Lsp\Router\Attribute\Route;
use Microsoft\PhpParser\MissingToken;
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

        $functionCompletionProvider = new FunctionCompletionProvider();
        ['internal' => $internalFunctions, 'user' => $userFunctions] = $functionCompletionProvider->complete();
        $internalFunctions = $this->filter($internalFunctions, $request);
        $userFunctions = $this->filter($userFunctions, $request);

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

        return array_map(
            fn(string $name) => new CompletionItem(
                label: $name,
                kind: CompletionItemKind::FunctionKind,
            ),
            $internalFunctions,
        );
    }

    private function filter(array $functions, CompletionParams $request): array
    {
        $result = [];

        $result = array_splice($functions, 0, 50);
        return $result;
    }
}
