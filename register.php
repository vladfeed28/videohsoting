<?php
session_start();
require_once 'db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $channel_name = trim($_POST['channel_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($channel_name) || empty($password) || empty($confirm_password)) {
        $message = 'Все поля обязательны для заполнения.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Введите корректный Email адрес.';
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = 'Пароли не совпадают.';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Пароль должен содержать минимум 6 символов.';
        $message_type = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR channel_name = ?");
        $stmt->execute([$email, $channel_name]);
        if ($stmt->fetchColumn() > 0) {
            $message = 'Пользователь с таким Email или именем канала уже существует.';
            $message_type = 'error';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO users (email, channel_name, password) VALUES (?, ?, ?)");
                $stmt->execute([$email, $channel_name, $hashed_password]);

                $_SESSION['message'] = 'Регистрация прошла успешно! Теперь вы можете войти.';
                $_SESSION['message_type'] = 'success';
                header('Location: login.php');
                exit();
            } catch (PDOException $e) {
                $message = 'Ошибка при регистрации: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - Мой Видеохостинг</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <a href="index.php" class="logo">МойВидХост</a>
            <nav class="auth-nav">
                <a href="login.php" class="btn btn-login">Войти</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="auth-form-wrapper">
                <h2 class="section-title">Регистрация</h2>
                <?php if ($message): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>
                <form action="register.php" method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="channel_name">Название канала:</label>
                        <input type="text" id="channel_name" name="channel_name" required value="<?php echo htmlspecialchars($channel_name ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Подтвердите пароль:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
                </form>
                <p class="auth-link-text">Уже есть аккаунт? <a href="login.php">Войти</a></p>
            </div>
        </div>
    </main>


</body>
</html>
