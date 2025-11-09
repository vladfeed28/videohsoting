<?php
session_start();
require_once 'db.php';

$ffmpeg_path = __DIR__ . '/ffmpeg/bin/ffmpeg.exe'; 
$ffprobe_path = __DIR__ . '/ffmpeg/bin/ffprobe.exe';

function formatDuration($seconds) {
    if (!is_numeric($seconds) || $seconds <= 0) {
        return '00:00';
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
    return sprintf('%02d:%02d', $minutes, $seconds);
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Для загрузки видео необходимо войти в аккаунт.';
    $_SESSION['message_type'] = 'error';
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';
$duration = '00:00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $user_id = $_SESSION['user_id'];


    $base_upload_dir = __DIR__ . '/uploads/';

    $video_upload_dir = $base_upload_dir . 'videos/';
    $thumbnail_upload_dir = $base_upload_dir . 'thumbnails/';

    if (!is_dir($video_upload_dir)) {
        mkdir($video_upload_dir, 0777, true);
    }
    if (!is_dir($thumbnail_upload_dir)) {
        mkdir($thumbnail_upload_dir, 0777, true);
    }
    if (empty($title)) {
        $message = 'Название видео обязательно для заполнения.';
        $message_type = 'error';
    } elseif (empty($_FILES['video_file']['name'])) {
        $message = 'Выберите видеофайл для загрузки.';
        $message_type = 'error';
    } else {
        $video_file = $_FILES['video_file'];
        $video_file_name = uniqid('video_') . '_' . basename($video_file['name']);
        $video_target_file = $video_upload_dir . $video_file_name;
        $video_db_path = 'uploads/videos/' . $video_file_name;
        $video_upload_ok = 1;
        $video_file_type = strtolower(pathinfo($video_target_file, PATHINFO_EXTENSION));

        $allowed_video_types = ['mp4', 'webm', 'ogg'];
        if (!in_array($video_file_type, $allowed_video_types)) {
            $message = 'Разрешены только MP4, WebM, OGG видеофайлы.';
            $message_type = 'error';
            $video_upload_ok = 0;
        }

        if ($video_file['size'] > 500 * 1024 * 1024) {
            $message = 'Размер видеофайла слишком большой (макс. 500МБ).';
            $message_type = 'error';
            $video_upload_ok = 0;
        }

        $thumbnail_upload_ok = 1;
        $thumbnail_file = $_FILES['thumbnail_file'];
        $thumbnail_db_path = '';

        if (empty($thumbnail_file['name'])) {
            $thumbnail_db_path = 'uploads/thumbnails/' . uniqid('thumb_auto_') . '.jpg';
            $thumbnail_target_file = $thumbnail_db_path;
            $thumbnail_upload_ok = 2;
        } else {
            $thumbnail_file_name = uniqid('thumb_') . '_' . basename($thumbnail_file['name']);
            $thumbnail_target_file = $thumbnail_upload_dir . $thumbnail_file_name;
            $thumbnail_db_path = 'uploads/thumbnails/' . $thumbnail_file_name;
            
            $allowed_image_types = ['jpg', 'jpeg', 'png', 'gif'];
            $thumbnail_file_type = strtolower(pathinfo($thumbnail_target_file, PATHINFO_EXTENSION));

            if (!in_array($thumbnail_file_type, $allowed_image_types)) {
                $message = 'Разрешены только JPG, JPEG, PNG, GIF изображения для миниатюр.';
                $message_type = 'error';
                $thumbnail_upload_ok = 0;
            }

            if ($thumbnail_file['size'] > 5 * 1024 * 1024) {
                $message = 'Размер миниатюры слишком большой (макс. 5МБ).';
                $message_type = 'error';
                $thumbnail_upload_ok = 0;
            }

        }

            if ($video_upload_ok && $thumbnail_upload_ok !== 0) {

                if (!move_uploaded_file($video_file['tmp_name'], $video_target_file)) {
                    $message = 'Ошибка при перемещении видеофайла.';
                    $message_type = 'error';
                    goto end_upload;
                }

                if ($thumbnail_upload_ok === 2) {
                    $ffmpeg_command = escapeshellarg($ffmpeg_path) . " -i " . escapeshellarg($video_target_file) . " -ss 00:00:01 -vframes 1 -q:v 2 " . escapeshellarg($thumbnail_target_file) . " 2>&1";
                    print($ffmpeg_command);
                    
                    exec($ffmpeg_command, $output, $return_code);

                    if ($return_code !== 0) {
                        $message = 'Ошибка генерации миниатюры. (Код: ' . $return_code . ')';
                        $message_type = 'error';
                        unlink($video_target_file);
                        goto end_upload;
                    }
                } elseif ($thumbnail_upload_ok === 1) {
                    if (!move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_target_file)) {
                        $message = 'Ошибка при перемещении файла миниатюры.';
                        $message_type = 'error';
                        unlink($video_target_file);
                        goto end_upload;
                    }
                }

                $ffprobe_command = escapeshellarg($ffprobe_path) . " -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($video_target_file);

                exec($ffprobe_command, $duration_output, $return_code_duration);

                if ($return_code_duration === 0 && !empty($duration_output[0])) {
                    $seconds = floatval($duration_output[0]);
                    $duration = formatDuration($seconds);
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO videos (user_id, title, description, video_path, thumbnail_path, duration) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $title, $description, $video_db_path, $thumbnail_db_path, $duration]);

                    $_SESSION['message'] = 'Видео успешно загружено!';
                    $_SESSION['message_type'] = 'success';
                    header('Location: index.php');
                    exit();
                } catch (PDOException $e) {
                    $message = 'Ошибка при сохранении данных видео: ' . $e->getMessage();
                    $message_type = 'error';
                    unlink($video_target_file);
                    if (file_exists($thumbnail_target_file)) unlink($thumbnail_target_file);
                }
            }
        }
    }

end_upload:

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузить видео - Мой Видеохостинг</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <a href="index.php" class="logo">МойВидХост</a>
            <nav class="auth-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-text">Привет, <?php echo htmlspecialchars($_SESSION['channel_name']); ?>!</span>
                    <a href="logout.php" class="btn btn-login">Выйти</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-login">Войти</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <div class="upload-form-wrapper">
                <h2 class="section-title">Загрузить новое видео</h2>
                <?php if ($message): ?>
                    <p class="message <?php echo $message_type; ?>"><?php echo $message; ?></p>
                <?php endif; ?>
                <form action="upload.php" method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-group">
                        <label for="title">Название видео:</label>
                        <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($title ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Описание:</label>
                        <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="video_file">Видеофайл (MP4, WebM, OGG):</label>
                        <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm,video/ogg" required>
                    </div>
                    <div class="form-group">
                        <label for="thumbnail_file">Миниатюра (JPG, PNG, GIF):</label>
                        <input type="file" id="thumbnail_file" name="thumbnail_file" accept="image/jpeg,image/png,image/gif">
                    </div>
                    <button type="submit" class="btn btn-primary">Загрузить видео</button>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
