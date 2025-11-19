<?php
declare(strict_types=1);

namespace App\Core\Contracts\Signature;

use Lsp\Protocol\Type\SignatureInformation;

class SignatureConsumer
{
    public function __construct(
        /**
         * @var list<SignatureInformation>
         */
        public array $results = [],
    )
    {
    }

    public function __invoke(SignatureInformation ...$items): void
    {
        array_push($this->results, ...$items);
    }
}
