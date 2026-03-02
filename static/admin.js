// Admin Panel JavaScript
console.log('admin.js loaded');

let currentPostId = null; // Global variable to track current post being reviewed

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

function openPostApprovalModal(postId, post) {
    currentPostId = postId;
    const content = document.getElementById('postApprovalContent');
    content.innerHTML = `
        <div>
            <p><strong>Title:</strong> ${htmlEscape(post.title)}</p>
            <p><strong>User:</strong> ${htmlEscape(post.username)} (${htmlEscape(post.email)})</p>
            <p><strong>Category:</strong> ${htmlEscape(post.category || 'N/A')}</p>
            <p><strong>Budget:</strong> ${post.budget ? post.budget + ' NOK' : 'Not specified'}</p>
            <p><strong>Location:</strong> ${htmlEscape(post.location || 'N/A')}</p>
            <p><strong>Posted:</strong> ${new Date(post.created_at).toLocaleString()}</p>
            <p><strong>Description:</strong></p>
            <p style="white-space: pre-wrap; word-break: break-word;">${htmlEscape(post.description)}</p>
        </div>
    `;
    document.getElementById('postApprovalModal').classList.add('active');
}

function closePostApprovalModal() {
    document.getElementById('postApprovalModal').classList.remove('active');
    currentPostId = null;
}

function approvePost() {
    if(!currentPostId) return;
    
    fetch('display.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=approve_post&post_id=' + currentPostId,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Post approved!');
            closePostApprovalModal();
            loadPendingPosts();
        } else {
            alert('Error: ' + (data.message || 'Failed to approve post'));
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function rejectPost() {
    if(!currentPostId) return;
    
    if(!confirm('Are you sure you want to reject and delete this post?')) return;
    
    fetch('display.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=reject_post&post_id=' + currentPostId,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            alert('Post rejected and deleted');
            closePostApprovalModal();
            loadPendingPosts();
        } else {
            alert('Error: ' + (data.message || 'Failed to reject post'));
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function loadPendingPosts() {
    fetch('display.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=list_pending_posts',
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            const posts = data.posts || [];
            const container = document.getElementById('pendingJobsList');
            const section = document.getElementById('pendingPostsSection');
            const noMessage = document.getElementById('noPendingMessage');
            
            if(posts.length === 0) {
                section.style.display = 'none';
                noMessage.style.display = 'block';
                container.innerHTML = '';
            } else {
                section.style.display = 'block';
                noMessage.style.display = 'none';
                container.innerHTML = posts.map(post => `
                    <div class="service-card" style="cursor: pointer;" onclick="openPostApprovalModal(${post.id}, ${JSON.stringify(post)})">
                        <h3>${htmlEscape(post.title)}</h3>
                        <p><strong>User:</strong> ${htmlEscape(post.username)}</p>
                        <p><strong>Category:</strong> ${htmlEscape(post.category || 'N/A')}</p>
                        <p><strong>Budget:</strong> ${post.budget ? post.budget + ' NOK' : 'Not specified'}</p>
                        <p style="color: var(--muted); font-size: 12px;">Posted: ${new Date(post.created_at).toLocaleString()}</p>
                        <div class="user-actions">
                            <button class="btn btn-primary" onclick="event.stopPropagation(); openPostApprovalModal(${post.id}, ${JSON.stringify(post)})">Review</button>
                        </div>
                    </div>
                `).join('');
            }
        } else {
            console.error('Error loading pending posts:', data.message);
        }
    })
    .catch(err => console.error('Error:', err.message));
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
