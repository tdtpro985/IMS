<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$activePage  = $currentPage;

function navItem(string $href, string $icon, string $label, string $page, string $active): string {
    $isActive = ($page === $active) ? 'active' : '';
    return <<<HTML
    <a href="{$href}" class="nav-item {$isActive}">
        <span class="nav-icon"><i class="fas {$icon}"></i></span>
        <span class="nav-label">{$label}</span>
    </a>
    HTML;
}
?>
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="sidebar-logo-wrap">
            <img src="/uploads/photos/logo-dark.png"
                 alt="TDT Powersteel"
                 class="sidebar-logo"
                 onerror="this.style.display='none';document.getElementById('sidebarLogoText').style.display='flex'">
            <!-- Fallback text logo if image missing -->
            <div id="sidebarLogoText" class="sidebar-logo-text" style="display:none">
                <span class="logo-tdt">TDT</span><span class="logo-power">POWER</span><span class="logo-steel">STEEL</span>
            </div>
            <span class="sidebar-system-name">Intern Management System</span>
        </div>
        <button class="sidebar-close d-md-none" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?= navItem('/dashboard.php',   'fa-th-large',      'Dashboard',         $activePage, 'dashboard') ?>
        <?= navItem('/departments.php', 'fa-building',      'Departments',       $activePage, 'departments') ?>
        <?= navItem('/interns.php',     'fa-users',         'Intern Management', $activePage, 'interns') ?>
        <?= navItem('/moa.php',         'fa-file-contract', 'MOA Management',    $activePage, 'moa') ?>
        <?= navItem('/policies.php',    'fa-shield-alt',    'Policy Hub',        $activePage, 'policies') ?>
        <?= navItem('/reports.php',     'fa-file-export',   'Reports & Export',  $activePage, 'reports') ?>
        <?= navItem('/audit.php',       'fa-history',       'Audit Trail',       $activePage, 'audit') ?>
        <?php if (isAdmin()): ?>
        <?= navItem('/settings.php',    'fa-cog',           'Settings',          $activePage, 'settings') ?>
        <?php endif; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars(currentUserName()) ?></span>
                <span class="user-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'] ?? '')) ?></span>
            </div>
        </div>
        <a href="/logout.php" class="nav-item nav-logout">
            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
