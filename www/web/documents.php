<?php
$currentPage = 'documents';
$pageTitle = 'Documents - ZeroAI CRM';
$context = $_GET['context'] ?? 'general';
$contextId = $_GET['project_id'] ?? $_GET['company_id'] ?? null;
include __DIR__ . '/includes/header.php';

// Get context info
$contextInfo = null;
if ($contextId) {
    try {
        if ($context === 'projects' || isset($_GET['project_id'])) {
            $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
            $stmt->execute([$contextId]);
            $contextInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $contextType = 'Project';
        } elseif ($context === 'companies' || isset($_GET['company_id'])) {
            $stmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $stmt->execute([$contextId]);
            $contextInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $contextType = 'Company';
        }
    } catch (Exception $e) {
        $error = "Context not found";
    }
}

// Handle file upload
if ($_POST && isset($_FILES['document'])) {
    try {
        $uploadDir = __DIR__ . '/../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = basename($_FILES['document']['name']);
        $targetPath = $uploadDir . time() . '_' . $fileName;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            $success = "Document uploaded successfully!";
        } else {
            $error = "Failed to upload document.";
        }
    } catch (Exception $e) {
        $error = "Upload error: " . $e->getMessage();
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>📄 Documents<?= $contextInfo ? ' - ' . htmlspecialchars($contextInfo['name']) : '' ?></h5>
                </div>
                <div class="card-body">
                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="file" class="form-control" name="document" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Document
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Documents List -->
                    <div class="alert alert-info">
                        <h6>Document Management Features:</h6>
                        <ul class="mb-0">
                            <li>File upload and storage</li>
                            <li>Document sharing between companies</li>
                            <li>Version control</li>
                            <li>Access permissions</li>
                            <li>Document categories and tags</li>
                        </ul>
                        <p class="mt-2 mb-0"><strong>Note:</strong> Full document management system coming soon!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>