<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

use Lsp\Protocol\Type\CallHierarchyOutgoingCall;

class OutgoingCallsConsumer
{
    public function __construct(
        /**
         * @var list<CallHierarchyOutgoingCall>
         */
        public array $results = [],
    ) {}

    public function __invoke(CallHierarchyOutgoingCall ...$items): void
    {
        array_push($this->results, ...$items);
    }
}
