<?php
namespace ZeroAI\Core;

class FormBuilder {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function renderTable($table, $data, $columns, $actions = ['edit', 'delete']) {
        $html = '<div class="crm-table-wrapper"><table class="crm-table"><thead><tr>';
        
        // Add ID column first
        $html .= '<th class="crm-th">ID</th>';
        
        // Add other columns
        foreach ($columns as $key => $label) {
            $html .= "<th class='crm-th'>{$label}</th>";
        }
        
        // Add actions column
        if (!empty($actions)) {
            $html .= '<th class="crm-th">Actions</th>';
        }
        
        $html .= '</tr></thead><tbody class="crm-tbody">';
        
        // Add rows
        foreach ($data as $row) {
            $html .= '<tr class="crm-tr">';
            $html .= "<td class='crm-td'>{$row['id']}</td>";
            
            foreach ($columns as $key => $label) {
                $value = htmlspecialchars($row[$key] ?? '');
                $html .= "<td class='crm-td'>{$value}</td>";
            }
            
            // Add action buttons
            if (!empty($actions)) {
                $html .= '<td class="crm-td">';
                foreach ($actions as $action) {
                    if ($action === 'edit') {
                        $html .= "<button class='crm-btn crm-btn-edit' onclick='editRecord(\"{$table}\", {$row['id']})'>Edit</button> ";
                    } elseif ($action === 'delete') {
                        // Show Archive for protected tables, Delete for others
                        $protectedTables = ['companies', 'contacts', 'users'];
                        if (in_array($table, $protectedTables)) {
                            $status = $row['status'] ?? 'active';
                            if ($status !== 'archived') {
                                $html .= "<button class='crm-btn crm-btn-archive' onclick='archiveRecord(\"{$table}\", {$row['id']})'>Archive</button>";
                            } else {
                                $html .= "<button class='crm-btn crm-btn-restore' onclick='unarchiveRecord(\"{$table}\", {$row['id']})'>Restore</button>";
                            }
                        } else {
                            $html .= "<button class='crm-btn crm-btn-delete' onclick='deleteRecord(\"{$table}\", {$row['id']})'>Delete</button>";
                        }
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
        $html = "<form method='POST' class='crm-form'>";
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
            
            $html .= "<div class='crm-field'>";
            $html .= "<label class='crm-label'>{$label}</label>";
            
            if ($type === 'select') {
                $html .= "<select name='{$name}' class='crm-select'" . ($required ? ' required' : '') . ">";
                foreach ($config['options'] as $optValue => $optLabel) {
                    $selected = $value == $optValue ? ' selected' : '';
                    $html .= "<option value='{$optValue}'{$selected}>{$optLabel}</option>";
                }
                $html .= "</select>";
            } elseif ($type === 'textarea') {
                $html .= "<textarea name='{$name}' class='crm-textarea'" . ($required ? ' required' : '') . ">{$value}</textarea>";
            } else {
                $html .= "<input type='{$type}' name='{$name}' class='crm-input' value='{$value}'" . ($required ? ' required' : '') . ">";
            }
            
            $html .= "</div>";
        }
        
        $submitText = $action === 'edit' ? 'Update' : 'Add';
        $html .= "<button type='submit' class='crm-btn crm-btn-primary'>{$submitText}</button>";
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
                // SAFETY: Prevent deletion of critical CRM data
                $protectedTables = ['companies', 'contacts', 'users'];
                if (in_array($table, $protectedTables)) {
                    return ['error' => 'Deletion of ' . $table . ' is disabled for data protection. Use archive/deactivate instead.'];
                }
                
                $id = $_POST['id'];
                $this->db->delete($table, ['id' => $id]);
                return ['success' => ucfirst($table) . ' deleted successfully!'];
            }
            
            if ($action === 'archive') {
                // Safe alternative to delete - just mark as inactive
                $id = $_POST['id'];
                $this->db->update($table, ['status' => 'archived'], ['id' => $id]);
                return ['success' => ucfirst($table) . ' archived successfully!'];
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
        
        function archiveRecord(table, id) {
            if (confirm('Archive this record? It will be hidden but can be restored later.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type=\"hidden\" name=\"action\" value=\"archive\">
                    <input type=\"hidden\" name=\"table\" value=\"\${table}\">
                    <input type=\"hidden\" name=\"id\" value=\"\${id}\">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function unarchiveRecord(table, id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type=\"hidden\" name=\"action\" value=\"edit\">
                <input type=\"hidden\" name=\"table\" value=\"\${table}\">
                <input type=\"hidden\" name=\"id\" value=\"\${id}\">
                <input type=\"hidden\" name=\"status\" value=\"active\">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        </script>";
    }
}