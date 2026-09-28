import fs from 'node:fs';
const must=(cond,msg)=>{if(!cond){console.error('FAIL:',msg);process.exit(1)}};
const read=p=>fs.readFileSync(p,'utf8');
const scanner=read('app/Services/PulseScannerService.php');
const research=read('app/Services/PulseStrategyResearchService.php');
const cycle=read('app/Services/PulseStrategyCycleService.php');
const workflow=read('app/Http/Controllers/Admin/AdminStrategyWorkflowController.php');
const layout=read('resources/views/admin/layout.blade.php');
const overview=read('resources/views/admin/pulse/workflow/overview.blade.php');
const scans=read('resources/views/admin/pulse/workflow/scan-signals.blade.php');
const paper=read('resources/views/admin/pulse/workflow/paper-trades.blade.php');
const members=read('resources/views/admin/dashboard.blade.php');
const css=read('public/assets/css/admin-flow-v1550.css');

must(scanner.includes('foreach ($qualifiedCandidates as $rank => $candidate)'), 'system research persists each qualified candidate');
must(scanner.includes("'type' => 'qualified_signal_set'"), 'multi-signal set recorded in scan summary');
must(scanner.includes('if ($user)') && scanner.includes('Member / Professional scans remain a single Best Signal experience.'), 'member Best Signal flow preserved');
must(scanner.includes('equivalentOpenSystemSignal'), 'per-signal unresolved setup deduplication present');
must(!research.includes('reuseEquivalentOpenSignal($run)'), 'legacy best-only research deduplication removed');
must(research.includes("'deduplication_scope' => 'per_qualified_signal'"), 'research metadata records per-signal deduplication');

const validationPos=cycle.indexOf('$validation = $this->validation->process(500);');
const scanPos=cycle.indexOf('$scan = $this->research->runIfDue($force);');
must(validationPos>=0 && scanPos>=0 && validationPos<scanPos, 'existing paper positions are checked before new signals are created');
must(cycle.includes('queued for entry validation on the next market cycle'), 'next-cycle entry lifecycle is explicit');

must(workflow.includes("'signals'=>$signals"), 'scan register builds full signal collection');
must(scans.includes('Signals in this scan') && scans.includes('wf-signal-chip'), 'scan page displays multiple signals');
must(paper.includes('Waiting entry') && paper.includes('next synchronized market cycle'), 'paper trade page explains execution timing');

must(!overview.toLowerCase().includes('ceo'), 'strategy overview has no internal CEO wording');
must(overview.includes('Pulse Strategy Overview') && overview.includes('VALIDATION WORKFLOW'), 'modern strategy overview retained');
must(layout.includes('Members &amp; Access') && layout.includes('Access &amp; Renewals'), 'member navigation follows task-based category approach');
must(!layout.includes('<span>1</span><span>Price Source') && !layout.includes('<span>01</span>Performance'), 'workflow links are not numbered');
must(members.includes('Members Overview') && members.includes('MEMBER WORKFLOW'), 'member overview is a short task-based landing page');

must(layout.includes('admin-flow-v1550.css') && layout.includes('abs-admin-v1550'), 'V15.5 brand layer loaded last');
must(css.includes('Final ABS Pulse admin brand lock') && css.includes('--abs1550-gold') && css.includes('--abs1550-cyan'), 'ABS Pulse palette lock present');
must(css.includes('.admin-capability-toggle') && css.includes('.admin-market-mode-card'), 'legacy package configuration cards normalized to ABS Pulse branding');

console.log('PASS: ABS V15.5.0 multi-signal strategy validation and Admin flow contract');
