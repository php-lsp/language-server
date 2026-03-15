<?php

declare(strict_types=1);

namespace App\Core\Contracts\CodeAction;

interface CodeActionContributor
{
    public function contribute(CodeActionContext $context, CodeActionConsumer $consumer): void;
}
