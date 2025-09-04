# 🐳 Enhanced Docker Tool Usage Guide

## Available Actions

### **1. Run Container**
```json
{
  "action": "run",
  "image": "nginx:latest",
  "ports": "8080:80",
  "volumes": "/tmp/project:/var/www/html",
  "environment": "ENV=production,DEBUG=false",
  "network": "zeroai-network",
  "detach": true
}
```

### **2. Execute in Container**
```json
{
  "action": "exec",
  "container": "zeroai-nginx-123456",
  "command": "ls -la /var/www/html"
}
```

### **3. Container Management**
```json
// Stop container
{
  "action": "stop",
  "container": "zeroai-nginx-123456"
}

// Remove container
{
  "action": "remove",
  "container": "zeroai-nginx-123456"
}

// List all containers
{
  "action": "list"
}

// Get container logs
{
  "action": "logs",
  "container": "zeroai-nginx-123456"
}
```

### **4. Network Management**
```json
// Create network
{
  "action": "network",
  "command": "create",
  "network": "zeroai-network"
}

// List networks
{
  "action": "network",
  "command": "list"
}
```

### **5. Volume Management**
```json
// Create volume
{
  "action": "volume",
  "command": "create",
  "volumes": "zeroai-data"
}

// List volumes
{
  "action": "volume",
  "command": "list"
}
```

## 🎯 Common Use Cases

### **Development Environment**
```json
{
  "action": "run",
  "image": "php:8.2-apache",
  "ports": "8080:80",
  "volumes": "/tmp/internal_crew/zeroai:/var/www/html",
  "environment": "APACHE_DOCUMENT_ROOT=/var/www/html"
}
```

### **Database Container**
```json
{
  "action": "run",
  "image": "mysql:8.0",
  "ports": "3306:3306",
  "environment": "MYSQL_ROOT_PASSWORD=zeroai123,MYSQL_DATABASE=testdb",
  "volumes": "mysql-data:/var/lib/mysql"
}
```

### **Testing Environment**
```json
{
  "action": "run",
  "image": "node:18-alpine",
  "volumes": "/tmp/project:/app",
  "command": "npm test",
  "detach": false
}
```

### **Microservice Deployment**
```json
{
  "action": "run",
  "image": "my-microservice:latest",
  "ports": "3000:3000",
  "network": "microservices",
  "environment": "NODE_ENV=production"
}
```
## Docker Compose
- This action allows agents to orchestrate multi-container applications using a docker-compose.yml file.
- Parameters:
-   "action": "compose"
-   "compose_file": Path to the compose file.
-   "command": The specific compose command ("up", "down", or "exec").
-   "service": (Required for "exec") The service name within the compose file.
-   "command": (Required for "exec") The command to run inside the service.

// Start a service defined in a compose file
{
  "action": "compose",
  "compose_file": "./docker-compose.yml",
  "command": "up"
}

// Stop a service defined in a compose file
{
  "action": "compose",
  "compose_file": "./docker-compose.yml",
  "command": "down"
}

// Execute a command in a compose service
{
  "action": "compose",
  "compose_file": "./docker-compose.yml",
  "command": "exec",
  "service": "ollama",
  "command": "ollama list"
}



## 🔧 Advanced Features

### **Container Networking**
- Containers automatically get unique names: `zeroai-{image}-{timestamp}`
- Use networks to connect containers
- Access containers via container names on same network

### **Volume Mounting**
- Mount host directories: `/host/path:/container/path`
- Use named volumes for persistence
- Share data between containers

### **Environment Variables**
- Pass multiple variables: `KEY1=value1,KEY2=value2`
- Configure application settings
- Database credentials and API keys

### **Port Mapping**
- Expose container ports: `host_port:container_port`
- Access services from host system
- Multiple port mappings supported

## ⚠️ Best Practices

1. **Always use detached mode** for long-running services
2. **Create networks** for multi-container applications
3. **Use volumes** for persistent data
4. **Clean up containers** when done to save resources
5. **Check logs** if containers fail to start
6. **Use meaningful names** for easier management

## 🚀 Agent Capabilities

Your agents can now:
- **Spin up isolated environments** for testing
- **Deploy microservices** dynamically
- **Create development sandboxes**
- **Manage container lifecycles**
- **Network containers together**
- **Persist data with volumes**
- **Monitor container health**