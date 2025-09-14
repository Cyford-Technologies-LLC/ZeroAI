#!/bin/bash

# ZeroAI Test Environment Manager
# Usage: ./test_runner.sh [option]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../../../../../.." && pwd)"

cd "$ROOT_DIR"

show_menu() {
    echo "========================================"
    echo "ZeroAI Test Environment Manager"
    echo "========================================"
    echo
    echo "Select an option:"
    echo "1. Start Test Environment (CPU)"
    echo "2. Start Test Environment (GPU)"
    echo "3. Stop Test Environment"
    echo "4. Rebuild and Start (CPU)"
    echo "5. Rebuild and Start (GPU)"
    echo "6. Prune and Fresh Build (CPU)"
    echo "7. Prune and Fresh Build (GPU)"
    echo "8. View Test Logs"
    echo "9. Exit"
    echo
}

show_urls() {
    echo
    echo "========================================"
    echo "Test Environment Started Successfully!"
    echo "========================================"
    echo
    echo "Test URLs:"
    echo "- API:           http://localhost:4949"
    echo "- Web Interface: http://localhost:444"
    echo "- Peer Service:  http://localhost:9090"
    echo "- Ollama:        http://localhost:12434"
    echo
    echo "Container Status:"
    docker ps --filter "name=zeroai_.*-test"
    echo
}

start_cpu() {
    echo "Starting Test Environment (CPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml up -d
    show_urls
}

start_gpu() {
    echo "Starting Test Environment (GPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
    show_urls
}

stop_env() {
    echo "Stopping Test Environment..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml down
    echo "Test environment stopped."
}

rebuild_cpu() {
    echo "Rebuilding and Starting (CPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml down
    docker-compose -f docker-compose.testing.yml build --no-cache
    docker-compose -f docker-compose.testing.yml up -d
    show_urls
}

rebuild_gpu() {
    echo "Rebuilding and Starting (GPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml down
    docker-compose -f docker-compose.testing.yml build --no-cache
    docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
    show_urls
}

prune_cpu() {
    echo "Pruning Docker and Fresh Build (CPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml down
    docker system prune -f
    docker volume prune -f
    docker-compose -f docker-compose.testing.yml build --no-cache
    docker-compose -f docker-compose.testing.yml up -d
    show_urls
}

prune_gpu() {
    echo "Pruning Docker and Fresh Build (GPU)..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml down
    docker system prune -f
    docker volume prune -f
    docker-compose -f docker-compose.testing.yml build --no-cache
    docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
    show_urls
}

view_logs() {
    echo "Showing Test Container Logs..."
    cd knowledge/internal_crew/cyford/zeroai/resources
    docker-compose -f docker-compose.testing.yml logs -f
}

# Handle command line arguments
if [ $# -eq 1 ]; then
    case $1 in
        "start-cpu"|"1") start_cpu; exit 0 ;;
        "start-gpu"|"2") start_gpu; exit 0 ;;
        "stop"|"3") stop_env; exit 0 ;;
        "rebuild-cpu"|"4") rebuild_cpu; exit 0 ;;
        "rebuild-gpu"|"5") rebuild_gpu; exit 0 ;;
        "prune-cpu"|"6") prune_cpu; exit 0 ;;
        "prune-gpu"|"7") prune_gpu; exit 0 ;;
        "logs"|"8") view_logs; exit 0 ;;
        *) echo "Invalid option: $1"; exit 1 ;;
    esac
fi

# Interactive menu
while true; do
    show_menu
    read -p "Enter choice (1-9): " choice
    
    case $choice in
        1) start_cpu ;;
        2) start_gpu ;;
        3) stop_env ;;
        4) rebuild_cpu ;;
        5) rebuild_gpu ;;
        6) prune_cpu ;;
        7) prune_gpu ;;
        8) view_logs ;;
        9) echo "Exiting..."; exit 0 ;;
        *) echo "Invalid choice. Please try again." ;;
    esac
done