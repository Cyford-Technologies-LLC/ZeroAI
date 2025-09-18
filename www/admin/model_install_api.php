<?php
require_once 'includes/autoload.php';

header('Content-Type: application/json');

use ZeroAI\Core\PeerManager;

$peerManager = PeerManager::getInstance();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'start_install':
            $peerIp = $_POST['peer_ip'] ?? '';
            $modelName = $_POST['model_name'] ?? '';
            
            if (empty($peerIp) || empty($modelName)) {
                throw new Exception('Missing peer IP or model name');
            }
            
            $jobId = $peerManager->startModelInstallation($peerIp, $modelName);
            echo json_encode(['success' => true, 'job_id' => $jobId]);
            break;
            
        case 'get_status':
            $jobId = $_GET['job_id'] ?? '';
            
            if (empty($jobId)) {
                throw new Exception('Missing job ID');
            }
            
            $status = $peerManager->getInstallationStatus($jobId);
            echo json_encode(['success' => true, 'status' => $status]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>