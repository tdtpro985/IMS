<?php
// Profile Tab — all POST handling is done in intern_workspace.php
// $intern, $internId, $db are available from parent scope

$profileError   = $_SESSION['profile_error']   ?? null; unset($_SESSION['profile_error']);
$profileSuccess = $_SESSION['profile_success'] ?? null; unset($_SESSION['profile_success']);
$isNew          = isset($_GET['new']) && $_GET['new'] === '1';

function v(array $arr, string $key): string {
    return htmlspecialchars($arr[$key] ?? '');
}
?>

<?php if ($isNew): ?>
<div style="margin-bottom:16px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.3);color:#3B82F6;border-radius:8px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:13px">
    <i class="fas fa-info-circle" style="font-size:16px;flex-shrink:0"></i>
    <div>
        <strong>Intern created!</strong> Complete the profile below and click <strong>Save Changes</strong> when done.
        All fields are optional except First Name and Last Name.
    </div>
</div>
<?php endif; ?>

<?php if ($profileError): ?>
<div style="margin-bottom:16px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);color:var(--danger);border-radius:8px;padding:11px 14px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($profileError) ?>
</div>
<?php endif; ?>
<?php if ($profileSuccess): ?>
<div style="margin-bottom:16px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:var(--success);border-radius:8px;padding:11px 14px;display:flex;align-items:center;gap:8px;font-size:13px">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($profileSuccess) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="profileForm">
<input type="hidden" name="action" value="update_profile">

<div style="display:grid;grid-template-columns:200px 1fr;gap:20px;align-items:start">

    <!-- Photo -->
    <div class="card">
        <div class="card-body" style="text-align:center;padding:22px 16px">
            <div id="photoContainer"
                 style="width:110px;height:110px;border-radius:50%;margin:0 auto 14px;
                        overflow:hidden;border:3px solid var(--orange);
                        box-shadow:var(--neon-glow-sm);
                        background:var(--orange-light);
                        display:flex;align-items:center;justify-content:center;
                        font-size:38px;font-weight:700;color:var(--orange)">
                <?php if ($intern['profile_photo']): ?>
                    <img src="/uploads/photos/<?= v($intern,'profile_photo') ?>"
                         alt="Photo" id="photoPreview"
                         style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                    <span id="photoInitials">
                        <?= strtoupper(substr($intern['first_name'],0,1).substr($intern['last_name'],0,1)) ?>
                    </span>
                    <img src="" alt="" id="photoPreview"
                         style="display:none;width:100%;height:100%;object-fit:cover">
                <?php endif; ?>
            </div>
            <label class="btn btn-secondary btn-sm w-100" style="cursor:pointer;justify-content:center;margin-bottom:6px">
                <i class="fas fa-camera"></i> Change Photo
                <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png"
                       style="display:none" onchange="previewPhoto(this)">
            </label>
            <p class="text-muted" style="font-size:11px">JPEG or PNG · max 5 MB</p>
        </div>
    </div>

    <!-- Personal info -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-user text-orange"></i> Personal Information</span></div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" required maxlength="80"
                           value="<?= v($intern,'first_name') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" required maxlength="80"
                           value="<?= v($intern,'last_name') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" maxlength="80"
                           value="<?= v($intern,'middle_name') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">— Select —</option>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($intern['gender']===$g)?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="150"
                           value="<?= v($intern,'email') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" maxlength="30"
                           value="<?= v($intern,'phone') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Birthdate</label>
                    <input type="date" name="birthdate" class="form-control"
                           value="<?= v($intern,'birthdate') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Civil Status</label>
                    <select name="civil_status" class="form-control">
                        <option value="">— Select —</option>
                        <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
                        <option value="<?= $cs ?>" <?= ($intern['civil_status']??'')===$cs?'selected':'' ?>><?= $cs ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nationality</label>
                    <input type="text" name="nationality" class="form-control" maxlength="60"
                           value="<?= v($intern,'nationality') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" maxlength="255"
                           value="<?= v($intern,'address') ?>">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Academic -->
<div class="card mt-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-graduation-cap text-orange"></i> Academic Information</span></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">School / University</label>
                <input type="text" name="school" class="form-control" maxlength="150"
                       value="<?= v($intern,'school') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Course / Program</label>
                <input type="text" name="course" class="form-control" maxlength="150"
                       value="<?= v($intern,'course') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Year Level</label>
                <select name="year_level" class="form-control">
                    <option value="">— Select —</option>
                    <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $yl): ?>
                    <option value="<?= $yl ?>" <?= ($intern['year_level']??'')===$yl?'selected':'' ?>><?= $yl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">School Address</label>
                <input type="text" name="school_address" class="form-control" maxlength="255"
                       value="<?= v($intern,'school_address') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Emergency Contact -->
<div class="card mt-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-phone-alt text-orange"></i> Emergency Contact</span></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Guardian / Parent Name</label>
                <input type="text" name="guardian_name" class="form-control" maxlength="100"
                       value="<?= v($intern,'guardian_name') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Guardian Contact Number</label>
                <input type="text" name="guardian_contact" class="form-control" maxlength="30"
                       value="<?= v($intern,'guardian_contact') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Internship Details -->
<div class="card mt-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-briefcase text-orange"></i> Internship Details</span></div>
    <div class="card-body">
        <?php
        // Fetch all departments for the dropdown
        $allDepts = $db->query("SELECT id, name FROM departments ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="department_id" class="form-control">
                    <?php foreach ($allDepts as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (int)$intern['department_id'] === (int)$d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($d['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-error" style="color:var(--text-muted);font-size:11px">
                    Changing department will move the intern's record. DTR and requirements are not affected.
                </span>
            </div>
            <div class="form-group">
                <label class="form-label">Supervisor</label>
                <input type="text" name="supervisor" class="form-control" maxlength="100"
                       value="<?= v($intern,'supervisor') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control"
                       value="<?= v($intern,'start_date') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control"
                       value="<?= v($intern,'end_date') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Required Hours</label>
                <input type="number" name="required_hours" class="form-control" min="1" step="0.5"
                       value="<?= v($intern,'required_hours') ?>">
            </div>
        </div>
    </div>
</div>

<!-- Action buttons -->
<div class="d-flex gap-8 mt-16" style="justify-content:space-between;flex-wrap:wrap">
    <div class="d-flex gap-8">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Changes
        </button>
        <a href="/api/export_requirements.php?intern_id=<?= $internId ?>" target="_blank" class="btn btn-secondary">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
    </div>
    <?php if ($intern['status'] === 'Active'): ?>
    <button type="button" class="btn btn-danger" onclick="openModal('archiveProfileModal')">
        <i class="fas fa-archive"></i> Archive Intern
    </button>
    <?php endif; ?>
</div>
</form>

<!-- Archive Confirm Modal -->
<div class="modal-overlay" id="archiveProfileModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Archive Intern</span>
            <button class="modal-close" onclick="closeModal('archiveProfileModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="archive_from_profile">
            <div class="modal-body">
                <p>Archive <strong><?= htmlspecialchars($intern['first_name'].' '.$intern['last_name']) ?></strong>?</p>
                <p class="text-muted mt-8" style="font-size:13px">The record will be preserved and can be restored later.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('archiveProfileModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-archive"></i> Archive</button>
            </div>
        </form>
    </div>
</div>

<script>
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const preview  = document.getElementById('photoPreview');
        const initials = document.getElementById('photoInitials');
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (initials) initials.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
