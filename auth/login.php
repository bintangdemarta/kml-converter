<?php
require_once __DIR__ . '/../lib/auth.php';
auth_boot();

if (is_logged_in()) {
    redirect('/pilot/dashboard.php');
}

$errors = [];
$old = ['identifier' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['identifier'] = post('identifier');
    $password = post('password');

    [$ok, $err] = login($old['identifier'], $password);
    if ($ok) {
        flash('Selamat datang kembali!', 'success');
        redirect('/pilot/dashboard.php');
    }
    $errors[] = $err;
}

$page_title = 'Login';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:440px;margin:0 auto;">
    <h2>Login</h2>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label>Username atau Email</label>
        <input name="identifier" value="<?= e($old['identifier']) ?>" required autofocus>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login</button>
    </form>
    <p class="muted" style="margin-top:16px;">Belum punya akun? <a href="<?= url('/auth/register.php') ?>">Register</a></p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
