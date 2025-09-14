<?php
namespace Models;

class BaseModel {
    protected $db;
    
    public function __construct() {
        $database = new \Core\Database();
        $this->db = $database->getConnection();
    }
}
?>