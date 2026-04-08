package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"net"
	"net/rpc"
	"os"
	"os/signal"
	"syscall"
)

func main() {
	addr := flag.String("addr", "127.0.0.1:9742", "RPC listen address (host:port)")
	mode := flag.String("mode", "server", "Mode: server (RPC daemon) or index (one-shot, print JSON)")
	path := flag.String("path", "", "Workspace path to index (required for index mode)")
	concurrency := flag.Int("concurrency", 8, "Number of concurrent indexing goroutines")
	flag.Parse()

	switch *mode {
	case "server":
		runServer(*addr)
	case "index":
		runOneShot(*path, *concurrency)
	default:
		fmt.Fprintf(os.Stderr, "unknown mode: %s\n", *mode)
		os.Exit(1)
	}
}

func runServer(addr string) {
	indexer := NewGoIndexer()
	service := &RPCService{indexer: indexer}

	server := rpc.NewServer()
	if err := server.RegisterName("GoIndexer", service); err != nil {
		log.Fatalf("Failed to register RPC service: %v", err)
	}

	listener, err := net.Listen("tcp", addr)
	if err != nil {
		log.Fatalf("Failed to listen on %s: %v", addr, err)
	}
	defer listener.Close()

	log.Printf("Go Indexer RPC server listening on %s", addr)

	// Graceful shutdown.
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)

	go func() {
		<-sigCh
		log.Println("Shutting down...")
		listener.Close()
		os.Exit(0)
	}()

	for {
		conn, err := listener.Accept()
		if err != nil {
			log.Printf("Accept error: %v", err)
			return
		}
		go server.ServeConn(conn)
	}
}

func runOneShot(path string, concurrency int) {
	if path == "" {
		fmt.Fprintln(os.Stderr, "path is required for index mode")
		os.Exit(1)
	}

	indexer := NewGoIndexer()
	result, err := indexer.IndexWorkspace(path, concurrency)
	if err != nil {
		fmt.Fprintf(os.Stderr, "indexing failed: %v\n", err)
		os.Exit(1)
	}

	enc := json.NewEncoder(os.Stdout)
	enc.SetIndent("", "  ")
	if err := enc.Encode(result); err != nil {
		fmt.Fprintf(os.Stderr, "failed to encode result: %v\n", err)
		os.Exit(1)
	}
}
