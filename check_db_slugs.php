<?php
require_once __DIR__ . '/config/db.php';
$rows = db_select("SELECT id, slug, title, is_published FROM news_articles");
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " | SLUG: " . var_export($r['slug'], true) . " | TITLE: " . $r['title'] . "\n";
}
