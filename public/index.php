<?php
session_start();
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Repository/UserRepository.php';
require __DIR__ . '/../src/Repository/ResponseRepository.php';
require __DIR__ . '/../src/Auth.php';

$db    = Database::connect();
$auth  = new Auth(new UserRepository($db));
$page  = $_GET['page'] ?? 'overview';

if ($page === 'logout') { $auth->logout(); header('Location: index.php?page=login'); exit; }

if ($page === 'login') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($auth->attempt($_POST['username'] ?? '', $_POST['password'] ?? '')) {
            header('Location: index.php'); exit;
        }
        $error = 'Invalid username or password.';
    }
    require __DIR__ . '/../views/login.php';
    exit;
}

// Everything below requires login.
if (!$auth->check()) { header('Location: index.php?page=login'); exit; }

$repo = new ResponseRepository($db);
$total      = $repo->total();
$avg        = $repo->averageScore();
$categories = $repo->categoryBreakdown();
$cells      = $repo->byCell();
$indicators = $repo->indicatorStats();
$recent     = $repo->recent(15);

require __DIR__ . '/../views/dashboard.php';
