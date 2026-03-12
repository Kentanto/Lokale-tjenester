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

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, attaching listener');
    const form = document.getElementById('editUserForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            const submitter = event.submitter;
            
            if (submitter && submitter.value === 'delete_user') {
                if (!confirm('Are you sure you want to delete this user?')) {
                    return;
                }
            }
            
            console.log('Form submitted, submitter:', submitter);
            console.log('Submitter name:', submitter ? submitter.name : 'none');
            console.log('Submitter value:', submitter ? submitter.value : 'none');
            
            const formData = new FormData(this);
            
            // Add the button's name/value if it has one
            if (submitter && submitter.name) {
                formData.set(submitter.name, submitter.value);
                console.log('Added to FormData:', submitter.name, '=', submitter.value);
            }
            
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
        });
    } else {
        console.error('Form not found');
    }
    
    // Attach handlers for approve/reject post forms
    document.querySelectorAll('form input[name="action"]').forEach(function(actionInput) {
        const form = actionInput.closest('form');
        if (form && !form.id) {  // Only attach if not already handled (skip editUserForm)
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                const action = actionInput.value;
                const postId = form.querySelector('input[name="post_id"]');
                
                if (!postId) {
                    alert('Error: Missing post ID');
                    return;
                }
                
                if (action === 'reject_post') {
                    if (!confirm('Are you sure you want to reject this job?')) {
                        return;
                    }
                }
                
                console.log('Submitting form with action:', action, 'post_id:', postId.value);
                
                fetch('admin.php', {
                    method: 'POST',
                    body: new FormData(this),
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
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error);
                });
            });
        }
    });
});
