<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
require_once '../../includes/constant.php';
require_once '../../includes/function.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['userAppId'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }

    $userId = $_SESSION['userAppId'];
    $user = dbSelect('users', ['id' => $userId])->fetch_assoc();
    if (!$user || !in_array($user['role'], ['admin', 'superadmin'])) {
        echo json_encode(['success' => false, 'message' => 'Not an admin']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'list':
            $videos = $db->query("SELECT * FROM guide_videos ORDER BY display_order ASC, created_at DESC");
            $list = [];
            while ($row = $videos->fetch_assoc()) {
                $list[] = $row;
            }
            echo json_encode(['success' => true, 'videos' => $list]);
            break;

        case 'create':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $videoUrl = trim($_POST['video_url'] ?? '');
            $icon = trim($_POST['icon'] ?? 'play_circle');
            $iconColor = trim($_POST['icon_color'] ?? 'primary');
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 1;

            if (empty($title) || empty($videoUrl)) {
                echo json_encode(['success' => false, 'message' => 'Title and video URL are required']);
                exit;
            }

            $videoId = getYouTubeVideoId($videoUrl);
            if ($videoId) {
                $videoUrl = 'https://www.youtube.com/embed/' . $videoId;
            }

            $id = dbInsert('guide_videos', [
                'title' => sanitize($title),
                'description' => sanitize($description),
                'video_url' => sanitize($videoUrl),
                'icon' => sanitize($icon),
                'icon_color' => sanitize($iconColor),
                'display_order' => $displayOrder,
                'is_active' => $isActive
            ]);

            if ($id) {
                echo json_encode(['success' => true, 'message' => 'Video added successfully', 'id' => $id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert video into database']);
            }
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $videoUrl = trim($_POST['video_url'] ?? '');
            $icon = trim($_POST['icon'] ?? 'play_circle');
            $iconColor = trim($_POST['icon_color'] ?? 'primary');
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (!$id || empty($title) || empty($videoUrl)) {
                echo json_encode(['success' => false, 'message' => 'Title and video URL are required']);
                exit;
            }

            $videoId = getYouTubeVideoId($videoUrl);
            if ($videoId) {
                $videoUrl = 'https://www.youtube.com/embed/' . $videoId;
            }

            dbUpdate('guide_videos', [
                'title' => sanitize($title),
                'description' => sanitize($description),
                'video_url' => sanitize($videoUrl),
                'icon' => sanitize($icon),
                'icon_color' => sanitize($iconColor),
                'display_order' => $displayOrder,
                'is_active' => $isActive
            ], ['id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid video ID']);
                exit;
            }

            dbDelete('guide_videos', ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
            break;

        case 'toggle':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid video ID']);
                exit;
            }

            $video = dbSelect('guide_videos', ['id' => $id])->fetch_assoc();
            if (!$video) {
                echo json_encode(['success' => false, 'message' => 'Video not found']);
                exit;
            }

            $newStatus = $video['is_active'] ? 0 : 1;
            dbUpdate('guide_videos', ['is_active' => $newStatus], ['id' => $id]);
            echo json_encode(['success' => true, 'message' => 'Video ' . ($newStatus ? 'activated' : 'deactivated'), 'is_active' => $newStatus]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'PHP error: ' . $e->getMessage()]);
}
