<?php $currentPage=basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar">
  <div class="brand"><div class="brand-mark">MWH</div><div class="brand-copy"><strong>Maan World</strong><span>Local Admin</span></div></div>
  <nav class="nav">
    <div class="nav-label">MWH Local</div>
    <a class="<?=$currentPage==='dashboard.php'?'active':''?>" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
    <a class="<?=$currentPage==='contractors.php'?'active':''?>" href="contractors.php"><i class="bi bi-briefcase-fill"></i><span>Contractors</span></a>
    <a class="<?=$currentPage==='staff.php'?'active':''?>" href="staff.php"><i class="bi bi-person-badge-fill"></i><span>Staff / Chefs</span></a>
    <a class="<?=$currentPage==='requirements.php'?'active':''?>" href="requirements.php"><i class="bi bi-clipboard-plus-fill"></i><span>Requirements</span></a>
    <a class="<?=$currentPage==='applications.php'?'active':''?>" href="applications.php"><i class="bi bi-file-earmark-check-fill"></i><span>Applications</span></a>
    <a class="<?=$currentPage==='assignments.php'?'active':''?>" href="assignments.php"><i class="bi bi-calendar2-check-fill"></i><span>Assignments</span></a>
    <a class="<?=$currentPage==='attendance.php'?'active':''?>" href="attendance.php"><i class="bi bi-clock-history"></i><span>Attendance</span></a>
    <a class="<?=$currentPage==='payments.php'?'active':''?>" href="payments.php"><i class="bi bi-wallet2"></i><span>Payments</span></a>
    <div class="nav-label">Configuration</div>
    <a class="<?=$currentPage==='categories.php'?'active':''?>" href="categories.php"><i class="bi bi-tags-fill"></i><span>Categories</span></a>
    <a class="<?=$currentPage==='job_roles.php'?'active':''?>" href="job_roles.php"><i class="bi bi-diagram-3-fill"></i><span>Job Roles</span></a>
    <div class="nav-label">Account</div>
    <a class="<?=$currentPage==='change_password.php'?'active':''?>" href="change_password.php"><i class="bi bi-shield-lock-fill"></i><span>Change Password</span></a>
  </nav>
  <div class="sidebar-bottom"><div class="admin-card"><div class="admin-avatar"><?=htmlspecialchars(strtoupper(substr($admin['name'],0,1)),ENT_QUOTES,'UTF-8')?></div><div class="admin-copy"><strong><?=htmlspecialchars($admin['name'],ENT_QUOTES,'UTF-8')?></strong><span><?=htmlspecialchars($admin['email'],ENT_QUOTES,'UTF-8')?></span></div></div><a class="logout" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</aside>
