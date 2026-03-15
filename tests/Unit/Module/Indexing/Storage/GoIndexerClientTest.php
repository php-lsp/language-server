<?php

declare(strict_types=1);

namespace App\Tests\Unit\Module\Indexing\Storage;

use App\Module\Indexing\Storage\GoIndexerClient;
use App\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('unit')]
final class GoIndexerClientTest extends TestCase
{
    private static function getBinaryPath(): string
    {
        return dirname(__DIR__, 5) . '/bin/go-indexer';
    }

    private static function getFixturePath(): string
    {
        return dirname(__DIR__, 4) . '/fixtures/go-indexer';
    }

    private static function ensureFixtures(): void
    {
        $dir = self::getFixturePath();
        if (is_dir($dir)) {
            return;
        }

        mkdir($dir . '/src/Controller', 0o755, true);
        mkdir($dir . '/src/Model', 0o755, true);
        mkdir($dir . '/src/Service', 0o755, true);

        file_put_contents($dir . '/src/Controller/UserController.php', <<<'PHP'
<?php

namespace App\Controller;

final class UserController extends AbstractController implements JsonSerializable {
    public function index(): void {}
}
PHP);

        file_put_contents($dir . '/src/Model/User.php', <<<'PHP'
<?php

namespace App\Model;

abstract readonly class User {
    public function getName(): string {}
}
PHP);

        file_put_contents($dir . '/src/Service/AuthService.php', <<<'PHP'
<?php

namespace App\Service;

class AuthService implements AuthInterface {
}
PHP);

        file_put_contents($dir . '/src/functions.php', <<<'PHP'
<?php

namespace App\Helpers;

function formatDate(string $date): string {
    return $date;
}
PHP);
    }

    #[TestDox('isAvailable returns true when binary exists')]
    public function testIsAvailable(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built. Run: cd go-indexer && go build -o ../bin/go-indexer .');
        }

        $client = new GoIndexerClient($binaryPath);
        $this->assertTrue($client->isAvailable());
    }

    #[TestDox('isAvailable returns false for missing binary')]
    public function testIsAvailableReturnsFalseForMissingBinary(): void
    {
        $client = new GoIndexerClient('/nonexistent/binary');
        $this->assertFalse($client->isAvailable());
    }

    #[TestDox('indexWorkspace returns classes and functions')]
    public function testIndexWorkspace(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $result = $client->indexWorkspace(self::getFixturePath());

        $this->assertArrayHasKey('classes', $result);
        $this->assertArrayHasKey('functions', $result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertSame(4, $result['file_count']);
        $this->assertCount(3, $result['classes']);
        $this->assertCount(1, $result['functions']);
    }

    #[TestDox('searchClasses filters by query')]
    public function testSearchClasses(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $client->indexWorkspace(self::getFixturePath());

        // Search for 'User' should match UserController and User.
        $results = $client->searchClasses('User');
        $this->assertCount(2, $results);

        // Search for 'Auth' should match AuthService.
        $results = $client->searchClasses('Auth');
        $this->assertCount(1, $results);
        $this->assertSame('App\\Service\\AuthService', $results[0]['fqn']);

        // Empty query returns all.
        $results = $client->searchClasses('');
        $this->assertCount(3, $results);
    }

    #[TestDox('searchFunctions filters by query')]
    public function testSearchFunctions(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $client->indexWorkspace(self::getFixturePath());

        $results = $client->searchFunctions('format');
        $this->assertCount(1, $results);
        $this->assertSame('App\\Helpers\\formatDate', $results[0]['fqn']);
    }

    #[TestDox('class data contains expected fields')]
    public function testClassDataFields(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $client->indexWorkspace(self::getFixturePath());

        $users = $client->searchClasses('UserController');
        $this->assertCount(1, $users);

        $userController = $users[0];
        $this->assertSame('App\\Controller\\UserController', $userController['fqn']);
        $this->assertSame('UserController', $userController['name']);
        $this->assertTrue($userController['is_final']);
        $this->assertFalse($userController['is_abstract']);
        $this->assertSame('AbstractController', $userController['extends']);
        $this->assertContains('JsonSerializable', $userController['implements']);
    }

    #[TestDox('clear resets cached results')]
    public function testClear(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $client->indexWorkspace(self::getFixturePath());

        $this->assertSame(4, $client->getFileCount());

        $client->clear();

        $this->assertSame(0, $client->getFileCount());
        $this->assertEmpty($client->searchClasses());
    }

    #[TestDox('getFileCount returns correct count')]
    public function testGetFileCount(): void
    {
        $binaryPath = self::getBinaryPath();
        if (!file_exists($binaryPath)) {
            $this->markTestSkipped('Go indexer binary not built.');
        }

        self::ensureFixtures();

        $client = new GoIndexerClient($binaryPath);
        $this->assertSame(0, $client->getFileCount());

        $client->indexWorkspace(self::getFixturePath());
        $this->assertSame(4, $client->getFileCount());
    }
}
