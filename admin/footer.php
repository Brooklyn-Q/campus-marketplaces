    </div> <!-- Closes .container opened in header.php -->

    <footer style="margin-top: 5rem; padding: 2.5rem 0; border-top: 1px solid var(--border); background: var(--bg-main);">
        <div style="width: 96%; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">
            <div>
                &copy; <?= date('Y') ?> <span style="color: var(--text-main); font-weight: 700;">Campus Marketplace</span> Admin
            </div>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <span style="background: rgba(0, 113, 227, 0.1); color: #0071e3; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.75rem;">
                    v2.5.0-PRO
                </span>
                <span>System Status: <span style="color: #28a745;">● Online</span></span>
            </div>
        </div>
    </footer>

    <!-- Global Admin Scripts -->
    <script>
        // Auto-refresh logic or global notifications could be initialized here
        console.log("Admin Panel Loaded - Version 2.5.0");
    </script>
</body>
</html>
