package main

import (
	"bufio"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
)

// ClassInfo represents an indexed PHP class declaration.
type ClassInfo struct {
	FQN        string   `json:"fqn"`
	Name       string   `json:"name"`
	Line       int      `json:"line"`
	IsAbstract bool     `json:"is_abstract"`
	IsFinal    bool     `json:"is_final"`
	IsReadonly bool     `json:"is_readonly"`
	Extends    string   `json:"extends"`
	Implements []string `json:"implements"`
	URI        string   `json:"uri"`
}

// FunctionInfo represents an indexed PHP function declaration.
type FunctionInfo struct {
	FQN  string `json:"fqn"`
	Name string `json:"name"`
	Line int    `json:"line"`
	URI  string `json:"uri"`
}

// IndexResult holds the complete index for a workspace.
type IndexResult struct {
	Classes   []ClassInfo    `json:"classes"`
	Functions []FunctionInfo `json:"functions"`
	FileCount int            `json:"file_count"`
}

// GoIndexer performs concurrent PHP file indexing.
type GoIndexer struct {
	mu        sync.RWMutex
	classes   []ClassInfo
	functions []FunctionInfo
	fileCount int
}

// NewGoIndexer creates a new indexer instance.
func NewGoIndexer() *GoIndexer {
	return &GoIndexer{}
}

// Regex patterns for PHP declarations.
var (
	namespaceRe = regexp.MustCompile(`^\s*namespace\s+([A-Za-z0-9_\\]+)\s*[;{]`)
	classRe     = regexp.MustCompile(`^\s*((?:(?:abstract|final|readonly)\s+)*)class\s+([A-Za-z0-9_]+)(?:\s+extends\s+([A-Za-z0-9_\\]+))?(?:\s+implements\s+([A-Za-z0-9_\\,\s]+))?`)
	functionRe  = regexp.MustCompile(`^\s*function\s+([A-Za-z0-9_]+)\s*\(`)
)

// IndexWorkspace walks a directory and indexes all PHP files concurrently.
func (idx *GoIndexer) IndexWorkspace(root string, concurrency int) (*IndexResult, error) {
	if concurrency <= 0 {
		concurrency = 8
	}

	// Collect PHP file paths.
	var files []string
	err := filepath.Walk(root, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			return nil
		}
		name := info.Name()
		if info.IsDir() {
			if isIgnoredDir(name) {
				return filepath.SkipDir
			}
			return nil
		}
		if strings.HasSuffix(name, ".php") {
			files = append(files, path)
		}
		return nil
	})
	if err != nil {
		return nil, err
	}

	// Process files concurrently.
	type fileResult struct {
		classes   []ClassInfo
		functions []FunctionInfo
	}

	results := make([]fileResult, len(files))
	sem := make(chan struct{}, concurrency)
	var wg sync.WaitGroup

	for i, f := range files {
		wg.Add(1)
		go func(idx int, path string) {
			defer wg.Done()
			sem <- struct{}{}
			defer func() { <-sem }()

			classes, functions := indexFile(path)
			results[idx] = fileResult{classes: classes, functions: functions}
		}(i, f)
	}
	wg.Wait()

	// Merge results.
	idx.mu.Lock()
	idx.classes = idx.classes[:0]
	idx.functions = idx.functions[:0]
	for _, r := range results {
		idx.classes = append(idx.classes, r.classes...)
		idx.functions = append(idx.functions, r.functions...)
	}
	idx.fileCount = len(files)
	result := &IndexResult{
		Classes:   idx.classes,
		Functions: idx.functions,
		FileCount: idx.fileCount,
	}
	idx.mu.Unlock()

	return result, nil
}

// GetResult returns the current index state.
func (idx *GoIndexer) GetResult() *IndexResult {
	idx.mu.RLock()
	defer idx.mu.RUnlock()
	return &IndexResult{
		Classes:   idx.classes,
		Functions: idx.functions,
		FileCount: idx.fileCount,
	}
}

// indexFile parses a single PHP file and extracts class/function declarations.
func indexFile(path string) ([]ClassInfo, []FunctionInfo) {
	f, err := os.Open(path)
	if err != nil {
		return nil, nil
	}
	defer f.Close()

	uri := "file://" + path
	var namespace string
	var classes []ClassInfo
	var functions []FunctionInfo

	scanner := bufio.NewScanner(f)
	scanner.Buffer(make([]byte, 0, 256*1024), 1024*1024)
	lineNum := 0

	for scanner.Scan() {
		lineNum++
		line := scanner.Text()

		// Detect namespace.
		if ns := namespaceRe.FindStringSubmatch(line); ns != nil {
			namespace = ns[1]
			continue
		}

		// Detect class declaration.
		if cm := classRe.FindStringSubmatch(line); cm != nil {
			modifiers := cm[1]
			name := cm[2]
			extends := cm[3]
			implementsStr := cm[4]

			fqn := name
			if namespace != "" {
				fqn = namespace + "\\" + name
			}

			var implements []string
			if implementsStr != "" {
				for _, impl := range strings.Split(implementsStr, ",") {
					impl = strings.TrimSpace(impl)
					if impl != "" {
						implements = append(implements, impl)
					}
				}
			}
			if implements == nil {
				implements = []string{}
			}

			classes = append(classes, ClassInfo{
				FQN:        fqn,
				Name:       name,
				Line:       lineNum,
				IsAbstract: strings.Contains(modifiers, "abstract"),
				IsFinal:    strings.Contains(modifiers, "final"),
				IsReadonly: strings.Contains(modifiers, "readonly"),
				Extends:    extends,
				Implements: implements,
				URI:        uri,
			})
			continue
		}

		// Detect function declaration (top-level only, rough heuristic).
		if fm := functionRe.FindStringSubmatch(line); fm != nil {
			name := fm[1]
			fqn := name
			if namespace != "" {
				fqn = namespace + "\\" + name
			}
			functions = append(functions, FunctionInfo{
				FQN:  fqn,
				Name: name,
				Line: lineNum,
				URI:  uri,
			})
		}
	}

	return classes, functions
}

func isIgnoredDir(name string) bool {
	switch name {
	case "node_modules", ".git", ".idea", ".vscode", "runtime":
		return true
	}
	return false
}
