// Load dark mode immediately on page load (before DOM renders)
(function(){
    if(localStorage.getItem('darkMode') === 'true'){
        document.documentElement.classList.add('dark-mode');
    }
})();

function toggleDropdown(){
    const dropdownMenu=document.getElementById('dropdownMenu');
    if(dropdownMenu) dropdownMenu.classList.toggle('active');
}


document.addEventListener('DOMContentLoaded',function(){
    // Dark mode toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    if(darkModeToggle){
        // Load dark mode preference from localStorage
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if(isDarkMode){
            document.body.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }
        
        // Toggle dark mode on checkbox change
        darkModeToggle.addEventListener('change',function(){
            if(this.checked){
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            }
        });
    }});
      
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

    document.getElementById("signupForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        fd.append('action','signup');
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // On login success, silently reload the current page (no popup)
            location.reload();
        }
    });

    document.getElementById("createPostForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        fd.append('action','create_post');
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // redirect to job listings after successful creation
            showConfirmation('Job created','Your job was posted.', 'pages.php?page=jobs', 900);
        }
    });

    // Jobs listing + search
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
            out += `<article class="service-card" style="margin-bottom:12px">
                <h3>${escapeHtml(j.title)}</h3>
                <p>${escapeHtml(j.description)}</p>
                <p style="color:#666;font-size:13px">${escapeHtml(j.username)} — ${escapeHtml(j.location||'')}</p>
                <p style="font-weight:600;margin-top:6px">Budget: ${j.budget ? (parseInt(j.budget) + ' NOK') : 'Negotiable'}</p>
                <div style="font-size:12px;color:#888;margin-top:6px">Posted: ${escapeHtml(j.created_at)}</div>
            </article>`;
        });
        container.innerHTML = out;
    }

    function escapeHtml(s){ if(!s && s!==0) return ''; return String(s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }

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
        try{ res = await fetch('display.php',{method:'POST', body: fd, credentials:'same-origin'}); }
        catch(err){ container.innerHTML = '<div class="note">Network error</div>'; return; }
        let data = await parseJsonResponse(res);
        if(data.status === 'success') renderJobs(data.jobs, container);
        else container.innerHTML = `<div class="note">${escapeHtml(data.message || 'Error')}</div>`;
    }

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

    // Page (full-page) signup form
    document.getElementById("signupPageForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        // forms in pages.php include a hidden input 'action'
        let res;
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            // On login success, silently reload the current page (no popup)
            location.reload();
        }
    });

    // Helper: display inline message for a form
    function showFormMessage(form, message, status){
        let container = form.querySelector('.form-message');
        if(!container){
            // fallback to alert
            alert(message);
            return;
        }
        container.textContent = message || '';
        container.classList.remove('success','error');
        if(status === 'success') container.classList.add('success');
        else container.classList.add('error');
    }

    // Helper: disable/enable form (buttons and inputs)
    function disableForm(form, disabled){
        [...form.querySelectorAll('input,button,textarea')].forEach(el=>{
            if(el.type === 'hidden') return;
            el.disabled = disabled;
            if(el.tagName === 'BUTTON') el.setAttribute('aria-disabled', disabled);
        });
    }

    document.getElementById("logoutBtn")?.addEventListener("click",async ()=>{
        let fd=new FormData();
        fd.append('action','logout');
        let res;
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            // refresh to show updated profile info
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
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('display.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            form.reset();
        }
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
            res = await fetch('display.php', {
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

