    </div>
    <footer style="background: #1e7e34; color: white; text-align: center; padding: 20px; margin-top: 40px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <p>&copy; 2025 ZeroAI Frontend Portal - Project Management Interface</p>
            <p style="font-size: 0.9em; opacity: 0.8;">
                User: <?= $_SESSION['web_user'] ?? 'Guest' ?> | 
                Active Projects: 0 | 
                <a href="/admin" style="color: #ffc107;">Admin Portal</a>
            </p>
        </div>
    </footer>
</body>
</html>