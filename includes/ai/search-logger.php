<?php
// Persist user searches into search_history.
// Feeds personalized suggestions + recommendations.

function ai_log_search($query, $resultsCount = 0, $filters = null) {
    global $db;
    if (!isset($db)) {
        return false;
    }
    $query = trim((string) $query);
    if ($query === '') {
        return false;
    }
    $userId = null;
    if (isset($_SESSION['userAppId'])) {
        $userId = (int) $_SESSION['userAppId'];
    }
    $filtersJson = ($filters !== null && is_array($filters)) ? json_encode($filters) : null;
    $resultsCount = (int) $resultsCount;

    if ($userId === null) {
        $stmt = $db->prepare("INSERT INTO search_history (user_id, search_query, filters, results_count) VALUES (NULL, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssi', $query, $filtersJson, $resultsCount);
    } else {
        $stmt = $db->prepare("INSERT INTO search_history (user_id, search_query, filters, results_count) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issi', $userId, $query, $filtersJson, $resultsCount);
    }
    return $stmt->execute();
}
