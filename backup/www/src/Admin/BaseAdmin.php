<?php

namespace ZeroAI\Admin;

abstract class BaseAdmin {
    protected $pageTitle;
    protected $currentPage;
    protected $data = [];
    
    public function __construct($pageTitle = '', $currentPage = '') {
        $this->pageTitle = $pageTitle;
        $this->currentPage = $currentPage;
        $this->init();
    }
    
    protected function init() {
        session_start();
        $this->checkAuth();
        $this->loadEnvironment();
    }
    
    protected function checkAuth() {
        if (!isset($_SESSION['admin_user'])) {
            header('Location: /admin/login.php');
            exit;
        }
    }
    
    protected function loadEnvironment() {
        if (file_exists('/app/.env')) {
            $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }
    }
    
    public function render() {
        $this->handleRequest();
        $this->renderHeader();
        $this->renderContent();
        $this->renderFooter();
    }
    
    protected function renderHeader() {
        include __DIR__ . '/../../admin/includes/header.php';
    }
    
    protected function renderFooter() {
        include __DIR__ . '/../../admin/includes/footer.php';
    }
    
    abstract protected function handleRequest();
    abstract protected function renderContent();
    
    protected function redirect($url, $message = null) {
        if ($message) {
            $_SESSION['message'] = $message;
        }
        header("Location: $url");
        exit;
    }
    
    protected function getMessage() {
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];
            unset($_SESSION['message']);
            return $message;
        }
        return null;
    }
}