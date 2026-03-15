<?php

declare(strict_types=1);

namespace App\Module\PsiFile;

use Lsp\Extension\DocumentManager\Editor\Document\Document;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class PHPPsiFileParser
{
    private Parser $parser;

    public function __construct()
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();

        $this->parser = $parser;
    }

    public function parse(Document $content): SourceFileRoot
    {
        $errorHandler = new Collecting();
        try {
            $ast = $this->parser->parse($content->getContents(), $errorHandler);

            $traverser = new NodeTraverser(
                new NameResolver($errorHandler, ['preserveOriginalNames' => true]),
                new NodeConnectingVisitor(),
                new ParentConnectingVisitor(),
            );
            $ast = $traverser->traverse($ast);
        } catch (Error $error) {
            echo "Parse error: {$error->getMessage()}\n";
            $ast = [];
        }

        return new SourceFileRoot($ast, $content, $errorHandler->getErrors());
    }
}
