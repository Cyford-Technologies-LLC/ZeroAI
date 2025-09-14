<?php
require_once 'includes/autoload.php';

$pageTitle = 'Cache Status - ZeroAI';
$currentPage = 'cache';
include __DIR__ . '/includes/header.php';

$cache = \ZeroAI\Core\CacheManager::getInstance();
?>

<h1>🚀 Performance & Cache Status</h1>

<div class="card">
    <h3>PHP Extensions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="padding: 10px; background: <?= extension_loaded('apcu') ? '#d4edda' : '#f8d7da' ?>; border-radius: 4px;">
            <?= extension_loaded('apcu') ? '✅' : '❌' ?> <strong>APCu:</strong> <?= extension_loaded('apcu') ? 'Enabled' : 'Disabled' ?>
        </div>
        <div style="padding: 10px; background: <?= extension_loaded('opcache') ? '#d4edda' : '#f8d7da' ?>; border-radius: 4px;">
            <?= extension_loaded('opcache') ? '✅' : '❌' ?> <strong>OPcache:</strong> <?= extension_loaded('opcache') ? 'Enabled' : 'Disabled' ?>
        </div>
        <div style="padding: 10px; background: <?= extension_loaded('redis') ? '#d4edda' : '#f8d7da' ?>; border-radius: 4px;">
            <?= extension_loaded('redis') ? '✅' : '❌' ?> <strong>Redis:</strong> <?= extension_loaded('redis') ? 'Enabled' : 'Disabled' ?>
        </div>
    </div>
</div>

<?php if (extension_loaded('opcache')): ?>
<div class="card">
    <h3>OPcache Status</h3>
    <?php $opcache = opcache_get_status(); ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Hit Rate:</strong> <?= round($opcache['opcache_statistics']['opcache_hit_rate'], 2) ?>%
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Memory Used:</strong> <?= round($opcache['memory_usage']['used_memory'] / 1024 / 1024, 2) ?>MB
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Cached Files:</strong> <?= $opcache['opcache_statistics']['num_cached_scripts'] ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (extension_loaded('apcu')): ?>
<div class="card">
    <h3>APCu Status</h3>
    <?php $apcu = apcu_cache_info(); ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Hit Rate:</strong> <?= round(($apcu['num_hits'] / ($apcu['num_hits'] + $apcu['num_misses'])) * 100, 2) ?>%
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Memory Used:</strong> <?= round($apcu['mem_size'] / 1024 / 1024, 2) ?>MB
        </div>
        <div style="padding: 10px; background: #f8f9fa; border-radius: 4px;">
            <strong>Cached Entries:</strong> <?= $apcu['num_entries'] ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>Cache Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button onclick="clearCache('apcu')" class="btn-warning">Clear APCu</button>
        <button onclick="clearCache('opcache')" class="btn-warning">Clear OPcache</button>
        <button onclick="clearCache('redis')" class="btn-warning">Clear Redis</button>
        <button onclick="clearCache('all')" class="btn-danger">Clear All Caches</button>
    </div>
</div>

<script>
function clearCache(type) {
    if (confirm('Clear ' + type + ' cache?')) {
        fetch('/admin/clear_cache.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type: type})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to clear cache');
            }
        });
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>