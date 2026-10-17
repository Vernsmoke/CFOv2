<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Change temporary password · Recollection System</title>
  <style>
    :root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#173b35;background:#f2f6f3}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px}.card{width:min(100%,520px);padding:32px;background:#fff;border:1px solid #dce7e1;border-radius:16px;box-shadow:0 16px 40px #063b2514}h1{margin:0 0 10px;font-size:27px}p{line-height:1.6;color:#58716a}.field{display:grid;gap:7px;margin:18px 0}.field input{min-height:46px;width:100%;padding:10px 12px;border:1px solid #bdcec5;border-radius:8px;font:inherit}.btn{min-height:44px;padding:10px 17px;border:0;border-radius:8px;background:#07543f;color:#fff;font:inherit;font-weight:650;cursor:pointer}.btn:disabled{opacity:.6;cursor:wait}.muted{font-size:13px}.error{color:#a12626;min-height:24px}.expired{padding:14px;border-radius:9px;background:#fff2dc;color:#70480e}dialog{width:min(420px,calc(100% - 32px));padding:24px;border:1px solid #dce7e1;border-radius:14px;color:#173b35}dialog::backdrop{background:#062f3699}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}.secondary{background:#fff;color:#173b35;border:1px solid #bdcec5}
  </style>
@include('partials.cfo-usability')
</head>
<body>
  <main class="card">
    <p class="muted">Christian Formation Office · {{ $user->name }}</p>
    <h1>Create a new password</h1>
    @if ($expired)
      <p class="expired">This temporary password has expired. Visit the Christian Formation Office and ask an administrator to reset it again.</p>
    @else
      <p>Your account password was reset by the office. Choose a new password to continue; your temporary password will no longer work afterward.</p>
      <form id="password-form">
        <label class="field">New password<input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
        <label class="field">Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></label>
        <p class="muted">Use at least 8 characters, with uppercase and lowercase letters, a number, and a symbol.</p>
        <p id="error" class="error" role="alert"></p>
        <button class="btn" type="submit">Save new password</button>
      </form>
    @endif
  </main>
  <dialog id="confirm-dialog" aria-labelledby="confirm-title">
    <h2 id="confirm-title">Confirm password change</h2>
    <p>Your temporary password will be replaced by the new password you entered.</p>
    <div class="actions"><button class="btn secondary" id="cancel" type="button">Cancel</button><button class="btn" id="confirm" type="button">Confirm and save</button></div>
  </dialog>
  @if (! $expired)
  <script>
    const form=document.querySelector('#password-form'),dialog=document.querySelector('#confirm-dialog');
    form.addEventListener('submit',event=>{event.preventDefault();dialog.showModal();});
    document.querySelector('#cancel').onclick=()=>dialog.close();
    document.querySelector('#confirm').onclick=async()=>{
      const button=document.querySelector('#confirm');button.disabled=true;document.querySelector('#error').textContent='';
      try{
        const response=await fetch('/api/force-password',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(Object.fromEntries(new FormData(form)))});
        const result=await response.json().catch(()=>({}));
        if(!response.ok)throw new Error(Object.values(result.errors||{}).flat().join('\n')||result.message||'The password could not be changed.');
        location.href=result.redirect;
      }catch(error){dialog.close();document.querySelector('#error').textContent=error.message;}
      finally{button.disabled=false;}
    };
  </script>
  @endif
</body>
</html>
