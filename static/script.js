function toggleDropdown(){
    const dropdownMenu=document.getElementById('dropdownMenu');
    if(dropdownMenu) dropdownMenu.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded',function(){
    const userBtn=document.querySelector('.user-btn');
    if(userBtn){
        userBtn.addEventListener('click',function(e){
            e.stopPropagation();
            toggleDropdown();
        });
    }

    document.addEventListener('click',function(e){
        const userProfile=document.querySelector('.user-profile');
        const dropdownMenu=document.getElementById('dropdownMenu');
        if(userProfile && dropdownMenu && !userProfile.contains(e.target)){
            dropdownMenu.classList.remove('active');
        }
    });

    document.querySelectorAll('.btn').forEach(button=>{
        button.addEventListener('click',function(){
            console.log('Button clicked:',this.textContent);
        });
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            showConfirmation('Login successful','You are now logged in.', 'pages.php?page=profile', 1400);
        }
    });

    document.getElementById("createPostForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        fd.append('action','create_post');
        let form = e.target;
        disableForm(form, true);
        let res;
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success') location.reload();
    });

    // Page (full-page) signup form
    document.getElementById("signupPageForm")?.addEventListener("submit",async e=>{
        e.preventDefault();
        let fd=new FormData(e.target);
        let form = e.target;
        disableForm(form, true);
        // forms in pages.php include a hidden input 'action'
        let res;
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status==='success'){
            showConfirmation('Login successful','You are now logged in.', 'pages.php?page=profile', 1200);
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
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
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(form,'Network error','error'); disableForm(form,false); return; }
        let data = await parseJsonResponse(res);
        showFormMessage(form, data.message, data.status);
        disableForm(form, false);
        if(data.status === 'success'){
            form.reset();
        }
    });

    // Resend verification
    document.getElementById('resendVerifyBtn')?.addEventListener('click', async e=>{
        e.preventDefault();
        const btn = e.target;
        btn.disabled = true;
        let fd = new FormData(); fd.append('action','send_verification');
        let res;
        try{ res = await fetch('index.php',{method:'POST',body:fd, credentials:'same-origin'}); }
        catch(err){ showFormMessage(document.getElementById('settingsForm')||document.body,'Network error','error'); btn.disabled=false; return; }
        let data = await parseJsonResponse(res);
        // show a toast in profile area
        const profileForm = document.getElementById('settingsForm');
        if(profileForm) showFormMessage(profileForm, data.message + (data.verification_url? (' — ' + data.verification_url) : ''), data.status);
        btn.disabled = false;
    });
});
