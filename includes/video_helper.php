<?php

function extract_youtube_video_id($url)
{
    $url = trim((string) $url);
    if ($url === '') return null;
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return null;
    $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
    if ($host === 'youtu.be') {
        $id = trim($parts['path'] ?? '', '/');
        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
    if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
        parse_str($parts['query'] ?? '', $query);
        if (!empty($query['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $query['v'])) return $query['v'];
        if (preg_match('#^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})#', $parts['path'] ?? '', $matches)) return $matches[1];
    }
    return null;
}

function extract_google_drive_file_id($url)
{
    $url = trim((string) $url);
    if ($url === '') return null;
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return null;
    $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
    if (!in_array($host, ['drive.google.com', 'docs.google.com'], true)) return null;
    if (preg_match('#/file/d/([A-Za-z0-9_-]+)#', $parts['path'] ?? '', $matches)) return $matches[1];
    parse_str($parts['query'] ?? '', $query);
    $id = $query['id'] ?? '';
    return preg_match('/^[A-Za-z0-9_-]+$/', $id) ? $id : null;
}

function google_drive_preview_url($url)
{
    $id = extract_google_drive_file_id($url);
    return $id ? 'https://drive.google.com/file/d/' . rawurlencode($id) . '/preview' : null;
}