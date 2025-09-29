async function testConnection() {
    debugLog('Testing avatar service connection');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=test');
        const result = await response.json();

        debugLog('Connection test result', result);

        if (result.status === 'success') {
            showNotification('Connection successful: ' + result.message, 'success');
        } else {
            showNotification('Connection failed: ' + result.message, 'error');
        }
    } catch (error) {
        debugLog('Connection test error', { error: error.message });
        showNotification('Connection test failed: ' + error.message, 'error');
    }
}

async function pingAllServers() {
    debugLog('Pinging all servers');
    showNotification('Pinging servers...', 'info');

    try {
        // This would need to be implemented in the API
        await refreshPeerInfo();
        showNotification('Server ping completed', 'success');
    } catch (error) {
        showNotification('Server ping failed', 'error');
    }
}

async function benchmarkServers() {
    debugLog('Benchmarking servers');
    showNotification('Benchmarking servers... This may take a while', 'info');

    // This would need to be implemented as a special API endpoint
    // that tests generation speed on each available server
    setTimeout(() => {
        showNotification('Benchmark feature coming soon', 'info');
    }, 2000);
}

async function getServerInfo() {
    debugLog('Getting server connection info');

    try {
        const response = await fetch('/web/api/avatar_dual.php?action=server_info');
        const result = await response.json();

        debugLog('Server info received', result);
        displayServerInfo(result);

    } catch (error) {
        debugLog('Server info retrieval error', { error: error.message });
        showNotification('Failed to get server info', 'error');
    }
}

function displayServerInfo(info) {
    const container = document.getElementById('serverInfo');
    if (!container) return;

    if (!info || !info.current_peer) {
        container.innerHTML = '<div class="status-item status-error">No server info available</div>';
        return;
    }

    const peer = info.current_peer;
    const available = info.available_peers || [];

    let html = `
                <div class="status-item status-${peer.type === 'local' ? 'warning' : 'ok'}">
                    <strong>Current Avatar Server:</strong><br>
                    ${peer.name}<br>
                    Type: ${peer.type}<br>
                    ${peer.ip ? `IP: ${peer.ip}<br>` : ''}
                    ${peer.gpu_memory ? `GPU: ${peer.gpu_memory}GB<br>` : ''}
                    ${peer.memory ? `RAM: ${peer.memory}GB` : ''}
                </div>
            `;

    if (available.length > 0) {
        html += `
                    <div class="status-item status-ok">
                        <strong>Available Servers:</strong><br>
                        ${available.map(p => `${p.name} (${p.type})`).join('<br>')}
                    </div>
                `;
    }

    container.innerHTML = html;

    // Update current peer display
    const currentDisplay = document.getElementById('currentPeerDisplay');
    if (currentDisplay) {
        currentDisplay.innerHTML = `
                    <div class="peer-option selected">
                        <div>
                            <strong>${peer.name}</strong> (${peer.type})<br>
                            <small>Currently active server</small>
                        </div>
                    </div>
                `;
    }
}
function displayAvailablePeers(peers) {
    const container = document.getElementById('availablePeers');
    if (!container) return;

    if (!peers || peers.length === 0) {
        container.innerHTML = '<div class="peer-option">No peers available</div>';
        return;
    }

    let html = '';
    peers.forEach(peer => {
        const isSelected = selectedPeer === peer.id;
        const statusClass = peer.status === 'online' ? 'status-ok' : 'status-error';

        html += `
                    <div class="peer-option ${isSelected ? 'selected' : ''}" onclick="selectPeer('${peer.id}')">
                        <div style="flex: 1;">
                            <strong>${peer.name}</strong> (${peer.type})<br>
                            <small>
                                Status: <span class="${statusClass}">${peer.status}</span>
                                ${peer.gpu_memory_gb ? ` | GPU: ${peer.gpu_memory_gb}GB` : ''}
                                ${peer.memory_gb ? ` | RAM: ${peer.memory_gb}GB` : ''}
                                ${peer.score ? ` | Score: ${peer.score}` : ''}
                            </small>
                        </div>
                        <div>
                            ${isSelected ? '✓' : ''}
                        </div>
                    </div>
                `;
    });

    container.innerHTML = html;
}

function selectPeer(peerId) {
    selectedPeer = peerId;
    debugLog('Peer selected', { peerId });

    // Update UI
    const peerOptions = document.querySelectorAll('.peer-option');
    peerOptions.forEach(option => {
        option.classList.remove('selected');
    });

    // Find the clicked element and mark it as selected
    const clickedOption = event.target.closest('.peer-option');
    if (clickedOption) {
        clickedOption.classList.add('selected');
    }

    showNotification(`Selected peer: ${peerId}`, 'success');
}


async function refreshPeerInfo() {
    debugLog('Refreshing peer information');
    try {
        await getServerInfo();
        await loadAvailablePeers();
        showNotification('Peer information refreshed', 'success');
    } catch (error) {
        debugLog('Failed to refresh peer info', { error: error.message });
        showNotification('Failed to refresh peer information', 'error');
    }
}

async function loadAvailablePeers() {
    try {
        const response = await fetch('/web/api/avatar_dual.php?action=server_info');
        const result = await response.json();

        if (result.available_peers) {
            displayAvailablePeers(result.available_peers);
        }
    } catch (error) {
        debugLog('Failed to load available peers', { error: error.message });
    }
}
