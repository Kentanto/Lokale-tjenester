
// ============================================
// Terms of Service Modal - localStorage control
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const termsModal = document.getElementById('termsModal');
    const acceptTermsBtn = document.getElementById('acceptTermsBtn');
    
    if (termsModal && acceptTermsBtn) {
        // Check if user has already accepted terms
        const hasAcceptedTerms = localStorage.getItem('termsAccepted');
        
        if (!hasAcceptedTerms) {
            // Show the modal if not accepted
            termsModal.classList.add('active');
        }
        
        // Handle accept button click
        acceptTermsBtn.addEventListener('click', function() {
            // Save to localStorage that user accepted
            localStorage.setItem('termsAccepted', 'true');
            // Hide the modal
            termsModal.classList.remove('active');
        });
        
        // Prevent closing modal by clicking outside (modal must be explicitly accepted)
        termsModal.addEventListener('click', function(e) {
            if (e.target === termsModal) {
                e.preventDefault();
            }
        });
    }
});

function toggleDropdown(){
    const dropdownMenu=document.getElementById('dropdownMenu');
    if(dropdownMenu) dropdownMenu.classList.toggle('active');
}

// Use event delegation so the listener works even if button is replaced
document.addEventListener('click',function(e){
    if(e.target.closest('.user-btn')){
        e.stopPropagation();
        toggleDropdown();
    }
});

document.addEventListener('click',function(e){
    const userProfile=document.querySelector('.user-profile');
    const dropdownMenu=document.getElementById('dropdownMenu');
    if(userProfile && dropdownMenu && !userProfile.contains(e.target)){
        dropdownMenu.classList.remove('active');
    }
});



async function parseJsonResponse(res){
    // read text and try to parse JSON; return object with status and message on failure
    let text = '';
    try{ text = await res.text(); }catch(e){ return {status:'error', message:'No response from server'}; }
    if(!text) return {status:'error', message:'Empty server response'};
    try{ return JSON.parse(text); }catch(e){
        return {status:'error', message: text};
    }
}

function showFormMessage(form, message, status){
    if (!form) return;

    const container = form.querySelector('.form-message');
    if (!container) {
        alert(message);
        return;
    }

    container.textContent = message || '';
    container.classList.remove('success', 'error');
    container.classList.add(status === 'success' ? 'success' : 'error');
}

// Helper: escape HTML special characters
function escapeHtml(s){ if(!s && s!==0) return ''; return String(s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

// Helper: render star rating display (read-only)
function renderStarDisplay(rating, count = 0) {
    const fullStars = Math.floor(rating);
    const hasHalf = rating % 1 >= 0.5;
    let html = '<div class="star-display">';
    for(let i = 0; i < 5; i++) {
        if(i < fullStars) html += '<span class="star filled">★</span>';
        else if(i === fullStars && hasHalf) html += '<span class="star half">★</span>';
        else html += '<span class="star empty">★</span>';
    }
    html += `<span class="rating-text"> ${rating.toFixed(1)} (${count})</span></div>`;
    return html;
}

// Helper: render star rating input (for submitting ratings)
function renderStarInput(onStarClick) {
    let html = '<div class="star-input" id="starInput">';
    for(let i = 1; i <= 5; i++) {
        html += `<span class="star-btn" data-rating="${i}" style="cursor:pointer;font-size:24px;color:#ddd;margin:0 4px;">★</span>`;
    }
    html += '</div>';
    const container = document.createElement('div');
    container.innerHTML = html;
    const stars = container.querySelectorAll('.star-btn');
    stars.forEach(star => {
        star.addEventListener('click', () => onStarClick(parseInt(star.getAttribute('data-rating'))));
        star.addEventListener('mouseover', () => {
            const rating = parseInt(star.getAttribute('data-rating'));
            stars.forEach((s, idx) => {
                s.style.color = idx < rating ? '#FFA400' : '#ddd';
            });
        });
    });
    container.addEventListener('mouseout', () => {
        stars.forEach(s => s.style.color = '#ddd');
    });
    return container.innerHTML;
}

// Render jobs list
// Helper: format relative time with specific intervals
function formatRelativeTime(isoDate){
    const now = new Date();
    const date = new Date(isoDate);
    const diffMs = now - date;
    const diffMinutes = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if(diffMinutes < 60){
        // Up to 1 hour: 10 minute intervals
        const interval = Math.round(diffMinutes / 10) * 10;
        if(interval === 0) return 'nettopp';
        return interval + ' min siden';
    } else if(diffHours < 24){
        // 1-24 hours: every hour
        return diffHours + ' time' + (diffHours > 1 ? 'r' : '') + ' siden';
    } else {
        // After 24 hours: every day
        return diffDays + ' dag' + (diffDays > 1 ? 'er' : '') + ' siden';
    }
}

function renderJobs(jobs, container){
    if(!container) return;
    if(!jobs || jobs.length === 0){
        container.innerHTML = `
            <div class="empty-state">
                <h3>No jobs found</h3>
                <p>Try widening your search or create a new job to attract local providers.</p>
                <div style="margin-top:12px;">
                    <a href="pages.php?page=create_job" class="btn btn-primary">Post a Job</a>
                </div>
            </div>`;
        return;
    }
    let out = '';
    jobs.forEach(j=>{
        let imageHtml = j.image ? `<img src="${j.image}" alt="${escapeHtml(j.title)}" style="width:100%;height:200px;object-fit:cover;border-radius:6px;margin-bottom:12px;">` : '';
        let profilePictureHtml = '';
        if(j.profile_picture){
            profilePictureHtml = `<img src="data:${j.profile_picture}" alt="${escapeHtml(j.username)}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;margin-right:8px;">`;
        } else {
            profilePictureHtml = `<div style="width:32px;height:32px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;font-weight:bold;margin-right:8px;font-size:14px;">${escapeHtml(j.username.charAt(0))}</div>`;
        }
        out += `<article class="service-card" style="margin-bottom:12px;cursor:pointer;" onclick="openJobDetail(${j.id})">
            ${imageHtml}
            <h3>${escapeHtml(j.title)}</h3>
            <p>${escapeHtml(j.description.substring(0,150)) + (j.description.length > 150 ? '...' : '')}</p>
            <div style="display:flex;align-items:center;color:#666;font-size:13px;margin-bottom:12px;">
                ${profilePictureHtml}
                <span>${escapeHtml(j.username)}</span>
            </div>
            <p style="color:#666;font-size:13px;"> — ${escapeHtml(j.location||'')}</p>
            <p style="font-weight:600;margin-top:6px">Budsjett: ${j.budget ? (parseInt(j.budget) + ' NOK') : 'Forhandlingsbart'}</p>
            <div style="font-size:12px;color:#888;margin-top:6px">Lagt ut: ${formatRelativeTime(j.created_at)}</div>
        </article>`;
    });
    container.innerHTML = out;
}

// Load jobs from server
async function loadJobs(filters={}){
    const container = document.getElementById('jobsList');
    if(!container) return;
    const fd = new FormData();
    fd.append('action','list_jobs');
    if(filters.q) fd.append('q', filters.q);
    if(filters.category) fd.append('category', filters.category);
    if(filters.location) fd.append('location', filters.location);
    if(filters.min_budget) fd.append('min_budget', filters.min_budget);
    if(filters.max_budget) fd.append('max_budget', filters.max_budget);
    let res;
    try{ res = await fetch('/display.php',{method:'POST', body: fd, credentials:'same-origin'}); }
    catch(err){ container.innerHTML = '<div class="note">Network error</div>'; return; }
    let data = await parseJsonResponse(res);
    if(data.status === 'success') renderJobs(data.jobs, container);
    else container.innerHTML = `<div class="note">${escapeHtml(data.message || 'Error')}</div>`;
}

// Render user posts on dashboard
function renderUserPosts(posts, container){
    if(!container) return;
    if(!posts || posts.length === 0){
        container.innerHTML = `
            <div class="empty-state">
                <h3>Ingen annonser funnet</h3>
                <p>Du har ikke opprettet noen jobber ennå.</p>
                <div style="margin-top:12px;">
                    <a href="pages.php?page=create_job" class="btn btn-primary">Lag en jobbannonse</a>
                </div>
            </div>`;
        return;
    }
    let out = '';
    posts.forEach(p=>{
        let imageHtml = p.image ? `<img src="${p.image}" alt="${escapeHtml(p.title)}" style="width:100%;height:150px;object-fit:cover;border-radius:6px;margin-bottom:8px;">` : '';
        out += `<article class="service-card" style="margin-bottom:12px;position:relative;" data-post-id="${p.id}">
            ${imageHtml}
            <h3>${escapeHtml(p.title)}</h3>
            <p>${escapeHtml(p.description.substring(0,150)) + (p.description.length>150?'...':'')}</p>
            <p style="font-size:13px;color:#666;">Status: ${escapeHtml(p.status)}</p>
            <div style="margin-top:8px;">
                <button class="btn btn-secondary take-down-btn" data-id="${p.id}">Ta ned</button>
                <button class="btn btn-danger delete-btn" data-id="${p.id}">Slett</button>
            </div>
        </article>`;
    });
    container.innerHTML = out;
}

// Load dashboard metrics and posts
async function loadDashboard(){
    const statActive = document.getElementById('statActive');
    const statOrders = document.getElementById('statOrders');
    const statRating = document.getElementById('statRating');
    const postsContainer = document.getElementById('userPostsContainer');
    if(!postsContainer) return;
    const fd = new FormData();
    fd.append('action','dashboard_data');
    let res;
    try{ res = await fetch('/display.php',{method:'POST',body:fd,credentials:'same-origin'}); }
    catch(err){ postsContainer.innerHTML = '<div class="note">Network error</div>'; return; }
    let data = await parseJsonResponse(res);
    if(data.status==='success'){
        const stats = data.stats || {};
        if(statActive) statActive.textContent = stats.approved_posts ?? stats.total_posts ?? '0';
        if(statOrders) statOrders.textContent = stats.total_orders ?? '0';
        if(statRating) statRating.textContent = stats.rating ?? '0';
        renderUserPosts(data.posts, postsContainer);
    } else {
        postsContainer.innerHTML = `<div class="note">${escapeHtml(data.message||'Feil')}</div>`;
    }
}

// Open job detail modal
async function openJobDetail(postId){
    const modal = document.getElementById('jobDetailModal');
    const detailContent = document.getElementById('jobDetailContent');
    if(!modal || !detailContent) return;
    
    const fd = new FormData();
    fd.append('action', 'get_post_detail');
    fd.append('post_id', postId);
    
    detailContent.innerHTML = '<p>Loading...</p>';
    modal.style.display = 'flex';
    
    try{
        const res = await fetch('/display.php', {method:'POST', body:fd, credentials:'same-origin'});
        const data = await res.json();
        
        if(data.status === 'success'){
            const p = data.post;
            let imageHtml = '';
            if(p.images && Array.isArray(p.images) && p.images.length){
                const main = p.images[0];
                const thumbs = p.images.map((src, idx) => `<button class="job-thumb" data-idx="${idx}" style="width:72px;height:72px;background-size:cover;background-position:center;border-radius:6px;border:1px solid #eee;" aria-label="Image ${idx+1}" data-src="${src}"></button>`).join('');
                imageHtml = `<div class="job-detail-gallery" style="margin-bottom:16px;">
                    <div class="job-detail-main" style="margin-bottom:8px;"><img src="${main}" alt="${escapeHtml(p.title)}" style="width:100%;height:auto;max-height:400px;object-fit:cover;border-radius:8px;"></div>
                    <div class="job-detail-thumbs" style="display:none;gap:8px;flex-wrap:wrap;">${thumbs}</div>
                </div>`;
            } else if(p.image){
                imageHtml = `<img src="${p.image}" alt="${escapeHtml(p.title)}" style="width:100%;max-height:400px;object-fit:cover;border-radius:8px;margin-bottom:16px;">`;
            }

            detailContent.innerHTML = `
                ${imageHtml}
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;cursor:pointer;" id="jobDetailProfileSection" data-user-id="${p.user_id}" onclick="openUserProfile(parseInt(this.getAttribute('data-user-id')));">
                    ${p.profile_picture ? `<img src="${p.profile_picture}" alt="${escapeHtml(p.username)}" style="width:50px;height:50px;border-radius:50%;object-fit:cover;flex-shrink:0;">` : `<div style="width:50px;height:50px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:20px;flex-shrink:0;">${escapeHtml(p.username.charAt(0))}</div>`}
                    <div>
                        <h3 style="margin:0;cursor:pointer;">${escapeHtml(p.username)}</h3>
                        <div style="margin-top:4px;">${renderStarDisplay(p.creator_rating || 0, p.creator_rating_count || 0)}</div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div><strong>Budsjett:</strong> <span style="font-size:18px;color:var(--green);">${p.budget ? (parseInt(p.budget) + ' NOK') : 'Forhandlingsbart'}</span></div>
                    <div><strong>Kategori:</strong> ${escapeHtml(p.category||'Ikke spesifisert')}</div>
                    <div style="grid-column:1/-1;"><strong>Lagt ut:</strong> ${formatRelativeTime(p.created_at)}</div>
                </div>
                ${p.contact_info ? `<div style="background:#f5f5f5;padding:12px;border-radius:6px;margin-bottom:20px;border-left:4px solid var(--green);"><strong>Kontakt:</strong> ${escapeHtml(p.contact_info)}</div>` : ''}
                <hr style="margin:20px 0;border:none;border-top:1px solid var(--off-white);">
                <h3 style="margin-top:0;">Beskrivelse</h3>
                <p style="line-height:1.6;white-space:pre-wrap;">${escapeHtml(p.description)}</p>
                <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--off-white);">
                    <div id="ratingFormSection"></div>
                    ${p.viewer_is_admin ? `<div style="margin-top:16px;"><button id="deletePostBtn" class="btn btn-primary" style="background:#d32f2f;" data-post-id="${p.id}">Slett post (Admin)</button></div>` : ''}
                </div>
            `;

            // Wire thumbnail clicks to swap main image
            const thumbContainer = detailContent.querySelector('.job-detail-thumbs');
            if(thumbContainer){
                const mainImg = detailContent.querySelector('.job-detail-main img');
                thumbContainer.addEventListener('click', function(ev){
                    const btn = ev.target.closest('.job-thumb');
                    if(!btn) return;
                    const src = btn.getAttribute('data-src');
                    if(mainImg && src) mainImg.src = src;
                });
            }

            // Wire delete button click for admins
            const deleteBtn = detailContent.querySelector('#deletePostBtn');
            if(deleteBtn){
                deleteBtn.addEventListener('click', async function(){
                    if(!confirm('Are you sure you want to delete this post?')) return;
                    
                    const postId = this.getAttribute('data-post-id');
                    const fd = new FormData();
                    fd.append('action', 'delete_post');
                    fd.append('post_id', postId);
                    
                    try{
                        const res = await fetch('/display.php', {method:'POST', body:fd, credentials:'same-origin'});
                        const data = await res.json();
                        
                        if(data.status === 'success'){
                            modal.style.display = 'none';
                            // Reload jobs list if it exists
                            if(document.getElementById('jobsList')){
                                loadJobs();
                            }
                            alert('Post deleted successfully');
                        } else {
                            alert('Error: ' + (data.message || 'Failed to delete post'));
                        }
                    } catch(err){
                        alert('Error: ' + err.message);
                    }
                });
            }

            // Add rating form if user is logged in (fetch to check isLoggedIn status from DOM or session)
            const ratingFormSection = detailContent.querySelector('#ratingFormSection');
            if(ratingFormSection && p.user_id) {
                // Check if there's a user element in navbar to detect logged-in state
                const userBtn = document.querySelector('.user-btn');
                if(userBtn && userBtn.textContent.trim() !== '') {
                    // User is logged in, show rating form
                    let selectedRating = 0;
                    const formHtml = `
                        <h4 style="margin-bottom:12px;">Vurdèr denne personen</h4>
                        <div id="ratingStarsContainer" style="margin-bottom:12px;"></div>
                        <textarea id="reviewText" placeholder="Valgfritt: legg til en kommentar" style="width:100%;padding:8px;border:1px solid #e0e0e0;border-radius:6px;font-size:14px;font-family:inherit;margin-bottom:12px;" maxlength="500" rows="3"></textarea>
                        <button id="submitRatingBtn" class="btn btn-primary" style="width:100%;opacity:0.5;cursor:not-allowed;" disabled>Lagre vurdering</button>
                        <div class="form-message" aria-live="polite" style="margin-top:12px;"></div>
                    `;
                    ratingFormSection.innerHTML = formHtml;
                    
                    // Render star input
                    const starsContainer = ratingFormSection.querySelector('#ratingStarsContainer');
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = renderStarInput((rating) => {
                        selectedRating = rating;
                        // Update UI
                        const stars = starsContainer.querySelectorAll('.star-btn');
                        stars.forEach((s, idx) => {
                            s.style.color = idx < rating ? '#FFA400' : '#ddd';
                        });
                        // Enable submit button
                        document.getElementById('submitRatingBtn').disabled = false;
                        document.getElementById('submitRatingBtn').style.opacity = '1';
                        document.getElementById('submitRatingBtn').style.cursor = 'pointer';
                    });
                    starsContainer.innerHTML = tempDiv.innerHTML;
                    
                    // Submit rating
                    const submitBtn = ratingFormSection.querySelector('#submitRatingBtn');
                    if(submitBtn) {
                        submitBtn.addEventListener('click', async () => {
                            if(selectedRating === 0) {
                                alert('Please select a rating');
                                return;
                            }
                            
                            const review = ratingFormSection.querySelector('#reviewText').value;
                            const fd = new FormData();
                            fd.append('action', 'submit_rating');
                            fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                            fd.append('ratee_id', p.user_id);
                            fd.append('rating', selectedRating);
                            fd.append('review', review);
                            
                            try {
                                const res = await fetch('/display.php', {method:'POST', body:fd, credentials:'same-origin'});
                                const data = await res.json();
                                const msgDiv = ratingFormSection.querySelector('.form-message');
                                
                                if(data.status === 'success') {
                                    msgDiv.textContent = 'Vurderingen ble lagret!';
                                    msgDiv.classList.add('success');
                                    submitBtn.disabled = true;
                                    submitBtn.style.opacity = '0.5';
                                } else {
                                    msgDiv.textContent = 'Feil: ' + (data.message || 'Kunne ikke lagre vurdering');
                                    msgDiv.classList.add('error');
                                }
                            } catch(err) {
                                const msgDiv = ratingFormSection.querySelector('.form-message');
                                msgDiv.textContent = 'Nettverksfeil';
                                msgDiv.classList.add('error');
                            }
                        });
                    }
                }
            }
        } else {
            detailContent.innerHTML = `<p style="color:red;">Error: ${escapeHtml(data.message)}</p>`;
        }
    } catch(err){
        detailContent.innerHTML = `<p style="color:red;">Network error loading details</p>`;
    }
}

// Helper: disable/enable form (buttons and inputs)
function disableForm(form, disabled){
    [...form.querySelectorAll('input,button,textarea')].forEach(el=>{
        if(el.type === 'hidden') return;
        el.disabled = disabled;
        if(el.tagName === 'BUTTON') el.setAttribute('aria-disabled', disabled);
    });
}

// View user profile with bio and all their jobs
async function openUserProfile(userId){
    // Create profile page overlay
    let profilePage = document.getElementById('userProfilePage');
    if(!profilePage){
        profilePage = document.createElement('div');
        profilePage.id = 'userProfilePage';
        profilePage.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:white;z-index:1000;overflow-y:auto;';
        profilePage.innerHTML = `
            <div style="padding:20px;max-width:900px;margin:0 auto;">
                <button style="position:fixed;top:20px;right:20px;background:none;border:none;font-size:28px;cursor:pointer;z-index:1001;" onclick="document.getElementById('userProfilePage').remove();">&times;</button>
                <div id="userProfileContent" style="padding-top:40px;">
                    <p>Loading profile...</p>
                </div>
            </div>
        `;
        document.body.appendChild(profilePage);
    }

    const profileContent = document.getElementById('userProfileContent');
    profileContent.innerHTML = '<p>Loading profile...</p>';
    
    const fd = new FormData();
    fd.append('action', 'view_user_profile');
    fd.append('user_id', userId);
    
    try {
        const res = await fetch('/display.php', {method:'POST', body:fd, credentials:'same-origin'});
        const data = await res.json();
        
        if(data.status === 'success'){
            const user = data.user;
            const jobs = data.jobs || [];
            console.log('User profile data:', user);
            
            let profilePicHtml = '';
            if(user.profile_picture){
                profilePicHtml = `<img src="${user.profile_picture}" alt="${escapeHtml(user.username)}" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid #ddd;">`;
            } else {
                profilePicHtml = `<div style="width:100px;height:100px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:bold;border:3px solid #ddd;">${escapeHtml(user.username.charAt(0))}</div>`;
            }
            
            let jobsHtml = '';
            if(jobs.length > 0){
                jobsHtml = '<h3 style="margin-top:24px;">Andre jobber av ' + escapeHtml(user.username) + '</h3>';
                jobs.forEach(job => {
                    jobsHtml += `<div class="service-card" style="margin-bottom:12px;cursor:pointer;" onclick="document.getElementById('userProfilePage').remove(); openJobDetail(${job.id});">
                        <h4>${escapeHtml(job.title)}</h4>
                        <p style="font-size:13px;color:#666;margin:4px 0;">${escapeHtml(job.category || 'Ikke spesifisert')} • ${escapeHtml(job.location || 'Sted ikke spesifisert')}</p>
                        <p style="font-size:13px;margin:4px 0;"><strong>${job.budget ? (parseInt(job.budget) + ' NOK') : 'Forhandlingsbart'}</strong></p>
                        <p style="font-size:13px;color:#999;">${formatRelativeTime(job.created_at)}</p>
                    </div>`;
                });
            } else {
                jobsHtml = '<p style="margin-top:24px;color:#666;"><em>Ingen andre jobber fra denne brukeren.</em></p>';
            }
            
            profileContent.innerHTML = `
                <div style="text-align:center;padding-bottom:24px;border-bottom:1px solid var(--off-white);">
                    ${profilePicHtml}
                    <h2 style="margin:12px 0 4px 0;">${escapeHtml(user.username)}</h2>
                </div>
                ${user.bio ? `<div style="margin:20px 0;padding:16px;background:#f9f9f9;border-radius:6px;border-left:4px solid var(--green);"><h3 style="margin:0 0 12px 0;">Om</h3><p style="margin:0;white-space:pre-wrap;line-height:1.6;">${escapeHtml(user.bio)}</p></div>` : '<p style="margin:20px 0;color:#999;"><em>Ingen bio ennå.</em></p>'}
                ${jobsHtml}
            `;
        } else {
            profileContent.innerHTML = `<p style="color:red;">Error: ${escapeHtml(data.message)}</p>`;
        }
    } catch(err){
        profileContent.innerHTML = `<p style="color:red;">Network error loading profile</p>`;
    }
}



document.addEventListener('DOMContentLoaded',function(){
    // Confirmation modal helper
    function ensureConfirmModal(){
        if(document.getElementById('confirmOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'confirmOverlay';
        overlay.className = 'confirm-overlay';
        overlay.innerHTML = `
            <div class="confirm-box">
                <h3 id="confirmTitle">Success</h3>
                <p id="confirmMessage">Action completed successfully.</p>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;">
                    <div class="small-muted">Redirecting in <span id="confirmCountdown">2</span>s</div>
                    <div class="confirm-actions">
                        <button id="confirmNow" class="btn btn-primary">Go now</button>
                        <button id="confirmStay" class="btn btn-secondary">Stay</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(overlay);
    }

    function showConfirmation(title, message, redirectUrl, autoMs=2000, verifyUrl=null, verifyText='Go now', closeText='Stay'){
        ensureConfirmModal();
        const overlay = document.getElementById('confirmOverlay');
        const titleEl = overlay.querySelector('#confirmTitle');
        const msgEl = overlay.querySelector('#confirmMessage');
        const nowBtn = overlay.querySelector('#confirmNow');
        const stayBtn = overlay.querySelector('#confirmStay');
        const countEl = overlay.querySelector('#confirmCountdown');
        const countdownDiv = countEl.closest('.small-muted');

        titleEl.textContent = title || 'Success';
        msgEl.textContent = message || '';
        
        // Only show countdown and redirect if redirectUrl is provided
        if(redirectUrl) {
            let remaining = Math.ceil(autoMs/1000);
            countEl.textContent = remaining;
            countdownDiv.style.display = 'block';
            overlay.classList.add('active');

            let cancelled = false;
            const interval = setInterval(()=>{
                remaining -= 1; if(remaining<=0) remaining=0; countEl.textContent = remaining;
            },1000);

            const timeout = setTimeout(()=>{
                if(!cancelled){ overlay.classList.remove('active'); window.location.href = redirectUrl; }
            }, autoMs);

            nowBtn.onclick = ()=>{ clearTimeout(timeout); clearInterval(interval); overlay.classList.remove('active'); window.location.href = redirectUrl; };
            stayBtn.onclick = ()=>{ cancelled = true; clearTimeout(timeout); clearInterval(interval); overlay.classList.remove('active'); };
            stayBtn.style.display = 'block';
        } else if(verifyUrl) {
            // Show verification button and close button
            countdownDiv.style.display = 'none';
            stayBtn.style.display = 'block';
            nowBtn.textContent = verifyText;
            stayBtn.textContent = closeText;
            nowBtn.onclick = ()=>{ overlay.classList.remove('active'); window.location.href = verifyUrl; };
            stayBtn.onclick = ()=>{ overlay.classList.remove('active'); };
            overlay.classList.add('active');
        } else {
            // No redirect, just show confirmation message with close button
            countdownDiv.style.display = 'none';
            stayBtn.style.display = 'none';
            nowBtn.textContent = 'Close';
            nowBtn.onclick = ()=>{ overlay.classList.remove('active'); };
            overlay.classList.add('active');
        }
    }

    // Handle reset limit button (for admins) - use event delegation
    document.addEventListener("click", async e => {
        if(e.target.id !== "resetLimitBtn") return;
        
        e.preventDefault();
        let fd = new FormData();
        fd.append('action', 'reset_post_limit');
        
        try {
            let res = await fetch('/display.php', {method: 'POST', body: fd, credentials: 'same-origin'});
            let data = await parseJsonResponse(res);
            
            if(data.status === 'success') {
                location.reload();
            } else {
                alert('Reset failed: ' + data.message);
            }
        } catch(err) {
            alert('Error: ' + err.message);
        }
    });

    // Initialize create job form with form data caching
    let createPostForm = document.getElementById("createPostForm");
    if(createPostForm) {
        const cacheKey = 'createPostFormCache';
        
        // Restore from localStorage on page load
        try {
            const cached = localStorage.getItem(cacheKey);
            if(cached) {
                const data = JSON.parse(cached);
                const fieldsToRestore = ['title', 'description', 'category', 'budget', 'location', 'contact_info'];
                fieldsToRestore.forEach(fieldName => {
                    const input = createPostForm.querySelector(`[name="${fieldName}"]`);
                    if(input && data[fieldName]) {
                        input.value = data[fieldName];
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
            }
        } catch(e) {}
        
        // Save to localStorage on input
        let saveTimeout;
        createPostForm.addEventListener('input', function(e) {
            if(e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    try {
                        const data = {};
                        const fieldsToSave = ['title', 'description', 'category', 'budget', 'location', 'contact_info'];
                        fieldsToSave.forEach(fieldName => {
                            const input = createPostForm.querySelector(`[name="${fieldName}"]`);
                            if(input) data[fieldName] = input.value;
                        });
                        localStorage.setItem(cacheKey, JSON.stringify(data));
                    } catch(e) {}
                }, 300);
            }
        });
        
        // Clear cache on successful submit
        const originalHandler = createPostForm.onsubmit;
        createPostForm.addEventListener('submit', function(e) {
            // This will run after form submission completes
            if(e.target.clearFormCache) {
                e.target.clearFormCache = function() {
                    try { localStorage.removeItem(cacheKey); } catch(e) {}
                };
            }
        });
        
        let remainingPosts = parseInt(createPostForm.dataset.remainingPosts || '3');
        let submitBtn = createPostForm.querySelector('button[type="submit"]');
        
        if(remainingPosts <= 0 && submitBtn) {
            submitBtn.disabled = true;
            submitBtn.title = 'Daglig grense nådd. Prøv igjen i morgen!';
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
        }
        
        // Add character counter for description
        let descField = createPostForm.querySelector('#job-desc');
        let descCounter = createPostForm.querySelector('#desc-counter');
        if(descField && descCounter) {
            descField.addEventListener('input', function() {
                descCounter.textContent = this.value.length + '/2000';
            });
            descCounter.textContent = descField.value.length + '/2000';
        }
        
        // Add character counter for contact info
        let contactField = createPostForm.querySelector('#job-contact');
        let contactCounter = createPostForm.querySelector('#contact-counter');
        if(contactField && contactCounter) {
            contactField.addEventListener('input', function() {
                contactCounter.textContent = this.value.length + '/30';
            });
            contactCounter.textContent = contactField.value.length + '/30';
        }
    }

    // Create job/post form handler
    document.getElementById("createPostForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let form = e.target;
        
        // Debug: log files being sent
        let fd = new FormData(form);
        let hasImage = false;
        for(let [key,val] of fd){
            if(key === 'image'){ console.log('Form has image field:', val.name || 'file', val.size || 'no size'); hasImage = true; }
        }
        if(!hasImage) console.log('WARNING: No image field in FormData!');
        
        fd.append('action','create_post');
        disableForm(form, true);
        
        let res;
        try{ 
            res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); 
        } catch(err){ 
            console.error('Fetch error:', err);
            showFormMessage(form,'Network error: ' + err.message,'error'); 
            disableForm(form,false); 
            return; 
        }
        
        let data = await parseJsonResponse(res);
        console.log('Server response:', data);
        disableForm(form, false);
        
        if(data.status === 'success'){
            // Clear form cache from localStorage
            try { localStorage.removeItem('createPostFormCache'); } catch(e) {}
            // Clear form fields
            form.reset();
            // Reset file input display
            let fileInput = form.querySelector('input[name="image"]');
            if(fileInput){
                let previewArea = document.getElementById('job-image-preview');
                if(previewArea) previewArea.innerHTML = '';
            }
            // redirect to job listings after successful creation
            showConfirmation('Jobb Publisert!','Din jobb har blitt sendt inn og venter på godkjenning fra moderator. Du vil bli varslet når den er publisert.');
        } else if(data.message && (data.message.includes('verifisere') || data.message.includes('Verifisere'))){
            // Show verification error popup with Verify and Close buttons
            showConfirmation('Verifiser e-posten din', data.message, null, 2000, 'pages.php?page=profile', 'Verifiser', 'Lukk');
        } else {
            // Show other errors as form message
            showFormMessage(form, data.message, data.status);
        }
    });

    document.getElementById("signupForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        fd.append('action','signup');
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            // Clear signup form cache
            localStorage.removeItem('signupFormCache');
            // Show email notification if email was sent
            if(data.email_sent === true){
                // Create and show notification popup
                let notif = document.createElement('div');
                notif.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    max-width: 400px;
                    padding: 16px;
                    background: #E8F5E9;
                    border: 2px solid #388E3C;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 5000;
                    font-family: Arial, sans-serif;
                    color: #1B5E20;
                `;
                notif.innerHTML = `
                    <strong style="font-size: 16px;">✓ Bekreftelsesmail sendt</strong><br/>
                    <small style="color: #1B5E20;">En lenke har blitt sendt til din e-postadresse. Klikk på den for å bekrefte kontoen.</small>
                `;
                document.body.appendChild(notif);
                setTimeout(() => notif.remove(), 5000);
            }
            // show small confirmation and then redirect to profile
            showConfirmation('Signup successful','Welcome — your account was created.', 'pages.php?page=profile', 1800);
        }
    });

    document.getElementById("loginForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        fd.append('action','login');
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            // Clear login form cache
            localStorage.removeItem('loginFormCache');
            location.reload();
        }
    });
    
    // Setup form data caching for login and signup forms
    function setupFormCache(formId, cacheKey) {
        const form = document.getElementById(formId);
        if(!form) return;
        
        // Restore cached data
        try {
            const cached = localStorage.getItem(cacheKey);
            if(cached) {
                const data = JSON.parse(cached);
                Object.keys(data).forEach(fieldName => {
                    const input = form.querySelector(`[name="${fieldName}"]`);
                    if(input) input.value = data[fieldName];
                });
            }
        } catch(e) {
            console.error('Error restoring form cache:', e);
        }
        
        // Save data on input change
        form.addEventListener('input', function(e) {
            if(e.target.tagName === 'INPUT') {
                try {
                    const data = {};
                    const inputs = form.querySelectorAll('input');
                    inputs.forEach(input => {
                        if(input.name && input.type !== 'hidden' && input.type !== 'submit') {
                            data[input.name] = input.value;
                        }
                    });
                    localStorage.setItem(cacheKey, JSON.stringify(data));
                } catch(e) {
                    console.error('Error saving form cache:', e);
                }
            }
        });
    }
    
    setupFormCache('signupForm', 'signupFormCache');
    setupFormCache('loginForm', 'loginFormCache');

    // Handle image preview for job creation form
    document.getElementById("job-image")?.addEventListener("change", function(e){
        const file = this.files[0];
        const form = this.closest('form');
        let previewContainer = form.querySelector('.image-preview-container');
        
        // Remove existing preview if present
        if(previewContainer) previewContainer.remove();
        
        if(!file) return;
        
        // Create preview container
        previewContainer = document.createElement('div');
        previewContainer.className = 'image-preview-container';
        
        const reader = new FileReader();
        reader.onload = function(event){
            const imgSizeKB = (file.size / 1024).toFixed(1);
            previewContainer.innerHTML = `
                <div class="image-preview">
                    <img src="${event.target.result}" alt="Preview">
                </div>
                <div class="image-preview-info">
                    <span>${file.name} (${imgSizeKB} KB)</span>
                    <button type="button" class="image-preview-remove" onclick="this.closest('.image-preview-container').remove(); document.getElementById('job-image').value=''; return false;">Remove</button>
                </div>
            `;
            
            const fileInput = document.getElementById('job-image');
            fileInput.parentElement.insertBefore(previewContainer, fileInput.nextElementSibling);
        };
        reader.readAsDataURL(file);
    });

    // wire jobs search form
    document.getElementById('jobsSearchForm')?.addEventListener('submit', async function(e){
        e.preventDefault();
        const f = e.target;
        const filters = {
            q: f.querySelector('[name="q"]')?.value || '',
            category: f.querySelector('[name="category"]')?.value || '',
            location: f.querySelector('[name="location"]')?.value || '',
            min_budget: f.querySelector('[name="min_budget"]')?.value || '',
            max_budget: f.querySelector('[name="max_budget"]')?.value || ''
        };
        loadJobs(filters);
    });

    // Reset filters button: clear inputs and reload jobs
    document.getElementById('jobsResetBtn')?.addEventListener('click', function(e){
        const form = document.getElementById('jobsSearchForm');
        if(!form) return;
        [...form.querySelectorAll('input')].forEach(i=>{ if(i.type !== 'hidden') i.value = ''; });
        loadJobs();
    });

    // auto-load jobs on page load when jobsList exists
    if(document.getElementById('jobsList')) loadJobs();
    // auto-load dashboard if stats container present
    if(document.getElementById('dashboardStats')) loadDashboard();

    // event delegation for take-down / delete buttons in dashboard
    document.addEventListener('click', async e=>{
        if(e.target.matches('.take-down-btn')){
            e.preventDefault();
            const id = e.target.dataset.id;
            if(!id) return;
            if(!confirm('Er du sikker på at du vil ta ned denne annonsen?')) return;
            const fd = new FormData();
            fd.append('action','take_down_post');
            fd.append('post_id', id);
            let res = await fetch('/display.php',{method:'POST',body:fd,credentials:'same-origin'});
            let data = await parseJsonResponse(res);
            if(data.status==='success'){
                loadDashboard();
            } else {
                alert(data.message || 'Feil');
            }
        }
        if(e.target.matches('.delete-btn')){
            e.preventDefault();
            const id = e.target.dataset.id;
            if(!id) return;
            if(!confirm('Slette permanent denne annonsen?')) return;
            const fd = new FormData();
            fd.append('action','delete_post');
            fd.append('post_id', id);
            let res = await fetch('/display.php',{method:'POST',body:fd,credentials:'same-origin'});
            let data = await parseJsonResponse(res);
            if(data.status==='success'){
                loadDashboard();
            } else { alert(data.message || 'Feil'); }
        }
    });

    // Close job detail modal when clicking the overlay
    document.getElementById('jobDetailModal')?.addEventListener('click', function(e){
        if(e.target === this || e.target.classList.contains('job-detail-overlay')){
            this.style.display = 'none';
        }
    });

    // Page (full-page) signup form
    document.getElementById("signupPageForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        // forms in pages.php include a hidden input 'action'
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            showConfirmation('Signup successful','Welcome — your account was created.', 'pages.php?page=profile', 1600);
        }
    });

    // Page (full-page) login form
    document.getElementById("loginPageForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // On login success, silently reload the current page (no popup)
            location.reload();
        }
    });

    document.getElementById("logoutBtn")?.addEventListener("click",async ()=>{
        let fd=new FormData();
        fd.append('action','logout');
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ return; }
        let data = await parseJsonResponse(res);
        if(data.status==='success') location.reload();
    });

    // Navbar signup form handler
    document.getElementById('signupForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // Close dropdown and reload to show logged in state
            document.getElementById('dropdownMenu')?.classList.remove('active');
            location.reload();
        }
    });

    // Navbar login form handler
    document.getElementById('loginForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // Close dropdown and reload
            document.getElementById('dropdownMenu')?.classList.remove('active');
            location.reload();
        }
    });

    // Settings form handler (profile page)
    document.getElementById('settingsForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        let form = e.target;
        let fd = new FormData(form);
        fd.append('action','update_settings');
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            // refresh to show updated profile info
            setTimeout(()=> location.reload(), 700);
        }
    });

    // Bio form handler
    document.getElementById('bioForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        let form = e.target;
        let fd = new FormData(form);
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            setTimeout(()=> location.reload(), 700);
        }
    });

    // Bio character counter
    document.getElementById('profile-bio')?.addEventListener('input', function(){
        const counter = document.getElementById('bio-char-count');
        if(counter) counter.textContent = this.value.length;
    });

    // Initialize bio character counter on page load
    const bioTextarea = document.getElementById('profile-bio');
    if(bioTextarea){
        const counter = document.getElementById('bio-char-count');
        if(counter) counter.textContent = bioTextarea.value.length;
    }

    // Profile picture upload form
    document.getElementById('profilePictureForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        let form = e.target;
        let fd = new FormData(form);
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            // refresh to show new profile picture
            setTimeout(()=> location.reload(), 700);
        }
    });

    // Contact form (AJAX)
    document.getElementById('contactForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        fd.append('action','contact');
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message || 'Unknown response', data.status || 'error');
        disableForm(form, false);
        if(data.status === 'success'){
            form.reset();
        }
    });

    // Client-side validation helpers
    function setFieldError(fieldId, msg){
        const el = document.getElementById(fieldId);
        const err = document.querySelector('.field-error[data-for="'+fieldId+'"]');
        if(el){
            if(msg){ el.classList.add('invalid'); }
            else el.classList.remove('invalid');
        }
        if(err) err.textContent = msg || '';
    }

    // Simple email check
    function isValidEmail(email){
        return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
    }

    // validate settings form before submit
    document.getElementById('settingsForm')?.addEventListener('input', e=>{
        const name = e.target.name;
        if(name === 'email'){
            const ok = isValidEmail(e.target.value);
            setFieldError('profile-email', ok ? '' : 'Invalid email');
        }
        if(name === 'username'){
            const val = e.target.value.trim();
            setFieldError('profile-username', (val.length >=3 && val.length <=32) ? '' : 'Username must be 3-32 chars');
        }
    });

    // Password change handler
    document.getElementById('passwordForm')?.addEventListener('submit', async e=>{
        e.preventDefault();
        const form = e.target;
        const current = form.querySelector('[name="current_password"]').value || '';
        const nw = form.querySelector('[name="new_password"]').value || '';
        const conf = form.querySelector('[name="confirm_password"]').value || '';
        // client validation
        let ok = true;
        setFieldError('current-password',''); setFieldError('new-password',''); setFieldError('confirm-password','');
        if(current.length < 1){ setFieldError('current-password','Enter current password'); ok=false; }
        if(nw.length < 6){ setFieldError('new-password','Password too short'); ok=false; }
        if(nw !== conf){ setFieldError('confirm-password','Passwords do not match'); ok=false; }
        if(!ok) return;

        let fd = new FormData();
        fd.append('action','update_password');
        fd.append('current_password', current);
        fd.append('new_password', nw);
        disableForm(form, true);
        let res;
        try{ res = await fetch('/display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            form.reset();
        }
    });
});
    // Resend verification
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('resendVerifyBtn');
    if (!btn) {
        console.warn('Resend verification button not found!');
        return;
    }
    console.log('✓ Resend verification button found and listener attached');

    let cooldownActive = false;
    let cooldownEnd = 0;

    btn.addEventListener('click', async e => {
        e.preventDefault();
        
        // Check if cooldown is still active
        if (cooldownActive) {
            const remaining = Math.ceil((cooldownEnd - Date.now()) / 1000);
            alert(`Vennligst vent ${remaining} sekund før du prøver igjen.`);
            return;
        }
        
        console.log('🔘 Resend verification button clicked');
        btn.disabled = true;

        console.log('📤 Sending POST to /display.php with action=resend_verification');

        const fd = new FormData();
        fd.append('action', 'resend_verification');

        let res;
        try {
            res = await fetch('/display.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            console.log('📡 Fetch returned, status:', res.status, res.statusText);
        } catch (err) {
            console.error('❌ Network error:', err);
            alert('Network error: ' + err.message);
            btn.disabled = false;
            return;
        }

        let responseText = '';
        let data;
        try {
            responseText = await res.text();
            console.log('📝 Raw response:', responseText);
            data = JSON.parse(responseText);
            console.log('✓ Parsed JSON:', data);
        } catch (err) {
            console.error('❌ Failed to parse response:', err);
            console.error('Raw response was:', responseText);
            alert('Server error: Invalid response');
            btn.disabled = false;
            return;
        }

        console.log('📦 Server response data:', data);

        // Start 60-second cooldown
        cooldownActive = true;
        cooldownEnd = Date.now() + 60000;
        const originalText = btn.textContent;
        
        const cooldownInterval = setInterval(() => {
            const remaining = Math.ceil((cooldownEnd - Date.now()) / 1000);
            if (remaining > 0) {
                btn.textContent = `Vent ${remaining}s`;
            } else {
                clearInterval(cooldownInterval);
                cooldownActive = false;
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }, 100);

        if (data.success) {
            console.log('✓ Email sent successfully');
            // Show success notification (centered and prominent)
            let notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                max-width: 500px;
                padding: 32px;
                background: #F0F8E8;
                border: 4px solid #1B5E20;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.25);
                z-index: 5000;
                font-family: Arial, sans-serif;
                color: #1B5E20;
                text-align: center;
            `;
            notif.innerHTML = `
                <div style="position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 24px; line-height: 1; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: rgba(27, 94, 32, 0.1); border-radius: 4px; user-select: none;" class="close-btn">✕</div>
                <div style="font-size: 48px; margin-bottom: 16px;">✓</div>
                <strong style="font-size: 20px; display: block; margin-bottom: 12px;">Bekreftelsesmail sendt!</strong>
                <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #2C5F2D;">
                    En bekreftelseslenke har blitt sendt til din e-postadresse.<br/>
                    <strong>Sjekk også spam-mappen din hvis du ikke finner den i innboksen.</strong>
                </p>
            `;
            document.body.appendChild(notif);
            notif.querySelector('.close-btn').addEventListener('click', () => notif.remove());
        } else {
            console.log('❌ Email send failed');
            // Show error notification
            let notif = document.createElement('div');
            notif.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                max-width: 400px;
                padding: 16px;
                background: #FFEBEE;
                border: 2px solid #C62828;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 5000;
                font-family: Arial, sans-serif;
                color: #B71C1C;
            `;
            notif.innerHTML = `
                <strong style="font-size: 16px;">✗ Feil</strong><br/>
                <small style="color: #B71C1C;">${data.message}</small>
            `;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 5000);
        }

        console.log('✓ Resend verification handler complete');
        btn.disabled = false;
    });
});


