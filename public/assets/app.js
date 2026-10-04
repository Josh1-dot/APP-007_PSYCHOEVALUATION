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
 const editorForm = builder.closest('form');
 const fileInput = editorForm.querySelector('[name="questions_file"]');
 const loadButton = builder.querySelector('[data-load-json]');
 function decodeImport(text) {
  let value;
  try { value = JSON.parse(text); } catch { throw new Error('JSON invalide : vérifiez la syntaxe du fichier.'); }
  const snapshot = !Array.isArray(value) && value && typeof value === 'object' ? value : null;
  const items = snapshot ? snapshot.questions : value;
  if (!Array.isArray(items) || !items.length || items.length > 250 || items.some(q => !q || typeof q !== 'object' || Array.isArray(q) || typeof q.id !== 'string' || typeof q.label !== 'string' || !q.label.trim() || !/^[A-Za-z][A-Za-z0-9_]{0,39}$/.test(q.id) || !['text','choice','scale','boolean'].includes(q.type) || (q.type === 'choice' && (!Array.isArray(q.options) || q.options.length < 2 || q.options.some(option => typeof option !== 'string'))) || (q.type === 'scale' && (!Number.isInteger(q.min) || !Number.isInteger(q.max) || q.min < 0 || q.max > 100 || q.min >= q.max)))) {
   throw new Error('Structure incorrecte : une liste de 1 à 250 questions valides est requise.');
  }
  if (new Set(items.map(q => q.id)).size !== items.length) throw new Error('Identifiants de questions dupliqués');
  if (snapshot) {
   if (items.some(q => typeof q.item_key !== 'string' || !Number.isInteger(q.item_version) || q.item_version < 1 || !['fr','en'].includes(q.language) || typeof q.provenance !== 'string' || !q.provenance.trim())) throw new Error('Métadonnées des items pondérés manquantes');
   if (snapshot.kind !== 'enneagramme' || snapshot.engine_version !== 'enneagramme-weighted-v1' || !/^[A-Za-z0-9][A-Za-z0-9_-]{0,39}$/.test(snapshot.form_key || '')) {
    throw new Error('Export incorrect : utilisez un export Ennéagramme pondéré avec une clé de forme.');
   }
   const rules = snapshot.scoring_rules;
   const dimensions = Array.from({length:9}, (_, i) => `type${i+1}`);
   const object = value => value && typeof value === 'object' && !Array.isArray(value);
   if (!object(rules) || rules.method_version !== snapshot.engine_version || JSON.stringify(rules.dimensions) !== JSON.stringify(dimensions) || !object(rules.items) || Object.keys(rules.items).length !== items.length || items.length < 9) {
    throw new Error('Règles de scoring invalides : version, neuf dimensions et règles par item requises.');
   }
   if (new Set(items.map(q => q.id)).size !== items.length) throw new Error('Identifiants de questions dupliqués');
   const covered = new Set();
   for (const q of items) {
    const rule = rules.items[q.id];
    if (!object(rule) || !object(rule.dimension_weights) || !Object.keys(rule.dimension_weights).length || Object.entries(rule.dimension_weights).some(([key, weight]) => !dimensions.includes(key) || typeof weight !== 'number' || !Number.isFinite(weight) || weight <= 0 || weight > 100) || !object(rule.score_map) || !Object.keys(rule.score_map).length || (rule.reverse !== undefined && typeof rule.reverse !== 'boolean')) {
     throw new Error('Règles de scoring invalides pour un item.');
    }
    Object.keys(rule.dimension_weights).forEach(key => covered.add(key));
    let answers;
    if (q.type === 'scale' && Number.isInteger(q.min) && Number.isInteger(q.max) && q.min >= 0 && q.max <= 100 && q.min < q.max) answers = Array.from({length:q.max-q.min+1}, (_, i) => String(q.min+i));
    else if (q.type === 'choice' && Array.isArray(q.options) && q.options.length >= 2) answers = q.options.map(String);
    else if (q.type === 'boolean') answers = ['0','1'];
    else throw new Error('Type ou bornes de réponse invalides');
    if (JSON.stringify(Object.keys(rule.score_map).sort()) !== JSON.stringify(answers.sort()) || (rule.reverse && q.type !== 'scale')) throw new Error('Règles de scoring incompatibles avec les réponses permises');
    for (const points of Object.values(rule.score_map)) {
     if (!object(points) || Object.keys(points).length !== Object.keys(rule.dimension_weights).length || Object.keys(rule.dimension_weights).some(key => typeof points[key] !== 'number' || !Number.isFinite(points[key]) || points[key] < 0 || points[key] > 100)) {
      throw new Error('Points de scoring invalides.');
     }
    }
   }
   if (covered.size !== 9) throw new Error('Les règles doivent couvrir les neuf dimensions');
  }
  return {items, snapshot};
 }
 function applyImport(text) {
  const {items, snapshot} = decodeImport(text);
  if (snapshot) {
   editorForm.querySelector('[name="kind"]').value = 'enneagramme';
   editorForm.querySelector('[name="form_key"]').value = snapshot.form_key;
   editorForm.querySelector('[name="scoring_rules"]').value = JSON.stringify(snapshot.scoring_rules, null, 2);
   const source = editorForm.querySelector('[name="source_reference"]');
   if (!source.value && typeof snapshot.source_reference === 'string') source.value = snapshot.source_reference;
  }
  questions = items; render(); sync();
  status.textContent = `${questions.length} question(s) chargées.`;
  if (snapshot?.is_demo) status.textContent += ' Export DEMO : cochez explicitement « Questionnaire de démonstration (non validé) » avant de créer la version.';
  status.setAttribute('role', 'status');
 }
 function showImportError(error) {
  status.setAttribute('role', 'alert');
  status.textContent = `Import échoué : ${error.message || 'lecture du fichier impossible'}. L’éditeur précédent reste inchangé.`;
 }
 loadButton.addEventListener('click', async () => {
  loadButton.disabled = true; status.textContent = 'Lecture du JSON…';
  try {
   const file = fileInput.files?.[0];
   if (file && file.size > 200 * 1024) throw new Error('Le fichier dépasse la limite de 200 Ko');
   applyImport(file ? await file.text() : json.value);
  } catch (error) { showImportError(error); }
  finally { loadButton.disabled = false; }
 });
 try { applyImport(json.value); } catch (error) { showImportError(error); }
}
