<?php
session_start();
require_once __DIR__ . '/../src/bootstrap.php';

$logger = \ZeroAI\Core\Logger::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'Username, email and password are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            
            // Add email column if it doesn't exist
            try {
                $db->query("ALTER TABLE users ADD COLUMN email TEXT");
            } catch (Exception $e) {
                // Column already exists, ignore
            }
            
            // Check if username or email already exists
            $existing = $db->query("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
            if (!empty($existing)) {
                $error = 'Username or email already registered';
            } else {
                // Step 1: Create user using DatabaseManager
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $userId = $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => 'frontend',
                    'organization_id' => 1
                ]);
                
                // Step 2: Get real user ID by selecting from database
                $userResult = $db->select('users', ['username' => $username, 'email' => $email], 1);
                $realUserId = $userResult[0]['id'] ?? $userId;
                
                // Step 3: Create company record with real user ID
                if ($realUserId && !empty($company_name)) {
                    try {
                        $companyId = $db->insert('companies', [
                            'name' => $company_name,
                            'email' => $email,
                            'phone' => $phone,
                            'website' => $website,
                            'linkedin' => $linkedin,
                            'organization_id' => 1,
                            'user_id' => $realUserId,
                            'created_by' => $realUserId
                        ]);
                        
                        $logger->info('Company created during registration', [
                            'company_id' => $companyId,
                            'company_name' => $company_name,
                            'user_id' => $realUserId,
                            'username' => $username
                        ]);
                    } catch (Exception $companyError) {
                        $logger->error('Company creation failed during registration', [
                            'error' => $companyError->getMessage(),
                            'company_name' => $company_name,
                            'user_id' => $realUserId
                        ]);
                    }
                }
                
                $success = 'Registration successful! You can now login.';
                
                $logger->debug('User created with ID', ['user_id' => $realUserId, 'username' => $username]);
                
                // Debug logging
                $logger->debug('Registration completed', [
                    'user_id' => $userId,
                    'username' => $username,
                    'company_name' => $company_name
                ]);
                
                // Check if company was created and add debug info if debug mode is enabled
                if ($logger->isDebugEnabled()) {
                    $companyCheck = $db->select('companies', ['user_id' => $realUserId]);
                    
                    if (!empty($companyCheck)) {
                        $success .= "<br><small>Debug: Company created - {$companyCheck[0]['name']} (ID: {$companyCheck[0]['id']})</small>";
                    } else {
                        $success .= "<br><small style='color:red'>Debug: No company found for user ID: $realUserId</small>";
                    }
                }
            }
        } catch (Exception $e) {
            $logger->error('Registration failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'username' => $username ?? 'unknown',
                'email' => $email ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ZeroAI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container { min-height: 100vh; padding: 20px; }
        .login-card { 
            max-width: 500px; 
            margin: 0 auto;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .card { 
            border: none; 
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-body { padding: 3rem 2rem; }
        .display-4 { font-size: 3.5rem; }
        .btn-primary { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); 
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .input-group-text {
            border-radius: 15px 0 0 15px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 15px 15px 0;
        }
        .alert {
            border-radius: 15px;
            border: none;
        }
        .text-decoration-none:hover {
            text-decoration: underline !important;
        }
        @media (max-width: 576px) {
            .card-body { padding: 2rem 1.5rem; }
            .display-4 { font-size: 2.5rem; }
            .login-container { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid login-container d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6">
                <div class="card login-card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-plus display-4 text-primary"></i>
                            <h2 class="mt-2">Join ZeroAI</h2>
                            <p class="text-muted">Create your account</p>
                        </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <div class="text-center">
                            <a href="/web/login.php" class="btn btn-primary">Login Now</a>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Company Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-building"></i></span>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Username *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-circle"></i></span>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Website URL</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-globe"></i></span>
                                    <input type="url" name="website" class="form-control" placeholder="https://example.com" value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">LinkedIn Profile</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-linkedin"></i></span>
                                    <input type="url" name="linkedin" class="form-control" placeholder="https://linkedin.com/in/username" value="<?= htmlspecialchars($_POST['linkedin'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted">Already have an account? <a href="/web/login.php" class="text-decoration-none">Login here</a></p>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>