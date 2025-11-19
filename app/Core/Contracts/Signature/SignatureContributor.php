<?php
declare(strict_types=1);

namespace App\Core\Contracts\Signature;

interface SignatureContributor
{
    public function contribute(SignatureContext $context, SignatureConsumer $consumer): void;
}
