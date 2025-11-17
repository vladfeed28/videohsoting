<?php 
session_start();
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$user_id = $_SESSION['user_id'];
$channel_name = $is_logged_in ? htmlspecialchars($_SESSION['channel_name']) : '';
$channel_name_session = $is_logged_in ? htmlspecialchars($_SESSION['channel_name']) : '';

$video = null;
$message = '';
$message_type = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $video_id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT v.*, u.channel_name, u.id AS user_id FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id = ?");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();

        if ($video) {
            $pdo->prepare("UPDATE videos SET views = views + 1 WHERE id = ?")->execute([$video_id]);
            $video['views']++;
        } else {
            $message = 'Видео не найдено.';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Ошибка при загрузке видео: ' . $e->getMessage();
        $message_type = 'error';
        error_log("Ошибка при загрузке watch.php: " . $e->getMessage());
    }
} else {
    $message = 'Неверный ID видео.';
    $message_type = 'error';
}

if (!$video && $message_type == 'error') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header('Location: index.php');
    exit();
}


?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $video ? htmlspecialchars($video['title']) : 'Видео не найдено'; ?> - Мой Видеохостинг</title>
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
            <?php if ($message && $message_type != 'error'): ?>
                <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
            <?php endif; ?>

            <?php if ($video): ?>
                <div class="video-player-section">
                    <div class="video-player-container">
                        <video controls poster="<?php echo htmlspecialchars($video['thumbnail_path']); ?>">
                            <source src="<?php echo htmlspecialchars($video['video_path']); ?>" type="video/mp4">
                            Ваш браузер не поддерживает видео HTML5.
                        </video>
                    </div>
                    <h1 class="video-watch-title"><?php echo htmlspecialchars($video['title']); ?></h1>
                    <div class="video-details-bar">
                        <p class="video-meta-watch">
                            <span class="channel-name-watch">
                                <a href="channel.php?id=<?php echo $video['user_id']; ?>"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($video['channel_name']); ?></a>
                            </span>
                            <span class="views-watch"><i class="fas fa-eye"></i> <?php echo htmlspecialchars($video['views']); ?> просмотров</span>
                            <span class="upload-date-watch"><i class="fas fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($video['upload_date'])); ?></span>
                        </p>

                    </div>
                    <div class="video-description">
                        <h3>Описание:</h3>
                        <p><?php echo nl2br(htmlspecialchars($video['description'])); ?></p>
                    </div>
                </div>

                <div class="recommended-videos">
                    <h3 class="section-title">Рекомендуемые</h3>
                    <div class="video-grid">
                        <?php
                            try {
                                $stmt_recommended = $pdo->prepare("SELECT v.*, u.channel_name FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id != ? ORDER BY RANDOM() LIMIT 4");
                                $stmt_recommended->execute([$video_id]);
                                $recommended_videos = $stmt_recommended->fetchAll();
                            } catch (PDOException $e) {
                                error_log("Ошибка при получении рекомендованных видео: " . $e->getMessage());
                                $recommended_videos = [];
                            }

                            if (!empty($recommended_videos)):
                                foreach ($recommended_videos as $rec_video): ?>
                                    <a href="watch.php?id=<?php echo $rec_video['id']; ?>" class="video-card">
                                        <div class="thumbnail">
                                            <img src="<?php echo htmlspecialchars($rec_video['thumbnail_path']); ?>" alt="<?php echo htmlspecialchars($rec_video['title']); ?>">
                                            <span class="duration"><?php echo htmlspecialchars($rec_video['duration']); ?></span> 
                                        </div>
                                        <div class="video-info">
                                            <h3 class="video-title"><?php echo htmlspecialchars($rec_video['title']); ?></h3>
                                            <p class="video-meta">
                                                <span class="channel-name">
                                                    <a href="channel.php?id=<?php echo $rec_video['user_id']; ?>"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($rec_video['channel_name']); ?></a>
                                                </span>
                                                <span class="views"><i class="fas fa-eye"></i> <?php echo htmlspecialchars($rec_video['views']); ?> просмотров</span>
                                                <span class="upload-date"><i class="fas fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($rec_video['upload_date'])); ?></span>
                                            </p>
                                        </div>
                                    </a>
                                <?php endforeach;
                            else: ?>
                                <p style="text-align: center; width: 100%;">Пока нет других видео для рекомендаций.</p>
                            <?php endif;
                        ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>

</body>
</html>
