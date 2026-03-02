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
            console.log('Form submitted');
            const formData = new FormData(this);
            // Add the submitter's name/value
            if (submitter && submitter.name) {
                formData.append(submitter.name, submitter.value);
            }
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }
            fetch('admin.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(data => {
                console.log('Response data length:', data.length);
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
});
