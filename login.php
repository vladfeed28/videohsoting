<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = 'Пожалуйста, введите Email и пароль.';
        $message_type = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT id, email, channel_name, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['channel_name'] = $user['channel_name'];
            
            $_SESSION['message'] = 'Добро пожаловать, ' . htmlspecialchars($user['channel_name']) . '!';
            $_SESSION['message_type'] = 'success';
            header('Location: index.php');
            exit();
        } else {
            $message = 'Неверный Email или пароль.';
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Мой Видеохостинг</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <a href="index.php" class="logo">МойВидХост</a>
            <nav class="auth-nav">
                <a href="register.php" class="btn btn-login">Регистрация</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="auth-form-wrapper">
                <h2 class="section-title">Вход</h2>
                <?php if ($message): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>
                <form action="login.php" method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Войти</button>
                </form>
                <p class="auth-link-text">Нет аккаунта? <a href="register.php">Зарегистрироваться</a></p>
            </div>
        </div>
    </main>

</body>
</html>
