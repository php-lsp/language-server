<?php
declare(strict_types=1);

namespace App\Core\Contracts\Documentation;

use Attribute;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsDocumentationContributor
{
    public function __construct()
    {
    }
}
