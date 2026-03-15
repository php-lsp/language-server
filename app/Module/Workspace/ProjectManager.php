<?php

declare(strict_types=1);

namespace App\Module\Workspace;

use Lsp\Workspace\Project\Project;

class ProjectManager
{
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): void
    {
        $this->project = $project;
    }
}
