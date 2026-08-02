<?php
require_once __DIR__ . '/../lib/auth.php';
logout();
flash('Kamu sudah logout.', 'info');
redirect('/auth/login.php');
