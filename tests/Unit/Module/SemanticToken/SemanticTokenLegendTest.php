<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\SemanticToken;

use App\Module\SemanticToken\SemanticTokenLegend;
use App\Tests\TestCase;
use Lsp\Protocol\Type\SemanticTokenModifiers;
use Lsp\Protocol\Type\SemanticTokenTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class SemanticTokenLegendTest extends TestCase
{
    #[TestDox('type index returns correct position for each token type')]
    public function testTypeIndex(): void
    {
        $this->assertSame(0, SemanticTokenLegend::typeIndex(SemanticTokenTypes::NamespaceType));
        $this->assertSame(2, SemanticTokenLegend::typeIndex(SemanticTokenTypes::ClassType));
        $this->assertSame(3, SemanticTokenLegend::typeIndex(SemanticTokenTypes::EnumType));
        $this->assertSame(4, SemanticTokenLegend::typeIndex(SemanticTokenTypes::InterfaceType));
        $this->assertSame(6, SemanticTokenLegend::typeIndex(SemanticTokenTypes::ParameterType));
        $this->assertSame(7, SemanticTokenLegend::typeIndex(SemanticTokenTypes::VariableType));
        $this->assertSame(8, SemanticTokenLegend::typeIndex(SemanticTokenTypes::PropertyType));
        $this->assertSame(10, SemanticTokenLegend::typeIndex(SemanticTokenTypes::FunctionType));
        $this->assertSame(11, SemanticTokenLegend::typeIndex(SemanticTokenTypes::MethodType));
        $this->assertSame(18, SemanticTokenLegend::typeIndex(SemanticTokenTypes::DecoratorType));
    }

    #[TestDox('modifier bit returns correct bitmask')]
    public function testModifierBit(): void
    {
        $this->assertSame(1, SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Declaration));
        $this->assertSame(2, SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Definition));
        $this->assertSame(4, SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Readonly));
        $this->assertSame(8, SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Static));
        $this->assertSame(32, SemanticTokenLegend::modifierBit(SemanticTokenModifiers::Abstract));
    }

    #[TestDox('legend returns valid SemanticTokensLegend')]
    public function testLegend(): void
    {
        $legend = SemanticTokenLegend::legend();

        $this->assertSame(SemanticTokenLegend::TOKEN_TYPES, $legend->tokenTypes);
        $this->assertSame(SemanticTokenLegend::TOKEN_MODIFIERS, $legend->tokenModifiers);
    }
}
