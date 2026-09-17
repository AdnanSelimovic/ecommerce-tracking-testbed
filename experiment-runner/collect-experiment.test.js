import test from 'node:test';
import assert from 'node:assert/strict';
import { buildSchedule, validateBrowserSummary } from './collect-experiment.js';

test('final collection schedule has forty counterbalanced positions and ten per condition', () => {
  const schedule = buildSchedule();
  assert.equal(schedule.length, 40);
  assert.deepEqual(schedule.slice(0, 8).map((slot) => slot.condition_label), ['A', 'B', 'C', 'D', 'B', 'C', 'D', 'A']);
  assert.deepEqual(Object.fromEntries(['A', 'B', 'C', 'D'].map((label) => [label, schedule.filter((slot) => slot.condition_label === label).length])), { A: 10, B: 10, C: 10, D: 10 });
});

test('browser invariant validation accepts the expected non-blocked and controlled evidence', () => {
  const base = { status: 'completed', ground_truth_event_count: 4, ground_truth_event_names: ['view_item', 'add_to_cart', 'begin_checkout', 'purchase'], ga4_js_invocation_count: 4, unmatched_ga4_request_count: 0, ga4_controlled_block_count: 0, ga4_uuid_correlated_network_count: 4, ga4_network_event_request_count: 4, ga4_loader_block_count: 0 };
  assert.deepEqual(validateBrowserSummary(base, { blocking_mode: 'none' }), []);
  assert.deepEqual(validateBrowserSummary({ ...base, ga4_uuid_correlated_network_count: 0, ga4_network_event_request_count: 0, ga4_controlled_block_count: 4, ga4_loader_block_count: 4 }, { blocking_mode: 'controlled' }), []);
  assert.match(validateBrowserSummary({ ...base, ground_truth_event_count: 3 }, { blocking_mode: 'none' }).join(' '), /ground-truth/);
});
