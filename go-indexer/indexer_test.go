package main

import (
	"os"
	"path/filepath"
	"testing"
)

func createTestFiles(t *testing.T, dir string) {
	t.Helper()

	files := map[string]string{
		"src/Controller/UserController.php": `<?php

namespace App\Controller;

final class UserController extends AbstractController implements JsonSerializable {
    public function index(): void {}
}
`,
		"src/Model/User.php": `<?php

namespace App\Model;

abstract readonly class User {
    public function getName(): string {}
}
`,
		"src/functions.php": `<?php

namespace App\Helpers;

function formatDate(string $date): string {
    return $date;
}

function slugify(string $text): string {
    return $text;
}
`,
		"src/Service/AuthService.php": `<?php

namespace App\Service;

final class AuthService implements AuthInterface, LoggerAware {
}
`,
		"vendor/some/package/Foo.php": `<?php

namespace Some\Package;

class Foo {
}
`,
	}

	for path, content := range files {
		fullPath := filepath.Join(dir, path)
		if err := os.MkdirAll(filepath.Dir(fullPath), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(fullPath, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}
}

func TestIndexWorkspace(t *testing.T) {
	dir := t.TempDir()
	createTestFiles(t, dir)

	indexer := NewGoIndexer()
	result, err := indexer.IndexWorkspace(dir, 4)
	if err != nil {
		t.Fatalf("IndexWorkspace failed: %v", err)
	}

	if result.FileCount != 5 {
		t.Errorf("expected 5 files, got %d", result.FileCount)
	}

	if len(result.Classes) != 4 {
		t.Errorf("expected 4 classes, got %d", len(result.Classes))
		for _, c := range result.Classes {
			t.Logf("  class: %s", c.FQN)
		}
	}

	if len(result.Functions) != 2 {
		t.Errorf("expected 2 functions, got %d", len(result.Functions))
		for _, f := range result.Functions {
			t.Logf("  function: %s", f.FQN)
		}
	}

	// Check specific class details.
	var userController *ClassInfo
	var user *ClassInfo
	for i, c := range result.Classes {
		switch c.FQN {
		case "App\\Controller\\UserController":
			userController = &result.Classes[i]
		case "App\\Model\\User":
			user = &result.Classes[i]
		}
	}

	if userController == nil {
		t.Fatal("UserController not found")
	}
	if !userController.IsFinal {
		t.Error("UserController should be final")
	}
	if userController.Extends != "AbstractController" {
		t.Errorf("UserController extends: got %q, want %q", userController.Extends, "AbstractController")
	}
	if len(userController.Implements) != 1 || userController.Implements[0] != "JsonSerializable" {
		t.Errorf("UserController implements: got %v, want [JsonSerializable]", userController.Implements)
	}

	if user == nil {
		t.Fatal("User not found")
	}
	if !user.IsAbstract {
		t.Error("User should be abstract")
	}
	if !user.IsReadonly {
		t.Error("User should be readonly")
	}
}

func TestIgnoredDirs(t *testing.T) {
	dir := t.TempDir()

	// Create files in ignored directories.
	ignoredDirs := []string{"node_modules", ".git", ".idea"}
	for _, d := range ignoredDirs {
		fullPath := filepath.Join(dir, d, "test.php")
		os.MkdirAll(filepath.Dir(fullPath), 0o755)
		os.WriteFile(fullPath, []byte("<?php\nclass Ignored {}\n"), 0o644)
	}

	// Create a valid file.
	validPath := filepath.Join(dir, "src", "Valid.php")
	os.MkdirAll(filepath.Dir(validPath), 0o755)
	os.WriteFile(validPath, []byte("<?php\nclass Valid {}\n"), 0o644)

	indexer := NewGoIndexer()
	result, err := indexer.IndexWorkspace(dir, 4)
	if err != nil {
		t.Fatalf("IndexWorkspace failed: %v", err)
	}

	if result.FileCount != 1 {
		t.Errorf("expected 1 file (ignored dirs should be skipped), got %d", result.FileCount)
	}
}

func TestRPCSearch(t *testing.T) {
	dir := t.TempDir()
	createTestFiles(t, dir)

	indexer := NewGoIndexer()
	if _, err := indexer.IndexWorkspace(dir, 4); err != nil {
		t.Fatal(err)
	}

	svc := &RPCService{indexer: indexer}

	// Search for classes by name.
	var resp SearchResponse
	err := svc.Search(SearchRequest{Query: "User", Key: "classes"}, &resp)
	if err != nil {
		t.Fatal(err)
	}
	if len(resp.Classes) != 2 {
		t.Errorf("expected 2 classes matching 'User', got %d", len(resp.Classes))
	}

	// Search for functions.
	var funcResp SearchResponse
	err = svc.Search(SearchRequest{Query: "format", Key: "functions"}, &funcResp)
	if err != nil {
		t.Fatal(err)
	}
	if len(funcResp.Functions) != 1 {
		t.Errorf("expected 1 function matching 'format', got %d", len(funcResp.Functions))
	}
}

// BenchmarkIndexWorkspace benchmarks workspace indexing with various concurrency levels.
func BenchmarkIndexWorkspace(b *testing.B) {
	dir := b.TempDir()

	// Create a larger test workspace.
	for i := 0; i < 500; i++ {
		path := filepath.Join(dir, "src", "Generated", "Class"+itoa(i)+".php")
		os.MkdirAll(filepath.Dir(path), 0o755)
		content := "<?php\n\nnamespace App\\Generated;\n\nclass Class" + itoa(i) + " extends BaseClass implements Serializable {\n    public function method" + itoa(i) + "(): void {}\n}\n"
		os.WriteFile(path, []byte(content), 0o644)
	}

	concurrencies := []int{1, 2, 4, 8}
	for _, c := range concurrencies {
		b.Run("concurrency="+itoa(c), func(b *testing.B) {
			for b.Loop() {
				indexer := NewGoIndexer()
				result, err := indexer.IndexWorkspace(dir, c)
				if err != nil {
					b.Fatal(err)
				}
				if result.FileCount != 500 {
					b.Fatalf("expected 500 files, got %d", result.FileCount)
				}
			}
		})
	}
}

func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	s := ""
	for n > 0 {
		s = string(rune('0'+n%10)) + s
		n /= 10
	}
	return s
}
