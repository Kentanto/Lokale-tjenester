// Admin Panel JavaScript

function openEditModal(id, username, email, is_admin) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIsAdmin').checked = is_admin == 1;
    document.getElementById('editUserModal').classList.add('active');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('active');
}

function submitForm(action) {
    const form = document.getElementById('editUserForm');
    
    // For delete, skip validation
    if (action === 'delete_user') {
        if (!confirm('Are you sure you want to delete this user?')) {
            return;
        }
        // Set action and submit without validation
        document.getElementById('formAction').value = 'delete_user';
        submitFormViaAjax(form);
        return;
    }
    
    // For update, validate required fields
    if (action === 'update_user') {
        const username = document.getElementById('editUsername').value.trim();
        const email = document.getElementById('editEmail').value.trim();
        
        if (!username) {
            alert('Username is required');
            return;
        }
        if (!email) {
            alert('Email is required');
            return;
        }
        
        document.getElementById('formAction').value = 'update_user';
        submitFormViaAjax(form);
    }
}

function submitFormViaAjax(form) {
    const formData = new FormData(form);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        return response.text();
    })
    .then(data => {
        closeEditModal();
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Find all forms that contain approve_post or reject_post actions
    const allForms = document.querySelectorAll('form');
    
    // Try a different approach: find all buttons and attach directly
    const buttons = document.querySelectorAll('button[type="submit"]');
    
    buttons.forEach(function(button, index) {
        const form = button.closest('form');
        if (!form) {
            return;
        }
        
        const actionInput = form.querySelector('input[name="action"]');
        const postIdInput = form.querySelector('input[name="post_id"]');
        const formId = form.id;
        
        if (actionInput && postIdInput && !formId) {
            const action = actionInput.value;
            
            button.addEventListener('click', function(event) {
                event.preventDefault();
                
                if (action === 'reject_post') {
                    if (!confirm('Are you sure you want to reject this job?')) {
                        return;
                    }
                }
                
                const formData = new FormData(form);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    setTimeout(() => window.location.reload(), 500);
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
        }
    });
});
