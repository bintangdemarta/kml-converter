<?php
require_once __DIR__ . '/../lib/auth.php';
auth_boot();

if (is_logged_in()) {
    redirect('/pilot/dashboard.php');
}

$errors = [];
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old['username'] = post('username');
    $old['email'] = post('email');
    $password = post('password');
    $confirm = post('confirm');

    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }

    if (!$errors) {
        [$ok, $err] = register_user($old['username'], $old['email'], $password);
        if ($ok) {
            flash('Registrasi berhasil. Silakan login.', 'success');
            redirect('/auth/login.php');
        }
        $errors[] = $err;
    }
}

$page_title = 'Register';
require __DIR__ . '/../partials/header.php';
?>
<div class="card" style="max-width:440px;margin:0 auto;">
    <h2>Register Pilot</h2>
    <p class="muted">Buat akun untuk mulai mengajukan flight request.</p>

    <?php foreach ($errors as $err): ?>
        <div class="flash error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label>Username</label>
        <input name="username" value="<?= e($old['username']) ?>" required autofocus>

        <label>Email</label>
        <input type="email" name="email" value="<?= e($old['email']) ?>" required>

        <label>Password <span class="muted">(min. 6 karakter)</span></label>
        <input type="password" name="password" required>

        <label>Konfirmasi Password</label>
        <input type="password" name="confirm" required>

        <button type="submit">Register</button>
    </form>
    <p class="muted" style="margin-top:16px;">Sudah punya akun? <a href="<?= url('/auth/login.php') ?>">Login</a></p>
</div>
<?php require __DIR__ . '/../partials/footer.php'; ?>
