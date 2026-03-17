<?php

declare(strict_types=1);

namespace App\Core\Contracts\CallHierarchy;

interface CallHierarchyContributor
{
    public function prepare(PrepareCallHierarchyContext $context, PrepareCallHierarchyConsumer $consumer): void;

    public function incomingCalls(IncomingCallsContext $context, IncomingCallsConsumer $consumer): void;

    public function outgoingCalls(OutgoingCallsContext $context, OutgoingCallsConsumer $consumer): void;
}
