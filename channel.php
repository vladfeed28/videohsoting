<?php
session_start();
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$channel_name_session = $is_logged_in ? htmlspecialchars($_SESSION['channel_name']) : '';

$channel_info = null;
$channel_videos = [];
$message = '';
$message_type = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video_id'])) {
    if (!$is_logged_in) {
        $_SESSION['message'] = 'Необходимо авторизоваться для удаления видео.';
        $_SESSION['message_type'] = 'error';
        header('Location: login.php');
        exit();
    }

    $video_to_delete_id = $_POST['delete_video_id'];
    
    try {
        $stmt_check = $pdo->prepare("SELECT user_id, video_path, thumbnail_path FROM videos WHERE id = ?");
        $stmt_check->execute([$video_to_delete_id]);
        $video_data = $stmt_check->fetch();

        if (!$video_data) {
            $message = 'Видео для удаления не найдено.';
            $message_type = 'error';
        } elseif ($video_data['user_id'] != $_SESSION['user_id']) {
            $message = 'У вас нет прав для удаления этого видео.';
            $message_type = 'error';
        } else {
            $stmt_delete = $pdo->prepare("DELETE FROM videos WHERE id = ?");
            $stmt_delete->execute([$video_to_delete_id]);

            if ($stmt_delete->rowCount() > 0) { 
                $base_dir = __DIR__ . '/';
                $video_full_path = $base_dir . $video_data['video_path'];
                $thumbnail_full_path = $base_dir . $video_data['thumbnail_path'];

                if (file_exists($video_full_path)) {
                    unlink($video_full_path);
                }
                if (file_exists($thumbnail_full_path)) {
                    unlink($thumbnail_full_path);
                }

                $_SESSION['message'] = 'Видео успешно удалено.';
                $_SESSION['message_type'] = 'success';
            } else {
                $message = 'Не удалось удалить видео из базы данных.';
                $message_type = 'error';
            }
        }
    } catch (PDOException $e) {
        $message = 'Ошибка при удалении видео: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Ошибка при удалении видео (ID: {$video_to_delete_id}): " . $e->getMessage());
    }

    header('Location: channel.php?id=' . $_SESSION['user_id']);
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_from_url = $_GET['id'];

    try {
        $stmt_user = $pdo->prepare("SELECT id, channel_name, created_at FROM users WHERE id = ?");
        $stmt_user->execute([$user_id_from_url]);
        $channel_info = $stmt_user->fetch();

        if ($channel_info) {
            $stmt_videos = $pdo->prepare("SELECT *, user_id FROM videos WHERE user_id = ? ORDER BY upload_date DESC");
            $stmt_videos->execute([$user_id_from_url]);
            $channel_videos = $stmt_videos->fetchAll();
        } else {
            $message = 'Канал не найден.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Ошибка при загрузке данных канала: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Ошибка при загрузке channel.php: " . $e->getMessage());
    }
} else {
    $message = 'Неверный ID канала.';
    $message_type = 'error';
}

if (!$channel_info && $message_type == 'error') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: index.php');
    exit();
}
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $channel_info ? htmlspecialchars($channel_info['channel_name']) : 'Канал не найден'; ?> - Мой Видеохостинг</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <a href="index.php" class="logo">МойВидХост</a>
            <div class="search-bar">
                <form action="index.php" method="GET">
                    <input type="text" name="search" placeholder="Искать видео..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button type="submit">Поиск</button>
                </form>
            </div>
            <nav class="auth-nav">
                <?php if ($is_logged_in): ?>
                    <a href="upload.php" class="btn btn-upload">Загрузить видео</a>
                    <span class="welcome-text">Привет, <a href="channel.php?id=<?php echo $user_id; ?>"><?php echo $channel_name_session; ?></a>!</span>
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

            <?php if ($channel_info): ?>
                <div class="channel-header">
                    <h2 class="channel-name-title"><?php echo htmlspecialchars($channel_info['channel_name']); ?></h2>
                    <p class="channel-stats">
                        Канал создан: <?php echo date('d.m.Y', strtotime($channel_info['created_at'])); ?>
                    </p>
                </div>

                <h3 class="section-title">Видео канала (<?php echo count($channel_videos); ?>)</h3>
                <div class="video-grid">
                    <?php if (!empty($channel_videos)): ?>
                        <?php foreach ($channel_videos as $video): ?>
                            <div class="video-card">
                                <a href="watch.php?id=<?php echo $video['id']; ?>" class="video-card-link">
                                    <div class="thumbnail">
                                        <img src="<?php echo htmlspecialchars($video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>">
                                        <span class="duration"><?php echo htmlspecialchars($video['duration']); ?></span> 
                                    </div>
                                    <div class="video-info">
                                        <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                        <p class="video-meta">
                                            <span class="views"><?php echo htmlspecialchars($video['views']); ?> просмотров</span>
                                            <span class="upload-date"><?php echo date('d.m.Y', strtotime($video['upload_date'])); ?></span>
                                        </p>
                                    </div>
                                </a>
                                <?php 
                                if ($is_logged_in && $channel_info['id'] == $_SESSION['user_id'] && $video['user_id'] == $_SESSION['user_id']): 
                                ?>
                                    <form action="channel.php?id=<?php echo $user_id_from_url; ?>" method="POST" class="delete-video-form">
                                        <input type="hidden" name="delete_video_id" value="<?php echo $video['id']; ?>">
                                        <button type="submit" class="btn btn-delete-video" onclick="return confirm('Вы уверены, что хотите удалить видео &quot;<?php echo htmlspecialchars($video['title']); ?>&quot;?');">
                                            Удалить
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; width: 100%; color: #aaa;">Этот канал еще не загрузил ни одного видео.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
