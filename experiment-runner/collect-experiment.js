import { execFileSync, spawnSync } from 'node:child_process';
import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

export const CONDITIONS = Object.freeze({
  A: Object.freeze({ tracking_mode: 'client_only', blocking_mode: 'none' }),
  B: Object.freeze({ tracking_mode: 'server_augmented', blocking_mode: 'none' }),
  C: Object.freeze({ tracking_mode: 'client_only', blocking_mode: 'controlled' }),
  D: Object.freeze({ tracking_mode: 'server_augmented', blocking_mode: 'controlled' }),
});

export function buildSchedule() {
  const labels = Object.keys(CONDITIONS);
  return Array.from({ length: 10 }, (_, round) => labels.slice(round % 4).concat(labels.slice(0, round % 4))
    .map((condition_label, offset) => ({
      schedule_position: round * 4 + offset + 1,
      round: round + 1,
      condition_label,
      replication_index: round + 1,
      ...CONDITIONS[condition_label],
      run_id: null, status: 'planned', validation: null, artifact_directory: null, server_queue_result: null,
    }))).flat();
}

export function validateBrowserSummary(summary, slot) {
  const problems = [];
  if (summary?.status !== 'completed') problems.push('run status is not completed');
  if (summary?.ground_truth_event_count !== 4) problems.push('ground-truth event count is not 4');
  if (summary?.ga4_js_invocation_count !== 4) problems.push('GA4 JS invocation count is not 4');
  if (JSON.stringify(summary?.ground_truth_event_names) !== JSON.stringify(['view_item', 'add_to_cart', 'begin_checkout', 'purchase'])) problems.push('canonical event sequence differs');
  if (summary?.unmatched_ga4_request_count !== 0) problems.push('unmatched GA4 requests are present');
  if (slot.blocking_mode === 'none') {
    if (summary?.ga4_uuid_correlated_network_count !== 4) problems.push('four UUID-correlated GA4 transports were not observed');
    if (summary?.ga4_controlled_block_count !== 0) problems.push('controlled blocks were observed in an unblocked condition');
  } else {
    if (summary?.ga4_network_event_request_count !== 0) problems.push('successful GA4 event transport was observed while controlled blocking');
    if (summary?.ga4_loader_block_count !== 4) problems.push('four controlled GA4 loader blocks were not observed');
  }
  return problems;
}

function parseArgs(argv) {
  const value = (prefix, fallback = null) => argv.find((arg) => arg.startsWith(prefix))?.slice(prefix.length) ?? fallback;
  return { batchId: value('--batch-id='), resumeId: value('--resume='), dryRun: argv.includes('--dry-run'), execute: argv.includes('--execute') };
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  if ((!options.batchId && !options.resumeId) || options.dryRun === options.execute) throw new Error('use --batch-id=<id> with exactly one of --dry-run or --execute; resume requires --resume=<id> --execute');
  const batchId = options.resumeId ?? options.batchId;
  const root = join(process.cwd(), 'storage', 'app', 'research', 'collections', batchId);
  const manifestPath = join(root, 'manifest.json');
  const sha = git('rev-parse', 'HEAD');
  let manifest;
  try { manifest = JSON.parse(await readFile(manifestPath, 'utf8')); } catch { manifest = newManifest(batchId, sha); }
  assertManifest(manifest, batchId, sha);
  if (options.dryRun) { console.log(JSON.stringify(manifest, null, 2)); return; }
  if (manifest.runs.some((run) => run.status === 'failed')) throw new Error('manifest contains a failed position; resolve it deliberately before resuming');
  await preflight();
  await mkdir(root, { recursive: true });
  await atomicWrite(manifestPath, manifest);
  for (const slot of manifest.runs.filter((run) => run.status === 'planned')) {
    const child = spawnSync(process.execPath, ['experiment-runner/run-baseline.js', `--tracking-mode=${slot.tracking_mode}`, `--blocking-mode=${slot.blocking_mode}`, '--observation-ms=10000', `--batch-id=${batchId}`, '--collection-role=final', `--condition-label=${slot.condition_label}`, `--replication=${slot.replication_index}`, `--schedule-position=${slot.schedule_position}`], { cwd: process.cwd(), encoding: 'utf8' });
    const summary = jsonFrom(child.stdout);
    slot.run_id = summary?.run_id ?? null;
    slot.artifact_directory = summary?.artifact_directory ?? null;
    const problems = validateBrowserSummary(summary, slot);
    slot.validation = { browser: problems.length === 0 ? 'passed' : 'failed', problems };
    slot.status = child.status === 0 && problems.length === 0 ? 'completed' : 'failed';
    if (slot.status === 'completed' && slot.tracking_mode === 'server_augmented') {
      const queue = spawnSync('php', ['artisan', 'queue:work', '--queue=tracking,default', '--stop-when-empty'], { cwd: process.cwd(), encoding: 'utf8' });
      slot.server_queue_result = queue.status === 0 ? 'completed' : 'failed';
      if (queue.status !== 0) { slot.status = 'failed'; slot.validation.problems.push('one-shot tracking queue worker failed'); }
    }
    if (slot.status === 'completed') {
      const server = spawnSync('php', ['artisan', 'research:verify-final-server-run', slot.run_id], { cwd: process.cwd(), encoding: 'utf8' });
      const serverEvidence = jsonFrom(server.stdout);
      slot.validation.server = serverEvidence?.valid ? 'passed' : 'failed';
      if (server.status !== 0 || !serverEvidence?.valid) { slot.status = 'failed'; slot.validation.problems.push('persisted GA4 server-dispatch evidence differs from the condition expectation'); }
    }
    await atomicWrite(manifestPath, manifest);
    if (slot.status === 'failed') throw new Error(`collection stopped at position ${slot.schedule_position}: ${slot.condition_label} replication ${slot.replication_index}; run ${slot.run_id ?? 'not created'}; ${slot.validation.problems.join('; ')}`);
  }
}

function newManifest(batchId, gitCommit) {
  return { batch_id: batchId, created_at: new Date().toISOString(), git_commit: gitCommit, collector_version: '1.0.0', fixed_conditions: { browser: 'chromium', headless: true, observation_window_ms: 10000, privacy_mode: 'standard', consent_mode: 'full', service_workers: 'block', routing_enabled: true }, planned_schedule: 'deterministic counterbalanced rotation A-B-C-D', runs: buildSchedule() };
}
function assertManifest(manifest, batchId, sha) {
  if (manifest.batch_id !== batchId || manifest.git_commit !== sha) throw new Error('batch manifest does not match the current Git revision');
  const fixed = manifest.fixed_conditions;
  if (fixed?.observation_window_ms !== 10000 || fixed?.privacy_mode !== 'standard' || fixed?.consent_mode !== 'full' || fixed?.headless !== true || fixed?.browser !== 'chromium') throw new Error('batch fixed conditions are not the final collection settings');
  if (!Array.isArray(manifest.runs) || manifest.runs.length !== 40) throw new Error('batch manifest does not contain the required 40 positions');
}
async function preflight() {
  if (git('status', '--porcelain') !== '') throw new Error('final collection requires a clean Git working tree');
  const env = await envFile();
  if (!env.GA4_MEASUREMENT_ID || !env.GA4_SERVER_MEASUREMENT_ID || !env.GA4_SERVER_API_SECRET) throw new Error('required GA4 configuration is missing');
  if (env.GA4_MEASUREMENT_ID === env.GA4_SERVER_MEASUREMENT_ID) throw new Error('GA4 client and server Measurement IDs must differ');
  const baseUrl = (env.APP_URL || 'http://localhost').replace(/\/$/, '');
  let response;
  try { response = await fetch(baseUrl, { signal: AbortSignal.timeout(5000) }); } catch { throw new Error('application URL is unreachable'); }
  if (!response.ok) throw new Error('application URL is unreachable');
  const browser = await chromium.launch({ headless: true });
  await browser.close();
}
async function envFile() {
  const text = await readFile(join(process.cwd(), '.env'), 'utf8');
  return Object.fromEntries(text.split(/\r?\n/).filter((line) => line && !line.startsWith('#')).map((line) => { const index = line.indexOf('='); return index < 0 ? [line, ''] : [line.slice(0, index), line.slice(index + 1).replace(/^['"]|['"]$/g, '')]; }));
}
async function atomicWrite(path, value) { const temporary = `${path}.tmp`; await writeFile(temporary, `${JSON.stringify(value, null, 2)}\n`); await rename(temporary, path); }
function jsonFrom(output = '') { const start = output.indexOf('{'); try { return start < 0 ? null : JSON.parse(output.slice(start)); } catch { return null; } }
function git(...arguments_) { return execFileSync('git', arguments_, { encoding: 'utf8' }).trim(); }
if (import.meta.url === pathToFileURL(process.argv[1]).href) main().catch((error) => { console.error(error.message); process.exitCode = 1; });
