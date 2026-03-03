

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
        if(interval === 0) return 'just now';
        return interval + ' min ago';
    } else if(diffHours < 24){
        // 1-24 hours: every hour
        return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
    } else {
        // After 24 hours: every day
        return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
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
        out += `<article class="service-card" style="margin-bottom:12px;cursor:pointer;" onclick="openJobDetail(${j.id})">
            ${imageHtml}
            <h3>${escapeHtml(j.title)}</h3>
            <p>${escapeHtml(j.description.substring(0,150)) + (j.description.length > 150 ? '...' : '')}</p>
            <p style="color:#666;font-size:13px">${escapeHtml(j.username)} — ${escapeHtml(j.location||'')}</p>
            <p style="font-weight:600;margin-top:6px">Budget: ${j.budget ? (parseInt(j.budget) + ' NOK') : 'Negotiable'}</p>
            <div style="font-size:12px;color:#888;margin-top:6px">Posted: ${formatRelativeTime(j.created_at)}</div>
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
                    <div class="job-detail-thumbs" style="display:flex;gap:8px;flex-wrap:wrap;">${thumbs}</div>
                </div>`;
            } else if(p.image){
                imageHtml = `<img src="${p.image}" alt="${escapeHtml(p.title)}" style="width:100%;max-height:400px;object-fit:cover;border-radius:8px;margin-bottom:16px;">`;
            }

            detailContent.innerHTML = `
                ${imageHtml}
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    ${p.profile_picture ? `<img src="${p.profile_picture}" alt="${escapeHtml(p.username)}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">` : `<div style="width:40px;height:40px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;font-weight:bold;">${escapeHtml(p.username.charAt(0))}</div>`}
                    <div>
                        <h3 style="margin:0;">${escapeHtml(p.username)}</h3>
                        <p style="color:#666;font-size:13px;margin:4px 0 0 0;">${escapeHtml(p.location||'Not specified')}</p>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div><strong>Budget:</strong> <span style="font-size:18px;color:var(--green);">${p.budget ? (parseInt(p.budget) + ' NOK') : 'Negotiable'}</span></div>
                    <div><strong>Category:</strong> ${escapeHtml(p.category||'Not specified')}</div>
                    <div style="grid-column:1/-1;"><strong>Posted:</strong> ${formatRelativeTime(p.created_at)}</div>
                </div>
                ${p.contact_info ? `<div style="background:#f5f5f5;padding:12px;border-radius:6px;margin-bottom:20px;border-left:4px solid var(--green);"><strong>Contact:</strong> ${escapeHtml(p.contact_info)}</div>` : ''}
                <hr style="margin:20px 0;border:none;border-top:1px solid var(--off-white);">
                <h3 style="margin-top:0;">Description</h3>
                <p style="line-height:1.6;white-space:pre-wrap;">${escapeHtml(p.description)}</p>
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

    function showConfirmation(title, message, redirectUrl, autoMs=2000){
        ensureConfirmModal();
        const overlay = document.getElementById('confirmOverlay');
        const titleEl = overlay.querySelector('#confirmTitle');
        const msgEl = overlay.querySelector('#confirmMessage');
        const nowBtn = overlay.querySelector('#confirmNow');
        const stayBtn = overlay.querySelector('#confirmStay');
        const countEl = overlay.querySelector('#confirmCountdown');

        titleEl.textContent = title || 'Success';
        msgEl.textContent = message || '';
        let remaining = Math.ceil(autoMs/1000);
        countEl.textContent = remaining;
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

    // Initialize create job form with rate limit check
    let createPostForm = document.getElementById("createPostForm");
    if(createPostForm) {
        let remainingPosts = parseInt(createPostForm.dataset.remainingPosts || '3');
        let submitBtn = createPostForm.querySelector('button[type="submit"]');
        
        if(remainingPosts <= 0 && submitBtn) {
            submitBtn.disabled = true;
            submitBtn.title = 'Daglig grense nådd. Prøv igjen i morgen!';
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
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
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        
        if(data.status === 'success'){
            // Clear form fields
            form.reset();
            // Reset file input display
            let fileInput = form.querySelector('input[name="image"]');
            if(fileInput){
                let previewArea = document.getElementById('job-image-preview');
                if(previewArea) previewArea.innerHTML = '';
            }
            // redirect to job listings after successful creation
            showConfirmation('Job Posted!','Your job has been submitted and is awaiting moderator approval. You will be notified once it is published.');
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
            location.reload();
        }
    });

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

    btn.addEventListener('click', async e => {
        e.preventDefault();
        btn.disabled = true;

        console.log('Resend verification clicked — sending POST');

        const fd = new FormData();
        fd.append('action', 'resend_verification');

        let res;
        try {
            res = await fetch('/display.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
        } catch (err) {
            console.error('Network error:', err);
            btn.disabled = false;
            return;
        }

        let data;
        try {
            data = await res.json();
        } catch (err) {
            console.error('Invalid JSON response:', err, await res.text());
            btn.disabled = false;
            return;
        }

        console.log('Server response:', data);

        const profileForm = document.getElementById('settingsForm');
        if (profileForm) {
            showFormMessage(profileForm, data.message + (data.verification_url ? (' — ' + data.verification_url) : ''), data.status);
        } else {
            alert(data.message);
        }

        btn.disabled = false;
    });
});


