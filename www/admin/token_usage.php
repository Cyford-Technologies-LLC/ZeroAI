<?php 
$pageTitle = 'Token Usage - ZeroAI';
$currentPage = 'token_usage';
include __DIR__ . '/includes/header.php';

// Get usage data via Python script
$summaryCmd = "cd /app && /app/venv/bin/python -c \"
from src.database.token_tracking import tracker
import json
summary = tracker.get_usage_summary(30)
daily = tracker.get_daily_costs(7)
print(json.dumps({'summary': summary, 'daily': daily}))
\" 2>/dev/null";

$result = shell_exec($summaryCmd);
$data = json_decode($result, true) ?? ['summary' => ['by_service' => [], 'top_services' => []], 'daily' => []];
?>

<h1>Token Usage & Costs</h1>

<div class="card">
    <h3>Usage Summary (Last 30 Days)</h3>
    <table>
        <thead>
            <tr>
                <th>Service Type</th>
                <th>Requests</th>
                <th>Total Tokens</th>
                <th>Cost (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['summary']['by_service'] as $service): ?>
            <tr>
                <td><?= htmlspecialchars($service['service_type']) ?></td>
                <td><?= number_format($service['requests']) ?></td>
                <td><?= number_format($service['total_tokens']) ?></td>
                <td>$<?= number_format($service['total_cost'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Top Services by Cost</h3>
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Model</th>
                <th>Requests</th>
                <th>Tokens</th>
                <th>Cost (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['summary']['top_services'] as $service): ?>
            <tr>
                <td><?= htmlspecialchars($service['service_name']) ?></td>
                <td><?= htmlspecialchars($service['model_name'] ?? 'N/A') ?></td>
                <td><?= number_format($service['requests']) ?></td>
                <td><?= number_format($service['total_tokens']) ?></td>
                <td>$<?= number_format($service['total_cost'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Daily Costs (Last 7 Days)</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Service Type</th>
                <th>Tokens</th>
                <th>Cost (USD)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data['daily'] as $day): ?>
            <tr>
                <td><?= htmlspecialchars($day['date']) ?></td>
                <td><?= htmlspecialchars($day['service_type']) ?></td>
                <td><?= number_format($day['tokens']) ?></td>
                <td>$<?= number_format($day['cost'], 4) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


