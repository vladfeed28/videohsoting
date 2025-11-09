<?php
session_start();
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$channel_name = $is_logged_in ? htmlspecialchars($_SESSION['channel_name']) : '';
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

$search_query = trim($_GET['search'] ?? '');
$videos = [];
$section_title = 'Рекомендуемые видео';

try {
    if (!empty($search_query)) {
        $section_title = 'Результаты поиска по запросу: "' . htmlspecialchars($search_query) . '"';
        
        $stmt = $pdo->prepare("
            SELECT v.*, u.channel_name, u.id AS user_id 
            FROM videos v 
            JOIN users u ON v.user_id = u.id 
            WHERE v.title LIKE ? OR v.description LIKE ? OR u.channel_name LIKE ?
            ORDER BY v.upload_date DESC LIMIT 16
        ");
        $search_param = '%' . $search_query . '%';
        $stmt->execute([$search_param, $search_param, $search_param]);
    } else {
        $stmt = $pdo->query("SELECT v.*, u.channel_name, u.id AS user_id FROM videos v JOIN users u ON v.user_id = u.id ORDER BY v.upload_date DESC LIMIT 16");
    }
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка при получении видео: " . $e->getMessage());
    $message = 'Не удалось загрузить видео. Попробуйте позже.';
    $message_type = 'error';
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой Видеохостинг - Главная</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <a href="index.php" class="logo">МойВидХост</a>
            <div class="search-bar">
                <form action="index.php" method="GET">
                    <input type="text" name="search" placeholder="Искать видео..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit">Поиск</button>
                </form>
            </div>
            <nav class="auth-nav">
                <?php if ($is_logged_in): ?>
                    <a href="upload.php" class="btn btn-upload">Загрузить видео</a>
                    <span class="welcome-text">Привет, <a href="channel.php?id=<?php echo $user_id; ?>"><?php echo $channel_name; ?></a>!</span>
                    <a href="logout.php" class="btn btn-login">Выйти</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">Войти</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <?php if ($message): ?>
                <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
            <?php endif; ?>

            <h2 class="section-title"><?php echo $section_title; ?></h2>
            <div class="video-grid">
                <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $video): ?>
                        <a href="watch.php?id=<?php echo $video['id']; ?>" class="video-card">
                            <div class="thumbnail">
                                <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                <!-- Пока duration не получаем, но можно добавить позже -->
                                <span class="duration"><?php echo htmlspecialchars($video['duration']); ?></span>
                            </div>
                            <div class="video-info">
                                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <p class="video-meta">
                                    <span class="channel-name">
                                        <a href="channel.php?id=<?php echo $video['user_id']; ?>">
                                            <?php echo htmlspecialchars($video['channel_name']); ?>
                                        </a>
                                    </span>
                                    <span class="views"><?php echo htmlspecialchars($video['views']); ?> просмотров</span>
                                    <span class="upload-date"><?php echo date('d.m.Y', strtotime($video['upload_date'])); ?></span>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; width: 100%;">
                        <?php echo !empty($search_query) ? 'По вашему запросу "' . htmlspecialchars($search_query) . '" ничего не найдено.' : 'Пока нет загруженных видео. Будьте первым!'; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
