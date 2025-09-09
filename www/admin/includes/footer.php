        </div>
    </div>
    <footer style="background: #343a40; color: white; text-align: center; padding: 20px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <p>&copy; 2025 ZeroAI Admin Portal - Zero Cost AI Workforce Management</p>
            <p style="font-size: 0.9em; opacity: 0.8;">
                System Status: <span style="color: #28a745;">Online</span> | 
                Version: v0.0.0.1.0.0 | 
                Active Agents: <?= count(glob('/app/src/crews/internal/*/')) ?> |
                Uptime: <?= gmdate('H:i:s', time() - (time() % 86400)) ?>
            </p>
        </div>
    </footer>
</body>
</html>