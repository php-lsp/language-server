<?php

declare(strict_types=1);

namespace App\Module\SemanticToken;

use App\Core\Contracts\SemanticToken\AsSemanticTokenContributor;
use App\Core\Contracts\SemanticToken\SemanticTokenConsumer;
use App\Core\Contracts\SemanticToken\SemanticTokenContext;
use App\Core\Contracts\SemanticToken\SemanticTokenContributor;
use App\Module\PsiFile\Tree;
use Lsp\Protocol\Type\SemanticTokenModifiers;
use Lsp\Protocol\Type\SemanticTokenTypes;
use Override;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

#[AsSemanticTokenContributor]
final class AstSemanticTokenContributor extends NodeVisitorAbstract implements SemanticTokenContributor
{
    private SemanticTokenConsumer $consumer;

    /**
     * @var \Lsp\Extension\DocumentManager\Editor\Document\Document
     */
    private \Lsp\Extension\DocumentManager\Editor\Document\Document $document;

    #[Override]
    public function contribute(SemanticTokenContext $context, SemanticTokenConsumer $consumer): void
    {
        $file = $context->fileManager->findPsiFile($context->editor, $context->textDocumentIdentifier);
        if ($file === null) {
            return;
        }

        $this->consumer = $consumer;
        $this->document = $file->getDocument();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($this);
        $traverser->traverse($file->getChildren());
    }

    #[Override]
    public function enterNode(Node $node): ?int
    {
        match (true) {
            $node instanceof Node\Stmt\Namespace_ => $this->visitNamespace($node),
            $node instanceof Node\Stmt\Class_ => $this->visitClass($node),
            $node instanceof Node\Stmt\Interface_ => $this->visitInterface($node),
            $node instanceof Node\Stmt\Trait_ => $this->visitTrait($node),
            $node instanceof Node\Stmt\Enum_ => $this->visitEnum($node),
            $node instanceof Node\Stmt\EnumCase => $this->visitEnumCase($node),
            $node instanceof Node\Stmt\Function_ => $this->visitFunction($node),
            $node instanceof Node\Stmt\ClassMethod => $this->visitClassMethod($node),
            $node instanceof Node\Stmt\Property => $this->visitProperty($node),
            $node instanceof Node\Stmt\ClassConst => $this->visitClassConst($node),
            $node instanceof Node\Expr\Variable => $this->visitVariable($node),
            $node instanceof Node\Expr\FuncCall => $this->visitFuncCall($node),
            $node instanceof Node\Expr\MethodCall => $this->visitMethodCall($node),
            $node instanceof Node\Expr\StaticCall => $this->visitStaticCall($node),
            $node instanceof Node\Expr\PropertyFetch => $this->visitPropertyFetch($node),
            $node instanceof Node\Expr\StaticPropertyFetch => $this->visitStaticPropertyFetch($node),
            $node instanceof Node\Expr\ClassConstFetch => $this->visitClassConstFetch($node),
            $node instanceof Node\Expr\New_ => $this->visitNew($node),
            $node instanceof Node\Attribute => $this->visitAttribute($node),
            default => null,
        };

        return null;
    }

    private function visitNamespace(Node\Stmt\Namespace_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $this->addTokenForNode(
            $node->name,
            SemanticTokenTypes::NamespaceType,
            SemanticTokenModifiers::Declaration,
        );
    }

    private function visitClass(Node\Stmt\Class_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $modifiers =
            SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration)
            | SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Definition);

        if ($node->isAbstract()) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Abstract);
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::ClassType,
            $modifiers,
        );

        if ($node->extends !== null) {
            $this->addTokenForNode($node->extends, SemanticTokenTypes::ClassType);
        }

        foreach ($node->implements as $implement) {
            $this->addTokenForNode($implement, SemanticTokenTypes::InterfaceType);
        }
    }

    private function visitInterface(Node\Stmt\Interface_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::InterfaceType,
            SemanticTokenModifiers::Declaration,
        );

        foreach ($node->extends as $extend) {
            $this->addTokenForNode($extend, SemanticTokenTypes::InterfaceType);
        }
    }

    private function visitTrait(Node\Stmt\Trait_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::ClassType,
            SemanticTokenModifiers::Declaration,
        );
    }

    private function visitEnum(Node\Stmt\Enum_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::EnumType,
            SemanticTokenModifiers::Declaration,
        );

        foreach ($node->implements as $implement) {
            $this->addTokenForNode($implement, SemanticTokenTypes::InterfaceType);
        }
    }

    private function visitEnumCase(Node\Stmt\EnumCase $node): void
    {
        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::EnumMemberType,
            SemanticTokenModifiers::Declaration,
        );
    }

    private function visitFunction(Node\Stmt\Function_ $node): void
    {
        if ($node->name === null) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::FunctionType,
            SemanticTokenModifiers::Declaration,
        );

        $this->visitParams($node->params);
    }

    private function visitClassMethod(Node\Stmt\ClassMethod $node): void
    {
        $modifiers =
            SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration)
            | SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Definition);

        if ($node->isStatic()) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Static);
        }

        if ($node->isAbstract()) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Abstract);
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::MethodType,
            $modifiers,
        );

        $this->visitParams($node->params);
    }

    /**
     * @param list<Node\Param> $params
     */
    private function visitParams(array $params): void
    {
        foreach ($params as $param) {
            if (!$param->var instanceof Node\Expr\Variable) {
                continue;
            }

            if (!is_string($param->var->name)) {
                continue;
            }

            $modifiers = SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration);

            if ($param->flags & Node\Stmt\Class_::MODIFIER_READONLY) {
                $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Readonly);
            }

            $this->addTokenForVariable(
                $param->var,
                SemanticTokenTypes::ParameterType,
                $modifiers,
            );

            if ($param->type !== null) {
                $this->visitTypeNode($param->type);
            }
        }
    }

    private function visitProperty(Node\Stmt\Property $node): void
    {
        $modifiers = SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration);

        if ($node->isStatic()) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Static);
        }

        if ($node->isReadonly()) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Readonly);
        }

        foreach ($node->props as $prop) {
            $this->addTokenForNode(
                $prop,
                SemanticTokenTypes::PropertyType,
                $modifiers,
            );
        }

        if ($node->type !== null) {
            $this->visitTypeNode($node->type);
        }
    }

    private function visitClassConst(Node\Stmt\ClassConst $node): void
    {
        $modifiers =
            SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration)
            | SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Readonly);

        foreach ($node->consts as $const) {
            $this->addTokenForIdentifier(
                $const->name,
                SemanticTokenTypes::PropertyType,
                $modifiers,
            );
        }
    }

    private function visitVariable(Node\Expr\Variable $node): void
    {
        if (!is_string($node->name)) {
            return;
        }

        // Skip variables that are function/method parameters (handled in visitParams)
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Param) {
            return;
        }

        $modifiers = 0;

        // Check if this is the left side of an assignment
        if ($parent instanceof Node\Expr\Assign && $parent->var === $node) {
            $modifiers |= SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Modification);
        }

        $this->addTokenForVariable(
            $node,
            SemanticTokenTypes::VariableType,
            $modifiers,
        );
    }

    private function visitFuncCall(Node\Expr\FuncCall $node): void
    {
        if (!$node->name instanceof Node\Name) {
            return;
        }

        $this->addTokenForNode($node->name, SemanticTokenTypes::FunctionType);
    }

    private function visitMethodCall(Node\Expr\MethodCall $node): void
    {
        if (!$node->name instanceof Node\Identifier) {
            return;
        }

        $this->addTokenForIdentifier($node->name, SemanticTokenTypes::MethodType);
    }

    private function visitStaticCall(Node\Expr\StaticCall $node): void
    {
        if ($node->class instanceof Node\Name) {
            $this->addTokenForNode($node->class, SemanticTokenTypes::ClassType);
        }

        if (!$node->name instanceof Node\Identifier) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::MethodType,
            SemanticTokenModifiers::Static,
        );
    }

    private function visitPropertyFetch(Node\Expr\PropertyFetch $node): void
    {
        if (!$node->name instanceof Node\Identifier) {
            return;
        }

        $this->addTokenForIdentifier($node->name, SemanticTokenTypes::PropertyType);
    }

    private function visitStaticPropertyFetch(Node\Expr\StaticPropertyFetch $node): void
    {
        if ($node->class instanceof Node\Name) {
            $this->addTokenForNode($node->class, SemanticTokenTypes::ClassType);
        }

        if (!$node->name instanceof Node\VarLikeIdentifier) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::PropertyType,
            SemanticTokenModifiers::Static,
        );
    }

    private function visitClassConstFetch(Node\Expr\ClassConstFetch $node): void
    {
        if ($node->class instanceof Node\Name) {
            $this->addTokenForNode($node->class, SemanticTokenTypes::ClassType);
        }

        if (!$node->name instanceof Node\Identifier) {
            return;
        }

        $this->addTokenForIdentifier(
            $node->name,
            SemanticTokenTypes::PropertyType,
            SemanticTokenModifiers::Readonly,
        );
    }

    private function visitNew(Node\Expr\New_ $node): void
    {
        if (!$node->class instanceof Node\Name) {
            return;
        }

        $this->addTokenForNode($node->class, SemanticTokenTypes::ClassType);
    }

    private function visitAttribute(Node\Attribute $node): void
    {
        $this->addTokenForNode($node->name, SemanticTokenTypes::DecoratorType);
    }

    private function visitTypeNode(Node\ComplexType|Node\Identifier|Node\Name $node): void
    {
        if ($node instanceof Node\UnionType) {
            foreach ($node->types as $type) {
                $this->visitTypeNode($type);
            }

            return;
        }

        if ($node instanceof Node\IntersectionType) {
            foreach ($node->types as $type) {
                $this->visitTypeNode($type);
            }

            return;
        }

        if ($node instanceof Node\NullableType) {
            $this->visitTypeNode($node->type);

            return;
        }

        if ($node instanceof Node\Name) {
            $name = $node->toLowerString();
            // Skip built-in type names
            if (in_array(
                $name,
                [
                    'int',
                    'float',
                    'string',
                    'bool',
                    'array',
                    'object',
                    'null',
                    'void',
                    'never',
                    'mixed',
                    'callable',
                    'iterable',
                    'self',
                    'parent',
                    'static',
                    'true',
                    'false',
                ],
                strict: true,
            )) {
                return;
            }

            $this->addTokenForNode($node, SemanticTokenTypes::TypeType);
        }
    }

    private function addTokenForNode(
        Node $node,
        SemanticTokenTypes $type,
        SemanticTokenModifiers|int $modifiers = 0,
    ): void {
        $startPos = $node->getStartFilePos();
        $endPos = $node->getEndFilePos();
        if ($startPos < 0 || $endPos < 0) {
            return;
        }

        [$line, $column] = Tree::toLineColumn($this->document, $startPos);
        $length = $endPos - $startPos + 1;

        if ($length <= 0) {
            return;
        }

        $modifierBits = $modifiers instanceof SemanticTokenModifiers
            ? SemanticTokenLegend::modifierBit($modifiers)
            : $modifiers;

        /** @var int<0, 2147483647> $line */
        /** @var int<0, 2147483647> $column */
        /** @var int<0, 2147483647> $length */
        /** @var int<0, 2147483647> $modifierBits */
        ($this->consumer)(new RawSemanticToken(
            line: $line,
            column: $column,
            length: $length,
            type: SemanticTokenLegend::typeIndex($type),
            modifiers: $modifierBits,
        ));
    }

    private function addTokenForIdentifier(
        Node\Identifier $node,
        SemanticTokenTypes $type,
        SemanticTokenModifiers|int $modifiers = 0,
    ): void {
        $this->addTokenForNode($node, $type, $modifiers);
    }

    private function addTokenForVariable(
        Node\Expr\Variable $node,
        SemanticTokenTypes $type,
        int $modifiers = 0,
    ): void {
        $this->addTokenForNode($node, $type, $modifiers);
    }
}
