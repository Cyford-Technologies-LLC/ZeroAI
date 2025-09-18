function addModel(category) {
    const container = document.getElementById(category + '_models');
    const div = document.createElement('div');
    div.className = 'mb-1';
    div.innerHTML = `
        <input type="text" name="${category}[]" placeholder="model:tag" style="width: 200px; padding: 2px;">
        <button type="button" onclick="this.parentElement.remove()" style="background: #dc3545; color: white; border: none; padding: 2px 6px; border-radius: 3px; cursor: pointer;">×</button>
    `;
    container.appendChild(div);
}

let installationJobId = null;
let installationInterval = null;

function installModelWithProgress(peerIp, modelName) {
    document.getElementById('installModal').style.display = 'block';
    document.getElementById('installModelName').textContent = modelName;
    document.getElementById('installPeerIp').textContent = peerIp;
    document.getElementById('installProgress').innerHTML = 'Starting installation...';
    
    fetch('model_install_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=start_install&peer_ip=${encodeURIComponent(peerIp)}&model_name=${encodeURIComponent(modelName)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            installationJobId = data.job_id;
            installationInterval = setInterval(checkInstallationStatus, 2000);
        } else {
            document.getElementById('installProgress').innerHTML = 'Error: ' + data.error;
        }
    })
    .catch(error => {
        document.getElementById('installProgress').innerHTML = 'Error: ' + error.message;
    });
}

function checkInstallationStatus() {
    if (!installationJobId) return;
    
    fetch(`model_install_api.php?action=get_status&job_id=${installationJobId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const status = data.status;
            document.getElementById('installProgress').innerHTML = '<pre>' + status.log + '</pre>';
            
            if (status.status === 'completed') {
                clearInterval(installationInterval);
                document.getElementById('installProgress').innerHTML += '<div style="color: green; font-weight: bold;">Installation completed successfully!</div>';
                setTimeout(() => {
                    closeInstallModal();
                    location.reload();
                }, 2000);
            } else if (status.status === 'error') {
                clearInterval(installationInterval);
                document.getElementById('installProgress').innerHTML += '<div style="color: red; font-weight: bold;">Installation failed!</div>';
            }
        }
    })
    .catch(error => {
        console.error('Status check error:', error);
    });
}

function closeInstallModal() {
    document.getElementById('installModal').style.display = 'none';
    if (installationInterval) {
        clearInterval(installationInterval);
        installationInterval = null;
    }
    installationJobId = null;
}