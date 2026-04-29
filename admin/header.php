<?php
// FIX #4: Set page title default FIRST, before any logic that could redirect,
// so $page_title is always defined before isAdmin() runs.
$page_title = $page_title ?? 'Admin';

// FIX #1: Ensure session is started before isAdmin() tries to read $_SESSION.
// Using session_status() prevents "session already started" warnings if
// db.php or another include already called session_start().
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
if (!isAdmin())
    redirect('../');
$adminUnreadNotifications = getUnreadNotificationCount($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — Admin</title>

    <!--
        FIX #2: Load stylesheets BEFORE the theme-detection script so that
        dark-mode CSS rules exist on the page before the class is applied.
        This eliminates the flash of unstyled/light content (FOUC).
    -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/dist/app.css">

    <script>
        // Theme detection runs after CSS is loaded — no more FOUC.
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark-mode');
        }
    </script>

    <script type="module" src="../assets/dist/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>

    <style>
        .container {
            max-width: none !important;
            width: 96% !important;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        /* FIX #5: Hide hamburger on desktop so it doesn't appear alongside the full nav */
        .mobile-toggle {
            display: none;
        }

        .nav-links {
            display: flex !important;
            gap: 0.8rem;
            align-items: center;
            justify-content: center;
            flex: 1;
            min-width: 0;
        }

        @media (max-width: 768px) {

            /* FIX #5: Show hamburger only on mobile */
            .mobile-toggle {
                display: block !important;
            }

            .nav-links {
                display: none !important;
                position: fixed;
                top: 58px;
                left: 0;
                right: 0;
                flex-direction: column;
                /* FIX #6: Use a CSS variable-aware background so it respects dark mode
                   instead of the hardcoded rgba(255,255,255,0.95) which was always white. */
                background: var(--nav-mobile-bg, rgba(255, 255, 255, 0.95));
                backdrop-filter: blur(24px);
                border-bottom: 1px solid var(--border);
                padding: 1rem 0;
                gap: 0;
                align-items: stretch;
                justify-content: flex-start;
                z-index: 999;
                max-height: calc(100vh - 58px);
                overflow-y: auto;
            }

            .nav-links.open {
                display: flex !important;
            }

            .nav-links a {
                display: block !important;
                padding: 0.75rem 1.5rem !important;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                text-align: left;
                min-width: auto !important;
                flex-shrink: 0 !important;
            }

            .admin-brand {
                font-size: 0.92rem !important;
                max-width: 45vw;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }

        /* FIX #6: Dark mode override for mobile nav background */
        .dark-mode {
            --nav-mobile-bg: rgba(20, 20, 25, 0.97);
        }

        /* FIX #7: Replace onmouseover/onmouseout inline handlers with CSS :hover
           so hover states work on all devices including touch where mouse events
           don't fire. Applied here; inline handlers removed from HTML below. */
        .nav-link {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 0.8rem;
            border-radius: 10px;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .nav-link:hover,
        .nav-link:focus {
            color: var(--text-main);
            background: rgba(0, 0, 0, 0.05);
        }

        .dark-mode .nav-link:hover,
        .dark-mode .nav-link:focus {
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link-cta {
            background: #7c3aed;
            color: #fff !important;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.5rem 1.2rem;
            border-radius: 980px;
            text-decoration: none;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.2s;
        }

        .nav-link-cta:hover,
        .nav-link-cta:focus {
            background: #6d28d9;
        }

        .stat-card-link {
            color: inherit;
            text-decoration: none;
            display: block;
        }

        .stat-card-link .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .stat-card-link:hover .stat-card,
        .stat-card-link:focus .stat-card {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.08);
            border-color: rgba(124, 58, 237, 0.22);
        }

        .dark-mode nav {
            background: rgba(20, 20, 25, 0.88) !important;
        }
    </style>
</head>

<body>
    <nav
        style="position:sticky; top:0; z-index:9999; backdrop-filter:saturate(180%) blur(24px); -webkit-backdrop-filter:saturate(180%) blur(24px); background:rgba(255,255,255,0.85); border-bottom:1px solid var(--border); transition: all 0.3s ease; padding: 0 4%;">
        <div
            style="display:flex; align-items:center; justify-content:space-between; height:58px; max-width:none; margin:0 auto; width:100%;">

            <a href="index.php" class="admin-brand"
                style="font-size:1.05rem; font-weight:800; color:var(--text-main); text-decoration:none; letter-spacing:-0.04em; display:flex; align-items:center; gap:6px; flex-shrink:0; transition:opacity 0.2s;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
                Admin Panel
            </a>

            <!--
                FIX #3: Added onclick handler to each nav link to close the mobile
                menu before navigating, so the menu doesn't stay open on back-navigation.
                FIX #7: Replaced all inline onmouseover/onmouseout with .nav-link CSS class.
            -->
            <div class="nav-links" id="main-nav">
                <a href="index.php" class="nav-link" onclick="closeNav()">Dashboard</a>
                <a href="users.php" class="nav-link" onclick="closeNav()">Users</a>
                <a href="products.php" class="nav-link" onclick="closeNav()">Moderation</a>
                <a href="messages.php" class="nav-link" onclick="closeNav()">Messages</a>
                <a href="index.php" class="nav-link" onclick="closeNav()">Alerts <span id="admin-notif-badge" style="<?= $adminUnreadNotifications > 0 ? '' : 'display:none;' ?>; margin-left:6px; background:#ff3b30; color:#fff; padding:2px 8px; border-radius:999px; font-size:0.72rem; font-weight:800;"><?= (int) $adminUnreadNotifications ?></span></a>
                <a href="audit.php" class="nav-link" onclick="closeNav()">Audit Log</a>
                <a href="analytics.php" class="nav-link" onclick="closeNav()">📊 Analytics</a>
                <a href="ads.php" class="nav-link" onclick="closeNav()">📢 Ads</a>
                <a href="settings.php" class="nav-link" onclick="closeNav()">Settings</a>
                <a href="../dashboard.php" class="nav-link" onclick="closeNav()">Seller Dashboard</a>
                <a href="../" class="nav-link" onclick="closeNav()">Main App</a>
                <a href="../logout.php" class="nav-link-cta" onclick="closeNav()">Sign Out</a>
            </div>

            <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                <div id="react-theme-toggle"></div>

                <!--
                    FIX #8: Added aria-label, aria-expanded, and aria-controls for
                    screen reader accessibility on the hamburger button.
                    FIX #5: Moved display toggling to CSS (.mobile-toggle) so the
                    button is hidden on desktop without inline style overrides.
                -->
                <button class="mobile-toggle" id="mobile-toggle-btn" aria-label="Toggle navigation menu"
                    aria-expanded="false" aria-controls="main-nav" onclick="toggleNav()"
                    style="color:var(--text-main); cursor:pointer; background:none; border:none; padding:6px; border-radius:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <line x1="3" y1="12" x2="21" y2="12" />
                        <line x1="3" y1="18" x2="21" y2="18" />
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <script>
        // FIX #3 + #8: Centralized nav toggle so both the button aria-expanded
        // state and the .open class are always kept in sync.
        const navEl = document.getElementById('main-nav');
        const btnEl = document.getElementById('mobile-toggle-btn');

        function toggleNav() {
            const isOpen = navEl.classList.toggle('open');
            btnEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }

        // Called by each nav link's onclick so the menu closes before navigating.
        function closeNav() {
            navEl.classList.remove('open');
            btnEl.setAttribute('aria-expanded', 'false');
        }
    </script>

    <div class="container">
