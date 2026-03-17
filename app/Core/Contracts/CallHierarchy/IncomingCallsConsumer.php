<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

use Lsp\Protocol\Type\CallHierarchyIncomingCall;

class IncomingCallsConsumer
{
    public function __construct(
        /**
         * @var list<CallHierarchyIncomingCall>
         */
        public array $results = [],
    ) {}

    public function __invoke(CallHierarchyIncomingCall ...$items): void
    {
        array_push($this->results, ...$items);
    }
}
