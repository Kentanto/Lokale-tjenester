// Admin Panel JavaScript
console.log('admin.js loaded');

function openEditModal(id, username, email, is_admin) {
    console.log('Opening edit modal for user:', id, username, email, is_admin);
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIsAdmin').checked = is_admin == 1;
    document.getElementById('editUserModal').classList.add('active');
}

function closeEditModal() {
    console.log('Closing edit modal');
    document.getElementById('editUserModal').classList.remove('active');
}

function submitForm(action) {
    console.log('submitForm called with action:', action);
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
    
    console.log('FormData contents:');
    for (let [key, value] of formData.entries()) {
        console.log(key, '=', value);
    }
    
    fetch('admin.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text();
    })
    .then(data => {
        console.log('Response data:', data);
        closeEditModal();
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    
    // Find all forms that contain approve_post or reject_post actions
    const allForms = document.querySelectorAll('form');
    console.log('Total forms on page:', allForms.length);
    
    // Try a different approach: find all buttons and attach directly
    const buttons = document.querySelectorAll('button[type="submit"]');
    console.log('Found', buttons.length, 'submit buttons');
    
    buttons.forEach(function(button, index) {
        const form = button.closest('form');
        if (!form) {
            console.log('Button', index, 'has no form parent');
            return;
        }
        
        const actionInput = form.querySelector('input[name="action"]');
        const postIdInput = form.querySelector('input[name="post_id"]');
        const formId = form.id;
        
        if (actionInput && postIdInput && !formId) {
            const action = actionInput.value;
            console.log('Button', index, 'attached - action:', action);
            
            button.addEventListener('click', function(event) {
                event.preventDefault();
                console.log('Button clicked! Action:', action);
                
                if (action === 'reject_post') {
                    if (!confirm('Are you sure you want to reject this job?')) {
                        console.log('Reject cancelled by user');
                        return;
                    }
                }
                
                console.log('Submitting with action:', action, 'post_id:', postIdInput.value);
                const formData = new FormData(form);
                
                console.log('FormData:');
                for (let [key, value] of formData.entries()) {
                    console.log('  ', key, '=', value);
                }
                
                fetch('admin.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    console.log('Response:', data);
                    setTimeout(() => window.location.reload(), 500);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            });
        }
    });
    
    console.log('Event listener setup complete');
});
