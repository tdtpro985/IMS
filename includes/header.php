<?php
// $pageTitle should be set before including this file
$pageTitle = $pageTitle ?? 'IMS';
// $breadcrumbs = array of ['label' => '', 'url' => ''] — last item has no url
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — TDT Powersteel IMS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/main.css?v=1.0.2">
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-wrapper">
    <!-- Top bar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="topbar-toggle d-md-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <!-- Breadcrumbs -->
            <?php if (!empty($breadcrumbs)): ?>
            <nav class="breadcrumb-nav" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="/dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
                    </li>
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i === array_key_last($breadcrumbs)): ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['label']) ?></li>
                        <?php else: ?>
                        <li class="breadcrumb-item">
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php else: ?>
            <nav class="breadcrumb-nav" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item active"><i class="fas fa-th-large"></i> Dashboard</li>
                </ol>
            </nav>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <span class="topbar-user">
                <i class="fas fa-user-circle"></i>
                <?= htmlspecialchars(currentUserName()) ?>
            </span>
        </div>
    </header>

    <!-- Page content -->
    <main class="page-content">
