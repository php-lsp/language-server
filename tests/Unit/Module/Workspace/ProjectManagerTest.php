<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Workspace;

use App\Module\Workspace\ProjectManager;
use App\Tests\TestCase;
use Lsp\Workspace\Project\Project;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class ProjectManagerTest extends TestCase
{
    #[TestDox('setProject and getProject round-trip')]
    public function testSetGetProject(): void
    {
        $manager = new ProjectManager();
        $project = $this->createMock(Project::class);

        $manager->setProject($project);

        $this->assertSame($project, $manager->getProject());
    }

    #[TestDox('setProject accepts null and getProject returns null')]
    public function testSetNull(): void
    {
        $manager = new ProjectManager();
        $manager->setProject(null);

        $this->assertNull($manager->getProject());
    }
}
