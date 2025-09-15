<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();

$page_title = "Peer Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">ZeroAI Peer Network</h5>
                    <button class="btn btn-primary btn-sm" onclick="showAddPeerModal()">
                        <i class="fas fa-plus"></i> Add Peer
                    </button>
                </div>
                <div class="card-body">
                    <div id="peer-list" class="table-responsive">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Peer Modal -->
<div class="modal fade" id="addPeerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Peer</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="addPeerForm">
                    <div class="form-group">
                        <label>Peer URL</label>
                        <input type="url" class="form-control" id="peerUrl" placeholder="http://192.168.1.100:8080" required>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" class="form-control" id="peerName" placeholder="Optional display name">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="peerEnabled" checked>
                        <label class="form-check-label" for="peerEnabled">Enabled</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addPeer()">Add Peer</button>
            </div>
        </div>
    </div>
</div>

<script>
let peers = [];

function loadPeers() {
    fetch('/admin/peers_api.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                peers = data.peers;
                renderPeerTable();
            } else {
                document.getElementById('peer-list').innerHTML = 
                    '<div class="alert alert-warning">Failed to load peers: ' + (data.error || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('peer-list').innerHTML = 
                '<div class="alert alert-danger">Error loading peers: ' + error.message + '</div>';
        });
}

function renderPeerTable() {
    if (peers.length === 0) {
        document.getElementById('peer-list').innerHTML = 
            '<div class="alert alert-info">No peers configured. Add your first peer to get started.</div>';
        return;
    }

    let html = `
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Response Time</th>
                    <th>Last Check</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;

    peers.forEach((peer, index) => {
        const statusBadge = peer.status === 'online' ? 'success' : 
                           peer.status === 'offline' ? 'danger' : 'warning';
        const responseTime = peer.response_time ? (peer.response_time * 1000).toFixed(0) + 'ms' : '-';
        
        html += `
            <tr>
                <td>${peer.name || peer.url}</td>
                <td><code>${peer.url}</code></td>
                <td><span class="badge badge-${statusBadge}">${peer.status}</span></td>
                <td>${responseTime}</td>
                <td>${peer.last_check ? new Date(peer.last_check).toLocaleString() : '-'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="removePeer(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    document.getElementById('peer-list').innerHTML = html;
}

function showAddPeerModal() {
    $('#addPeerModal').modal('show');
}

function addPeer() {
    const url = document.getElementById('peerUrl').value;
    const name = document.getElementById('peerName').value;
    const enabled = document.getElementById('peerEnabled').checked;

    if (!url) {
        alert('Please enter a peer URL');
        return;
    }

    fetch('http://localhost:3939/peers', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            url: url,
            name: name,
            enabled: enabled
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#addPeerModal').modal('hide');
            document.getElementById('addPeerForm').reset();
            loadPeers();
        } else {
            alert('Failed to add peer: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error adding peer: ' + error.message);
    });
}

function removePeer(index) {
    if (!confirm('Are you sure you want to remove this peer?')) {
        return;
    }

    fetch(`http://localhost:3939/peers/${index}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPeers();
        } else {
            alert('Failed to remove peer: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error removing peer: ' + error.message);
    });
}

// Auto-refresh every 30 seconds
setInterval(loadPeers, 30000);

// Initial load
loadPeers();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


