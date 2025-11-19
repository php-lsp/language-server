<?php
declare(strict_types=1);

namespace App\Core\Contracts\Signature;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsSignatureContributor
{
    public function __construct()
    {
    }
}
