function editUser(id, username, email, role, status) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function changePassword(id, username) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').value = username;
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}