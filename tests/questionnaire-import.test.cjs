const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
 constructor() { this.value = ''; this.children = []; this.listeners = {}; this.attributes = {}; this.checked = false; }
 append(...nodes) { this.children.push(...nodes); }
 replaceChildren(...nodes) { this.children = nodes; }
 addEventListener(name, handler) { this.listeners[name] = handler; }
 setAttribute(name, value) { this.attributes[name] = value; }
}
async function run(snapshots) {
 const list = new Element(), status = new Element(), button = new Element(), add = new Element();
 const controls = Object.fromEntries(['questions_file','kind','form_key','scoring_rules','source_reference','is_demo','licensed'].map(name => [name,new Element()]));
 controls.questions_file.files = [];
 const json = new Element(); json.value = JSON.stringify([{id:'one',label:'First',type:'text'},{id:'two',label:'Second',type:'text'}]);
 const form = {querySelector: selector => controls[selector.match(/name="([^"]+)"/)[1]]};
 const builder = {closest: () => form, querySelector: selector => ({'[data-builder-list]':list,'[data-builder-status]':status,'[data-load-json]':button,'[data-add-question]':add})[selector]};
 const document = {querySelector: selector => selector === '[data-question-builder]' ? builder : selector === '[data-questions-json]' ? json : null, querySelectorAll: () => [], createElement: () => new Element(), createTextNode: text => text};
 vm.runInNewContext(fs.readFileSync('public/assets/app.js','utf8'),{document,window:{}});
 assert.equal(list.children.length,2);
 const load = async text => {controls.questions_file.files=[{size:Buffer.byteLength(text),text:async()=>text}];await button.listeners.click();};
 for (const snapshot of snapshots) {
  await load(JSON.stringify({...snapshot,approved_by:123,content_status:'APPROVED',licensed:true}));
  assert.match(status.textContent,/^9 question\(s\) chargées/);
  assert.equal(list.children.length,9);
  assert.equal(controls.form_key.value,snapshot.form_key);
  assert.equal(controls.kind.value,'enneagramme');
  assert.deepEqual(JSON.parse(json.value),snapshot.questions);
  assert.deepEqual(JSON.parse(controls.scoring_rules.value),snapshot.scoring_rules);
  assert.equal(controls.is_demo.checked,false);
  assert.equal(controls.licensed.checked,false);
 }
 const invalid = [JSON.stringify([{id:'bad',label:'Bad',type:'choice',options:'wrong'}]), '{', '{}', JSON.stringify({questions:[]}), JSON.stringify({...snapshots[0],scoring_rules:null}), JSON.stringify({...snapshots[0],scoring_rules:{...snapshots[0].scoring_rules,items:{}}})];
 const missingAnswer=JSON.parse(JSON.stringify(snapshots[0]));delete missingAnswer.scoring_rules.items[missingAnswer.questions[0].id].score_map['5'];invalid.push(JSON.stringify(missingAnswer));
 for (const text of invalid) {
  const before=json.value;
  await load(text);
  assert.match(status.textContent,/^Import échoué/);
  assert.equal(status.attributes.role,'alert');
  assert.equal(json.value,before);
  assert.equal(list.children.length,9);
  assert.equal(button.disabled,false);
 }
 controls.questions_file.files=[{size:1,text:async()=>{throw new Error('Lecture impossible');}}];
 await button.listeners.click();assert.match(status.textContent,/Import échoué/);
 controls.questions_file.files=[];json.value=JSON.stringify([{id:'plain',label:'Plain',type:'text'}]);
 await button.listeners.click();assert.equal(list.children.length,1);assert.match(status.textContent,/^1 question/);
 console.log('PASS: real editor click, canonical A/B/C, rules, explicit DEMO/license, malformed formats, read failure and plain-list compatibility');
}
let input='';process.stdin.setEncoding('utf8');process.stdin.on('data',chunk=>input+=chunk);process.stdin.on('end',()=>run(JSON.parse(input)).catch(error=>{console.error(error);process.exitCode=1;}));
