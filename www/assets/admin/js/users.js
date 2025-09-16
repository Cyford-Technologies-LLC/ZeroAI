function editUser(id, username, email, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    document.getElementById('editModal').style.display = 'block';
}

function changePassword(id, username) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').value = username;
    document.getElementById('new_password').value = '';
    document.getElementById('passwordModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('password_user_id').value = '';
    document.getElementById('password_username').value = '';
    document.getElementById('new_password').value = '';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'editModal') {
        closeModal();
    }
    if (e.target.id === 'passwordModal') {
        closePasswordModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closePasswordModal();
    }
});