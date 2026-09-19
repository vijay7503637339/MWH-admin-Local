<?php
require_once __DIR__ . '/auth.php';
requireAdmin();
$admin = currentAdmin();
$pageTitle = $pageTitle ?? 'MWH Local';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#0b1f4d">
<title><?=htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8')?> | MWH Local</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{--navy:#0b1f4d;--blue:#1e5eff;--ink:#172033;--muted:#7b879b;--line:#e6ebf3;--canvas:#f5f7fb}
*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font-family:Inter,system-ui,sans-serif;font-size:13px}.app{display:flex;min-height:100vh}.sidebar{width:254px;background:linear-gradient(180deg,#0b1f4d 0%,#102b63 100%);color:#fff;position:fixed;inset:0 auto 0 0;z-index:100;display:flex;flex-direction:column}.brand{padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;gap:11px;align-items:center}.brand-mark{width:44px;height:44px;border-radius:13px;background:#ffffff18;border:1px solid #ffffff1f;display:grid;place-items:center;font-weight:900;letter-spacing:.06em}.brand-copy strong{display:block;font-size:14px}.brand-copy span{display:block;color:#b9c8e9;font-size:10px;margin-top:2px}.nav{padding:15px 11px;overflow:auto;flex:1}.nav-label{color:#8596bb;font-size:9px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;padding:13px 12px 7px}.nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:10px;color:#d5def2;text-decoration:none;font-size:12px;font-weight:650;margin:2px 0}.nav a:hover,.nav a.active{background:#ffffff12;color:#fff}.nav a.active{box-shadow:inset 3px 0 0 #70a0ff}.nav i{width:19px;text-align:center;font-size:15px}.sidebar-bottom{padding:13px 12px;border-top:1px solid rgba(255,255,255,.08)}.admin-card{display:flex;gap:9px;align-items:center;padding:10px;border-radius:12px;background:#ffffff09;margin-bottom:7px}.admin-avatar{width:34px;height:34px;border-radius:10px;background:#1e5eff;display:grid;place-items:center;font-weight:800}.admin-copy{min-width:0}.admin-copy strong{display:block;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.admin-copy span{display:block;color:#a9b9da;font-size:9px;margin-top:2px}.logout{display:flex;gap:10px;align-items:center;color:#ffced0;text-decoration:none;padding:10px 12px;border-radius:10px;font-size:12px;font-weight:700}.logout:hover{background:#ffffff10}.main-wrap{margin-left:254px;min-width:0;flex:1}.topbar{height:68px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 26px;position:sticky;top:0;z-index:50}.topbar-title{font-weight:800;color:#25324a}.topbar-user{display:flex;align-items:center;gap:9px}.topbar-avatar{width:34px;height:34px;border-radius:10px;background:#eef3ff;color:#1e5eff;display:grid;place-items:center;font-weight:800}.topbar-user small{display:block;color:#8994a7;font-size:9px}.content{padding:26px;max-width:1600px;margin:0 auto}.page-kicker{font-size:10px;font-weight:800;color:#4169e1;letter-spacing:.11em;text-transform:uppercase}.page-title{font-size:30px;font-weight:850;margin:5px 0}.muted{color:var(--muted)}.panel{background:#fff;border:1px solid var(--line);border-radius:17px;box-shadow:0 7px 24px rgba(14,34,72,.05)}.panel-head{padding:19px 21px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.panel-head h2{font-size:17px;margin:0;font-weight:800}.panel-head p{margin:4px 0 0;font-size:11px}.toolbar{padding:15px 21px;border-bottom:1px solid var(--line);background:#fbfcff}.btn-local{border:0;background:#1e5eff;color:#fff;border-radius:10px;padding:9px 13px;font-size:12px;font-weight:800}.btn-outline-local{border:1px solid #dbe3ef;background:#fff;color:#4c5b73;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:750;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.table-wrap{overflow:auto}.table{margin:0;min-width:900px}.table th{font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:#8290a7;font-weight:800;background:#fafbfe;white-space:nowrap}.table td{font-size:12px;vertical-align:middle}.status{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:800;background:#eef1f5;color:#65738a}.status.verified{background:#e7f8ef;color:#16804a}.status.pending_verification{background:#fff4d8;color:#966200}.status.blocked{background:#ffe8e9;color:#b42332}.status.rejected{background:#efeaff;color:#7042b8}.stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin:19px 0}.stat{background:#fff;border:1px solid var(--line);border-radius:15px;padding:17px}.stat-label{font-size:10px;font-weight:800;color:#8490a2;text-transform:uppercase;letter-spacing:.06em}.stat-value{font-size:27px;font-weight:850;margin-top:4px}.mobile-menu{display:none}
@media(max-width:1000px){.sidebar{transform:translateX(-100%);transition:transform .2s}.nav-open .sidebar{transform:translateX(0)}.main-wrap{margin-left:0}.mobile-menu{display:inline-flex}.topbar{padding:0 14px}.content{padding:18px}.stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.stat-grid{grid-template-columns:1fr 1fr;gap:9px}.stat{padding:13px}.stat-value{font-size:23px}.page-title{font-size:26px}}
</style>
</head>
<body>
<div class="app">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="main-wrap">
<header class="topbar">
  <div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-light mobile-menu" type="button" id="mobileMenu"><i class="bi bi-list"></i></button><div class="topbar-title">Maan World Local / <?=htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8')?></div></div>
  <div class="topbar-user"><div class="topbar-avatar"><?=htmlspecialchars(strtoupper(substr($admin['name'],0,1)),ENT_QUOTES,'UTF-8')?></div><div><strong style="font-size:11px"><?=htmlspecialchars($admin['name'],ENT_QUOTES,'UTF-8')?></strong><small><?=htmlspecialchars($admin['role'],ENT_QUOTES,'UTF-8')?></small></div></div>
</header>
