'use strict';
document.querySelector('[data-menu]')?.addEventListener('click', () => document.querySelector('#sidebar').classList.toggle('open'));
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
const form = document.querySelector('[data-autosave]');
if (form) {
 const status = document.querySelector('[data-save-status]');
 let timer, pending = Promise.resolve(), dirty = false, submitting = false;
 const save = () => {
  if (!dirty || submitting) return pending;
  const data = new FormData(form); data.set('submit', '0');
  dirty = false; status.textContent = 'Sauvegarde en cours…';
  pending = pending.catch(() => {}).then(async () => {
   const response = await fetch(form.action, {method:'POST',body:data,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
   if (!response.ok) throw new Error('save');
   const result = await response.json(); status.textContent = `Sauvegardé à ${result.saved_at}`;
  }).catch(() => { dirty = true; status.textContent = 'Échec de sauvegarde. Vérifiez votre connexion ou utilisez Sauvegarder.'; });
  return pending;
 };
 form.addEventListener('input', () => {dirty = true; clearTimeout(timer); timer = setTimeout(save, 900); status.textContent = 'Modifications non enregistrées…';});
 form.addEventListener('submit', async event => {
  event.preventDefault(); if (submitting) return;
  const submitter = event.submitter;
  if (submitter?.value === '1' && !window.confirm('Soumettre définitivement vos réponses ? Vous ne pourrez plus les modifier.')) return;
  submitting = true; clearTimeout(timer); await pending;
  const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'submit'; hidden.value = submitter?.value || '0'; form.append(hidden);
  form.querySelectorAll('button').forEach(button => button.disabled = true);
  HTMLFormElement.prototype.submit.call(form);
 });
 window.addEventListener('beforeunload', event => {if (dirty && !submitting) {event.preventDefault();event.returnValue = '';}});
}

const builder = document.querySelector('[data-question-builder]');
if (builder) {
 const list = builder.querySelector('[data-builder-list]');
 const json = document.querySelector('[data-questions-json]');
 const status = builder.querySelector('[data-builder-status]');
 let questions = [];
 function field(labelText, tag, value, options) {
  const label = document.createElement('label'); label.textContent = labelText;
  const input = document.createElement(tag);
  if (options) options.forEach(text => {const opt = document.createElement('option');opt.value=text;opt.textContent=text;input.append(opt);});
  input.value=value ?? '';label.append(input);return {label,input};
 }
 function sync() {json.value=JSON.stringify(questions,null,2);status.textContent=`${questions.length} question(s) dans cette version.`;}
 function render() {
  list.replaceChildren();
  questions.forEach((question,index) => {
   const card = document.createElement('fieldset'); card.className='question-editor';
   const legend=document.createElement('legend');legend.textContent=`Question ${index+1}`;card.append(legend);
   const grid=document.createElement('div');grid.className='form-grid';
   [['Identifiant','id','input'],['Libellé','label','input'],['Type','type','select'],['Dimension Gordon','dimension','select']].forEach(([title,key,tag])=>{
    const choices=key==='type'?['text','choice','scale','boolean']:key==='dimension'?['','A','B','C','D']:null;
    const {label,input}=field(title,tag,question[key],choices);input.addEventListener('change',()=>{if(key==='dimension' && !input.value){delete question.dimension;}else{question[key]=input.value;}if(key==='type'){if(input.value==='scale'){question.min??=0;question.max??=10;}if(input.value==='choice'){question.options??=['Option 1','Option 2'];}render();}sync();});grid.append(label);
   });
   if(question.type==='scale') ['min','max'].forEach(key=>{const {label,input}=field(key==='min'?'Minimum':'Maximum','input',question[key]);input.type='number';input.addEventListener('input',()=>{question[key]=Number(input.value);sync();});grid.append(label);});
   if(question.type==='choice'){const {label,input}=field('Options, une par ligne','textarea',(question.options||[]).join('\n'));input.addEventListener('input',()=>{question.options=input.value.split('\n').map(x=>x.trim()).filter(Boolean);sync();});grid.append(label);}
   const required=document.createElement('label');required.className='check-label';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.checked=question.required!==false;checkbox.addEventListener('change',()=>{question.required=checkbox.checked;sync();});required.append(checkbox,document.createTextNode('Réponse obligatoire'));grid.append(required);card.append(grid);
   const remove=document.createElement('button');remove.type='button';remove.className='btn danger small';remove.textContent='Retirer cette question';remove.addEventListener('click',()=>{questions.splice(index,1);render();sync();});card.append(remove);list.append(card);
  });
 }
 builder.querySelector('[data-add-question]').addEventListener('click',()=>{questions.push({id:`question_${Date.now()}`,label:'',type:'text',required:true});render();sync();});
 function load(){try{const value=JSON.parse(json.value);if(!Array.isArray(value))throw new Error();questions=value;render();status.textContent=`${questions.length} question(s) chargées.`;}catch{status.textContent='Le JSON doit contenir une liste de questions valide.';}}
 builder.querySelector('[data-load-json]').addEventListener('click',load);load();
}
