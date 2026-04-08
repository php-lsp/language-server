package main

import (
	"fmt"
	"strings"
)

// RPCService exposes indexer methods over net/rpc.
type RPCService struct {
	indexer *GoIndexer
}

// IndexRequest is the argument for Index RPC call.
type IndexRequest struct {
	Path        string `json:"path"`
	Concurrency int    `json:"concurrency"`
}

// IndexResponse is returned by Index RPC call.
type IndexResponse struct {
	Classes   []ClassInfo    `json:"classes"`
	Functions []FunctionInfo `json:"functions"`
	FileCount int            `json:"file_count"`
}

// SearchRequest is the argument for Search RPC call.
type SearchRequest struct {
	Query string `json:"query"`
	Key   string `json:"key"`
}

// SearchResponse is returned by Search RPC call.
type SearchResponse struct {
	Classes   []ClassInfo    `json:"classes"`
	Functions []FunctionInfo `json:"functions"`
}

// PingRequest is used for the Ping RPC call.
type PingRequest struct{}

// PingResponse is returned by Ping RPC call.
type PingResponse struct {
	Status    string `json:"status"`
	FileCount int    `json:"file_count"`
}

// Index triggers full workspace indexing.
func (s *RPCService) Index(req IndexRequest, resp *IndexResponse) error {
	if req.Path == "" {
		return fmt.Errorf("path is required")
	}

	result, err := s.indexer.IndexWorkspace(req.Path, req.Concurrency)
	if err != nil {
		return fmt.Errorf("indexing failed: %w", err)
	}

	resp.Classes = result.Classes
	resp.Functions = result.Functions
	resp.FileCount = result.FileCount
	return nil
}

// Search finds declarations matching a query.
func (s *RPCService) Search(req SearchRequest, resp *SearchResponse) error {
	result := s.indexer.GetResult()

	query := req.Query
	if query == "" {
		resp.Classes = result.Classes
		resp.Functions = result.Functions
		return nil
	}

	queryLower := strings.ToLower(query)

	switch req.Key {
	case "php.classes.fqn", "classes":
		for _, c := range result.Classes {
			if strings.Contains(strings.ToLower(c.FQN), queryLower) || strings.Contains(strings.ToLower(c.Name), queryLower) {
				resp.Classes = append(resp.Classes, c)
			}
		}
	case "php.functions.fqn", "functions":
		for _, f := range result.Functions {
			if strings.Contains(strings.ToLower(f.FQN), queryLower) || strings.Contains(strings.ToLower(f.Name), queryLower) {
				resp.Functions = append(resp.Functions, f)
			}
		}
	default:
		// Search both.
		for _, c := range result.Classes {
			if strings.Contains(strings.ToLower(c.FQN), queryLower) || strings.Contains(strings.ToLower(c.Name), queryLower) {
				resp.Classes = append(resp.Classes, c)
			}
		}
		for _, f := range result.Functions {
			if strings.Contains(strings.ToLower(f.FQN), queryLower) || strings.Contains(strings.ToLower(f.Name), queryLower) {
				resp.Functions = append(resp.Functions, f)
			}
		}
	}

	return nil
}

// Ping checks if the indexer is alive.
func (s *RPCService) Ping(_ PingRequest, resp *PingResponse) error {
	result := s.indexer.GetResult()
	resp.Status = "ok"
	resp.FileCount = result.FileCount
	return nil
}
