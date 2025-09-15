<?php

namespace ZeroAI\Admin;

class DashboardAdmin extends BaseAdmin {
    
    protected function handleRequest() {
        // No form handling needed for dashboard
    }
    
    protected function renderContent() {
        ?>
        <h1>System Overview</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="cpu">0%</div>
                <div class="stat-label">CPU Usage</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="memory">0%</div>
                <div class="stat-label">Memory Usage</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="disk">0</div>
                <div class="stat-label">Disk I/O</div>
            </div>
        </div>
        
        <div class="card">
            <h3>System Status</h3>
            <p>🟢 Database: Connected</p>
            <p>🟢 Portal: Active</p>
            <p>👤 User: <?= $_SESSION['admin_user'] ?></p>
        </div>
        
        <script>
        function updateStats() {
            fetch('/api/system_stats.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('cpu').textContent = data.cpu + '%';
                    document.getElementById('memory').textContent = data.memory + '%';
                    document.getElementById('disk').textContent = data.disk_reads + data.disk_writes;
                })
                .catch(() => {});
        }
        setInterval(updateStats, 2000);
        updateStats();
        </script>
        <?php
    }
}


