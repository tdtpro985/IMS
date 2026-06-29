<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/audit.php';
checkSession();

$db = getDB();

// ── POST handlers (Admin only) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_policy') {
        $category   = trim($_POST['category']    ?? '');
        $customCat  = trim($_POST['custom_category'] ?? '');
        $title      = trim($_POST['title']       ?? '');
        $content    = trim($_POST['content']     ?? '');
        $icon       = trim($_POST['icon']        ?? 'fa-file-alt');
        $sortOrder  = (int)($_POST['sort_order'] ?? 99);

        if ($category === '__custom__') $category = $customCat;

        if ($title && $category) {
            $stmt = $db->prepare(
                "INSERT INTO intern_policies (category, title, content, icon, sort_order, is_active)
                 VALUES (?,?,?,?,?,1)"
            );
            $stmt->bind_param('ssssi', $category, $title, $content, $icon, $sortOrder);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();
            logAudit('CREATE', 'Policies', $newId, "Policy '{$title}' added to '{$category}'.");
        }
        header('Location: /policies.php'); exit;
    }

    if ($action === 'edit_policy') {
        $id        = (int)($_POST['policy_id']   ?? 0);
        $category  = trim($_POST['category']     ?? '');
        $customCat = trim($_POST['custom_category'] ?? '');
        $title     = trim($_POST['title']        ?? '');
        $content   = trim($_POST['content']      ?? '');
        $icon      = trim($_POST['icon']         ?? 'fa-file-alt');
        $sortOrder = (int)($_POST['sort_order']  ?? 99);

        if ($category === '__custom__') $category = $customCat;

        if ($id && $title && $category) {
            $stmt = $db->prepare(
                "UPDATE intern_policies SET category=?, title=?, content=?, icon=?, sort_order=? WHERE id=?"
            );
            $stmt->bind_param('ssssii', $category, $title, $content, $icon, $sortOrder, $id);
            $stmt->execute();
            $stmt->close();
            logAudit('UPDATE', 'Policies', $id, "Policy '{$title}' updated.");
        }
        header('Location: /policies.php'); exit;
    }

    if ($action === 'delete_policy') {
        $id = (int)($_POST['policy_id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE intern_policies SET is_active=0 WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAudit('ARCHIVE', 'Policies', $id, "Policy #{$id} deactivated.");
        }
        header('Location: /policies.php'); exit;
    }

    if ($action === 'restore_policy') {
        $id = (int)($_POST['policy_id'] ?? 0);
        if ($id) {
            $stmt = $db->prepare("UPDATE intern_policies SET is_active=1 WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            logAudit('RESTORE', 'Policies', $id, "Policy #{$id} restored.");
        }
        header('Location: /policies.php'); exit;
    }
}

// ── Fetch ──────────────────────────────────────────────────────────────────
$showInactive = isset($_GET['inactive']);

$policies = $db->query(
    "SELECT * FROM intern_policies WHERE is_active=" . ($showInactive ? 0 : 1) . " ORDER BY category ASC, sort_order ASC"
)->fetch_all(MYSQLI_ASSOC);

$grouped = [];
foreach ($policies as $p) {
    $grouped[$p['category']][] = $p;
}

// All existing categories for dropdown
$allCats = $db->query(
    "SELECT DISTINCT category FROM intern_policies ORDER BY category ASC"
)->fetch_all(MYSQLI_ASSOC);
$catList = array_column($allCats, 'category');

$pageTitle   = 'Intern Policy Hub';
$breadcrumbs = [['label' => 'Intern Policy Hub', 'url' => '']];
require_once __DIR__ . '/includes/header.php';

// Common FA icons for the picker
$iconOptions = [
    'fa-file-alt','fa-file-contract','fa-handshake','fa-calendar-check',
    'fa-tshirt','fa-shield-alt','fa-chalkboard-teacher','fa-ban',
    'fa-clock','fa-user-tie','fa-id-badge','fa-exclamation-triangle',
    'fa-check-circle','fa-times-circle','fa-info-circle','fa-lock',
    'fa-key','fa-bullhorn','fa-book','fa-gavel','fa-star','fa-heart',
    'fa-thumbs-up','fa-users','fa-building','fa-briefcase',
];

$catColors = [
    'Traineeship Terms'    => 'var(--info)',
    'Attendance & Schedule'=> 'var(--orange)',
    'Dress Code'           => '#8B5CF6',
    'Conduct & Performance'=> 'var(--danger)',
    'Trainer & Supervision'=> 'var(--success)',
    'Compensation'         => 'var(--gray-mid)',
];
$catIcons = [
    'Traineeship Terms'    => 'fa-handshake',
    'Attendance & Schedule'=> 'fa-calendar-check',
    'Dress Code'           => 'fa-tshirt',
    'Conduct & Performance'=> 'fa-shield-alt',
    'Trainer & Supervision'=> 'fa-chalkboard-teacher',
    'Compensation'         => 'fa-ban',
];
?>

<div class="page-header d-flex justify-between align-center" style="flex-wrap:wrap;gap:12px">
    <div>
        <h1>Intern Policy Hub</h1>
        <p>Company policies and guidelines for TDT Powersteel OJT/Intern Program.</p>
    </div>
    <div class="d-flex gap-8">
        <?php if (isAdmin()): ?>
        <a href="/policies.php?<?= $showInactive ? '' : 'inactive=1' ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-archive"></i> <?= $showInactive ? 'Active Policies' : 'Inactive' ?>
        </a>
        <button class="btn btn-primary btn-sm" onclick="openModal('addPolicyModal')">
            <i class="fas fa-plus"></i> Add Policy
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Notice banner -->
<div style="background:rgba(232,98,26,.08);border:1.5px solid rgba(232,98,26,.25);border-radius:10px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px">
    <i class="fas fa-info-circle" style="color:var(--orange);font-size:20px;flex-shrink:0"></i>
    <div>
        <div style="font-weight:600;font-size:13.5px;color:var(--text-main)">TDT Powersteel Corporation — On-The-Job Training Agreement</div>
        <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px">
            All interns are expected to be familiar with and abide by the following policies.
        </div>
    </div>
</div>

<?php if (empty($grouped)): ?>
<div class="card"><div class="card-body">
    <div class="empty-state">
        <i class="fas fa-shield-alt"></i>
        <p><?= $showInactive ? 'No inactive policies.' : 'No policies yet. Click "Add Policy" to get started.' ?></p>
    </div>
</div></div>
<?php endif; ?>

<?php foreach ($grouped as $category => $items):
    $catColor = $catColors[$category] ?? 'var(--orange)';
    $catIcon  = $catIcons[$category]  ?? 'fa-file-alt';
?>
<div class="card mb-20">
    <div class="card-header" style="background:var(--gray-light)">
        <span class="card-title" style="display:flex;align-items:center;gap:10px">
            <span style="width:32px;height:32px;border-radius:8px;background:<?= $catColor ?>;
                         display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="fas <?= $catIcon ?>" style="color:#fff;font-size:14px"></i>
            </span>
            <?= htmlspecialchars($category) ?>
            <span class="text-muted fs-12">(<?= count($items) ?>)</span>
        </span>
    </div>
    <div class="card-body" style="padding:0">
        <?php foreach ($items as $i => $policy): ?>
        <div style="padding:16px 22px;<?= $i < count($items)-1 ? 'border-bottom:1px solid var(--gray-border)' : '' ?>;display:flex;align-items:flex-start;gap:14px">
            <div style="width:36px;height:36px;border-radius:8px;
                        background:<?= $catColor ?>22;color:<?= $catColor ?>;
                        display:flex;align-items:center;justify-content:center;
                        flex-shrink:0;margin-top:2px">
                <i class="fas <?= htmlspecialchars($policy['icon']) ?>" style="font-size:14px"></i>
            </div>
            <div style="flex:1">
                <div style="font-size:14px;font-weight:600;color:var(--text-main);margin-bottom:4px">
                    <?= htmlspecialchars($policy['title']) ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);line-height:1.7">
                    <?= nl2br(htmlspecialchars($policy['content'])) ?>
                </div>
            </div>
            <?php if (isAdmin()): ?>
            <div class="d-flex gap-6" style="flex-shrink:0;margin-top:2px">
                <button class="btn btn-icon btn-sm" title="Edit"
                    onclick='editPolicy(<?= htmlspecialchars(json_encode($policy)) ?>)'>
                    <i class="fas fa-pen" style="color:var(--orange)"></i>
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Deactivate this policy?')">
                    <input type="hidden" name="action"    value="<?= $showInactive ? 'restore_policy' : 'delete_policy' ?>">
                    <input type="hidden" name="policy_id" value="<?= $policy['id'] ?>">
                    <button type="submit" class="btn btn-icon btn-sm" title="<?= $showInactive ? 'Restore' : 'Deactivate' ?>">
                        <i class="fas <?= $showInactive ? 'fa-undo' : 'fa-trash' ?>"
                           style="color:<?= $showInactive ? 'var(--success)' : 'var(--danger)' ?>"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px">
    <i class="fas fa-lock" style="margin-right:6px"></i>
    Policies sourced from the official TDT Powersteel On-The-Job Training Agreement.
    For questions, contact HR &amp; Admin.
</div>

<?php if (isAdmin()): ?>
<!-- ═══ Add Policy Modal ═══ -->
<div class="modal-overlay" id="addPolicyModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-plus text-orange"></i> Add Policy</span>
            <button class="modal-close" onclick="closeModal('addPolicyModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_policy">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <select name="category" id="addCatSelect" class="form-control" onchange="toggleCustomCat('add')">
                            <?php foreach ($catList as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="__custom__">+ New Category</option>
                        </select>
                    </div>
                    <div class="form-group" id="addCustomCatGroup" style="flex:1;display:none">
                        <label class="form-label">New Category Name <span class="required">*</span></label>
                        <input type="text" name="custom_category" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group" style="max-width:80px">
                        <label class="form-label">Order</label>
                        <input type="number" name="sort_order" class="form-control" value="99" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required maxlength="200">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="4" maxlength="2000"
                              style="resize:vertical"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <select name="icon" id="addIconSelect" class="form-control" style="max-width:220px"
                                onchange="updateIconPreview('add')">
                            <?php foreach ($iconOptions as $ico): ?>
                            <option value="<?= $ico ?>"><?= $ico ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="addIconPreview"
                             style="width:40px;height:40px;border-radius:8px;background:var(--orange-light);
                                    color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:18px">
                            <i class="fas fa-file-alt" id="addIconPreviewI"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPolicyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Policy</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Edit Policy Modal ═══ -->
<div class="modal-overlay" id="editPolicyModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-pen text-orange"></i> Edit Policy</span>
            <button class="modal-close" onclick="closeModal('editPolicyModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"    value="edit_policy">
            <input type="hidden" name="policy_id" id="editPolicyId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Category <span class="required">*</span></label>
                        <select name="category" id="editCatSelect" class="form-control" onchange="toggleCustomCat('edit')">
                            <?php foreach ($catList as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="__custom__">+ New Category</option>
                        </select>
                    </div>
                    <div class="form-group" id="editCustomCatGroup" style="flex:1;display:none">
                        <label class="form-label">New Category Name</label>
                        <input type="text" name="custom_category" id="editCustomCatInput" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group" style="max-width:80px">
                        <label class="form-label">Order</label>
                        <input type="number" name="sort_order" id="editSortOrder" class="form-control" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Title <span class="required">*</span></label>
                    <input type="text" name="title" id="editTitle" class="form-control" required maxlength="200">
                </div>
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" id="editContent" class="form-control" rows="4"
                              maxlength="2000" style="resize:vertical"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <select name="icon" id="editIconSelect" class="form-control" style="max-width:220px"
                                onchange="updateIconPreview('edit')">
                            <?php foreach ($iconOptions as $ico): ?>
                            <option value="<?= $ico ?>"><?= $ico ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="editIconPreview"
                             style="width:40px;height:40px;border-radius:8px;background:var(--orange-light);
                                    color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:18px">
                            <i class="fas fa-file-alt" id="editIconPreviewI"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPolicyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCustomCat(prefix) {
    const sel = document.getElementById(prefix + 'CatSelect');
    const grp = document.getElementById(prefix + 'CustomCatGroup');
    grp.style.display = sel.value === '__custom__' ? 'flex' : 'none';
}

function updateIconPreview(prefix) {
    const sel  = document.getElementById(prefix + 'IconSelect');
    const icon = document.getElementById(prefix + 'IconPreviewI');
    icon.className = 'fas ' + sel.value;
}

function editPolicy(p) {
    document.getElementById('editPolicyId').value   = p.id;
    document.getElementById('editTitle').value      = p.title;
    document.getElementById('editContent').value    = p.content || '';
    document.getElementById('editSortOrder').value  = p.sort_order || 99;

    const catSel = document.getElementById('editCatSelect');
    let catFound = false;
    for (let opt of catSel.options) {
        if (opt.value === p.category) { opt.selected = true; catFound = true; break; }
    }
    if (!catFound) {
        catSel.value = '__custom__';
        document.getElementById('editCustomCatGroup').style.display = 'flex';
        document.getElementById('editCustomCatInput').value = p.category;
    } else {
        document.getElementById('editCustomCatGroup').style.display = 'none';
    }

    const iconSel = document.getElementById('editIconSelect');
    let iconFound = false;
    for (let opt of iconSel.options) {
        if (opt.value === p.icon) { opt.selected = true; iconFound = true; break; }
    }
    document.getElementById('editIconPreviewI').className = 'fas ' + (iconFound ? p.icon : 'fa-file-alt');

    openModal('editPolicyModal');
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
