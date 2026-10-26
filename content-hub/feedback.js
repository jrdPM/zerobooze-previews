(() => {
  let csrf='',ready=false;
  let pending={};
  const apiBase=new URL('api.php',window.location.href.split('#')[0]);
  const configStatus=()=>document.getElementById('shared-save-status');
  async function call(action,data){
    const url=new URL(apiBase);url.searchParams.set('action',action);
    const opts={credentials:'same-origin',cache:'no-store',headers:{}};
    if(data){opts.method='POST';opts.headers['Content-Type']='application/json';opts.body=JSON.stringify({...data,csrf});}
    const response=await fetch(url,opts);let result;
    try{result=await response.json()}catch{throw Error('The server did not accept the submission. Your draft is still here.')}
    if(!response.ok)throw Error(result.error||'Your draft has not been sent. Please try again.');
    if(result.csrf)csrf=result.csrf;
    return result;
  }
  const originalReader=readerResource,originalQuestions=questions,originalBind=bind;
  function identity(){return {name:local.reviewerName||'',email:local.reviewerEmail||''}}
  function reviewerPanel(){const v=identity();return `<section class="reviewer-panel" aria-label="Your review details"><div><strong>Send feedback to the ZeroBooze team</strong><p>Submissions are saved centrally and visible to the team in the protected dashboard. They are not posted publicly. Browser drafts remain private until you send them.</p></div><div class="reviewer-fields"><label>Your name <input id="reviewer-name" maxlength="120" autocomplete="name" value="${escape(v.name)}" placeholder="Required to send feedback"></label><label>Email (optional)<input id="reviewer-email" type="email" maxlength="254" autocomplete="email" value="${escape(v.email)}" placeholder="For a reply"></label></div><div id="shared-save-status" role="status">${ready?'Shared saving is connected.':'Connecting to feedback storage…'}</div><p class="reviewer-privacy">Please keep feedback focused on the project. Do not include sensitive medical, financial, or personal information.</p><div class="review-honeypot" aria-hidden="true"><label>Website<input name="website" id="review-website" tabindex="-1" autocomplete="off"></label></div></section>`}
  readerResource=function(i){let html=originalReader(i);html=html.replace('Your review notes','Your feedback').replace('Saved on this browser only. Export your collection to share notes with the team.','Drafts save on this browser. Click Send feedback to save a response for the team.');html=html.replace('</textarea></details>','</textarea><div class="feedback-controls"><button class="btn" data-send-feedback="'+i.id+'">Send feedback</button><span class="feedback-receipt" id="receipt-'+i.id+'" role="status"></span></div></details>');return html;};
  questions=function(){return originalQuestions().replace('Answers save locally on this browser. Export them to send to the team.','Answers remain browser drafts until you click Send answers to the team. Each submission is saved as a new review record.').replace('<button class="btn secondary" id="export-answers">Export answers ↓</button>','<button class="btn secondary" id="export-answers">Export answers ↓</button><button class="btn" id="send-answers">Send answers to the team</button>')+'<p class="answer-receipt" role="status" id="answer-receipt"></p>'};
  function updateMessages(){if(configStatus())configStatus().textContent=ready?'Shared saving is connected.':'Shared saving is not configured or is unavailable. Drafts stay on this browser.';}
  async function send(kind,id,button){
    const receipt=kind==='comment'?document.getElementById('receipt-'+id):document.getElementById('answer-receipt');
    if(!ready){if(receipt)receipt.textContent='Shared saving is unavailable. Your draft has not been sent.';return;}
    const {name,email}=identity();
    if(!name.trim()){if(receipt)receipt.textContent='Add your name in the review details above before sending.';document.getElementById('reviewer-name')?.focus();return;}
    if(email&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){if(receipt)receipt.textContent='Check your email address above.';return;}
    const payload={name,email,kind,website:document.getElementById('review-website')?.value||''};
    if(kind==='comment'){payload.resource_id=id;payload.body=local.notes[id]||'';if(!payload.body.trim()){receipt.textContent='Add feedback before sending.';return;}}
    else{payload.answers=Object.fromEntries(Object.entries(local.answers).filter(([k,v])=>String(v).trim()));if(!Object.keys(payload.answers).length){receipt.textContent='Add at least one answer before sending.';return;}}
    const key=kind+':'+(id||'answers'),fingerprint=JSON.stringify(payload);
    if(!pending[key]||pending[key].fingerprint!==fingerprint)pending[key]={fingerprint,id:crypto.randomUUID()};
    payload.request_id=pending[key].id;button.disabled=true;const label=button.textContent;button.textContent='Sending…';if(receipt)receipt.textContent='';
    try{const r=await call('submit',payload);if(receipt)receipt.textContent='Saved for the team. Reference #'+r.ids.join(', #');toast('Your feedback was saved centrally.');}
    catch(e){if(receipt)receipt.textContent=e.message;}
    finally{button.disabled=false;button.textContent=label;}
  }
  bind=function(){originalBind();
    // A global identity panel is rendered once per page, alongside existing sections.
    if(!document.getElementById('reviewer-name'))document.getElementById('content').insertAdjacentHTML('afterbegin',reviewerPanel());
    document.getElementById('reviewer-name').oninput=e=>{local.reviewerName=e.target.value;persist()};
    document.getElementById('reviewer-email').oninput=e=>{local.reviewerEmail=e.target.value;persist()};
    document.querySelectorAll('[data-send-feedback]').forEach(b=>b.onclick=()=>send('comment',b.dataset.sendFeedback,b));
    const answers=document.getElementById('send-answers');if(answers)answers.onclick=()=>send('answers','',answers);
    updateMessages();
  };
  // Existing local exports and bookmarks remain available; draft migration is never automatic.
  render();
  call('session').then(()=>{ready=true;updateMessages()}).catch(()=>{ready=false;updateMessages()});
})();
