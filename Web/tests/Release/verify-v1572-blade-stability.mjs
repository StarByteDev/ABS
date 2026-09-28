import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import {spawnSync} from 'node:child_process';

const roots=['resources/views'];
const files=[];
const walk=(dir)=>{for(const name of fs.readdirSync(dir)){const p=path.join(dir,name);const st=fs.statSync(p);if(st.isDirectory())walk(p);else if(p.endsWith('.blade.php'))files.push(p)}};
for(const root of roots) walk(root);
const fail=(msg)=>{console.error('FAIL:',msg);process.exit(1)};

const phpBlocks=[]; const echoes=[];
for(const file of files){
  const src=fs.readFileSync(file,'utf8');
  const ifCount=(src.match(/@if\s*\(/g)||[]).length+(src.match(/@hasSection\s*\(/g)||[]).length;
  const endifCount=(src.match(/@endif\b/g)||[]).length;
  if(ifCount!==endifCount) fail(`${file}: if/hasSection vs endif mismatch (${ifCount}/${endifCount})`);
  const pairs=[['foreach','endforeach'],['forelse','endforelse'],['for','endfor'],['while','endwhile'],['switch','endswitch'],['isset','endisset'],['unless','endunless'],['auth','endauth'],['guest','endguest'],['can','endcan'],['cannot','endcannot']];
  for(const [open,close] of pairs){
    const openRx=new RegExp('@'+open+'\\b','g');
    const closeRx=new RegExp('@'+close+'\\b','g');
    const oc=(src.match(openRx)||[]).length, cc=(src.match(closeRx)||[]).length;
    if(oc!==cc) fail(`${file}: ${open}/${close} mismatch (${oc}/${cc})`);
  }
  for(const match of src.matchAll(/@php\s*([\s\S]*?)\s*@endphp/g)) phpBlocks.push({file,code:match[1]});
  for(const match of src.matchAll(/\{\{\s*([\s\S]*?)\s*\}\}/g)) echoes.push({file,code:match[1]});
}

const chunks=(items,size)=>Array.from({length:Math.ceil(items.length/size)},(_,i)=>items.slice(i*size,(i+1)*size));
const lint=(items,kind)=>{
  let base=0;
  for(const chunk of chunks(items,80)){
    let php='<?php\n';
    chunk.forEach((item,i)=>{php+=kind==='block'?`function __b_${base+i}(){\n${item.code}\n}\n`:`function __e_${base+i}(){ return (${item.code}); }\n`;});
    const f=path.join(os.tmpdir(),`abs_v1572_${kind}_${base}.php`);fs.writeFileSync(f,php);
    const r=spawnSync('php',['-l',f],{encoding:'utf8'});
    if(r.status!==0) fail(`${kind} PHP syntax failed near batch ${base}: ${r.stdout||r.stderr}`);
    base+=chunk.length;
  }
};
lint(phpBlocks,'block'); lint(echoes,'echo');
console.log(`PASS: ${files.length} Blade views; ${phpBlocks.length} PHP blocks; ${echoes.length} echo expressions`);
