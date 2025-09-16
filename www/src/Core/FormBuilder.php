<?php
namespace ZeroAI\Core;

class FormBuilder {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function renderTable($table, $data, $columns, $actions = ['edit', 'delete']) {
        $html = '<div class="table-responsive"><table class="table table-striped"><thead><tr>';
        
        // Add ID column first
        $html .= '<th>ID</th>';
        
        // Add other columns
        foreach ($columns as $key => $label) {
            $html .= "<th>{$label}</th>";
        }
        
        // Add actions column
        if (!empty($actions)) {
            $html .= '<th>Actions</th>';
        }
        
        $html .= '</tr></thead><tbody>';
        
        // Add rows
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= "<td>{$row['id']}</td>";
            
            foreach ($columns as $key => $label) {
                $value = htmlspecialchars($row[$key] ?? '');
                $html .= "<td>{$value}</td>";
            }
            
            // Add action buttons
            if (!empty($actions)) {
                $html .= '<td>';
                foreach ($actions as $action) {
                    if ($action === 'edit') {
                        $html .= "<button class='btn btn-sm btn-warning' onclick='editRecord(\"{$table}\", {$row['id']})'>Edit</button> ";
                    } elseif ($action === 'delete') {
                        $html .= "<button class='btn btn-sm btn-danger' onclick='deleteRecord(\"{$table}\", {$row['id']})'>Delete</button>";
                    }
                }
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
        return $html;
    }
    
    public function renderForm($table, $fields, $action = 'add', $data = []) {
        $html = "<form method='POST' class='crud-form'>";
        $html .= "<input type='hidden' name='action' value='{$action}'>";
        $html .= "<input type='hidden' name='table' value='{$table}'>";
        
        if ($action === 'edit' && isset($data['id'])) {
            $html .= "<input type='hidden' name='id' value='{$data['id']}'>";
        }
        
        foreach ($fields as $name => $config) {
            $value = $data[$name] ?? '';
            $required = $config['required'] ?? false;
            $type = $config['type'] ?? 'text';
            $label = $config['label'] ?? ucfirst($name);
            
            $html .= "<div class='mb-3'>";
            $html .= "<label class='form-label'>{$label}</label>";
            
            if ($type === 'select') {
                $html .= "<select name='{$name}' class='form-select'" . ($required ? ' required' : '') . ">";
                foreach ($config['options'] as $optValue => $optLabel) {
                    $selected = $value == $optValue ? ' selected' : '';
                    $html .= "<option value='{$optValue}'{$selected}>{$optLabel}</option>";
                }
                $html .= "</select>";
            } elseif ($type === 'textarea') {
                $html .= "<textarea name='{$name}' class='form-control'" . ($required ? ' required' : '') . ">{$value}</textarea>";
            } else {
                $html .= "<input type='{$type}' name='{$name}' class='form-control' value='{$value}'" . ($required ? ' required' : '') . ">";
            }
            
            $html .= "</div>";
        }
        
        $submitText = $action === 'edit' ? 'Update' : 'Add';
        $html .= "<button type='submit' class='btn btn-primary'>{$submitText}</button>";
        $html .= "</form>";
        
        return $html;
    }
    
    public function handleRequest($table, $fields) {
        if (!$_POST || !isset($_POST['action']) || !isset($_POST['table'])) {
            return null;
        }
        
        // Only handle requests specifically for this table
        if ($_POST['table'] !== $table) {
            return null;
        }
        
        $action = $_POST['action'];
        
        try {
            if ($action === 'add') {
                $data = [];
                foreach ($fields as $name => $config) {
                    if (isset($_POST[$name])) {
                        $data[$name] = $_POST[$name];
                    }
                }
                $this->db->insert($table, $data);
                return ['success' => ucfirst($table) . ' added successfully!'];
            }
            
            if ($action === 'edit') {
                $id = $_POST['id'];
                $data = [];
                foreach ($fields as $name => $config) {
                    if (isset($_POST[$name])) {
                        $data[$name] = $_POST[$name];
                    }
                }
                $this->db->update($table, $data, ['id' => $id]);
                return ['success' => ucfirst($table) . ' updated successfully!'];
            }
            
            if ($action === 'delete') {
                $id = $_POST['id'];
                $this->db->delete($table, ['id' => $id]);
                return ['success' => ucfirst($table) . ' deleted successfully!'];
            }
            
        } catch (Exception $e) {
            return ['error' => 'Error: ' . $e->getMessage()];
        }
        
        return null;
    }
    
    public function renderScript() {
        return "
        <script>
        function editRecord(table, id) {
            window.location.href = window.location.pathname + '?action=edit&id=' + id;
        }
        
        function deleteRecord(table, id) {
            if (confirm('Are you sure you want to delete this record?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type=\"hidden\" name=\"action\" value=\"delete\">
                    <input type=\"hidden\" name=\"table\" value=\"\${table}\">
                    <input type=\"hidden\" name=\"id\" value=\"\${id}\">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        </script>";
    }
}