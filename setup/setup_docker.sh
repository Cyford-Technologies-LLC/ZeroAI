#!/bin/bash
# ZeroAI Docker Setup Script
# This script sets up Docker and required packages on Debian-based and RPM-based systems.

set -e

# --- Helper Functions ---

# Function to get the package manager and OS info
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_NAME=$ID
    else
        echo "❌ Cannot detect OS. Exiting."
        exit 1
    fi

    if [[ "$OS_NAME" == "ubuntu" || "$OS_NAME" == "debian" ]]; then
        PKG_MANAGER="apt"
        echo "🖥️  Detected OS: $PRETTY_NAME"
    elif [[ "$OS_NAME" == "rocky" || "$OS_NAME" == "centos" || "$OS_NAME" == "fedora" ]]; then
        PKG_MANAGER="dnf"
        echo "🖥️  Detected OS: $PRETTY_NAME"
    else
        echo "❌ Unsupported OS: $OS_NAME"
        exit 1
    fi
}

# Function to check for dependencies and install if missing
check_and_install_dependencies() {
    echo "📦 Checking and installing system dependencies..."
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt-get update
        sudo apt-get install -y ca-certificates curl gnupg git
    elif [[ "$PKG_MANAGER" == "dnf" ]]; then
        sudo dnf check-update
        sudo dnf install -y dnf-plugins-core curl gnupg git
    fi
}

# Function to install Docker from official repository
install_docker() {
    echo "🐳 Installing Docker Engine and Docker Compose..."
    if ! command -v docker &> /dev/null; then
        if [[ "$PKG_MANAGER" == "apt" ]]; then
            # Add Docker's official GPG key
            sudo install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
            sudo chmod a+r /etc/apt/keyrings/docker.gpg
            # Add the Docker repository to Apt sources
            echo \
              "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
              "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
              sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
            sudo apt-get update
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        elif [[ "$PKG_MANAGER" == "dnf" ]]; then
            # Set up the repository for Docker
            sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
            sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            sudo systemctl start docker
            sudo systemctl enable docker
        fi
    else
        echo "✅ Docker already installed."
    fi
}

# Function to add user to docker group
add_user_to_docker_group() {
    if ! groups "$USER" | grep -q 'docker'; then
        echo "🧑 Adding user '$USER' to the 'docker' group..."
        sudo usermod -aG docker "$USER"
        echo "🎉 Please log out and log back in for group changes to take effect."
    else
        echo "✅ User '$USER' is already in the 'docker' group."
    fi
}


# --- Main Execution Flow ---

echo "🚀 ZeroAI Dockerized Setup"
echo "=========================="

detect_os
check_and_install_dependencies
install_docker
add_user_to_docker_group

echo ""
echo "🎉 Setup complete! Next steps:"
echo "1. Clone the ZeroAI repository: git clone https://github.com/Cyford-Technologies-LLC/ZeroAI.git"
echo "2. Navigate into the directory: cd ZeroAI"
echo "3. Run the Docker containers: docker-compose up --build"
echo "4. The API will be available at http://localhost:3939"
echo ""

