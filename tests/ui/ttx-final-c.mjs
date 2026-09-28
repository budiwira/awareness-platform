// Real Vue surface regression; isolated HTTP fixtures, not authenticated acceptance.
import assert from 'node:assert/strict';
import { resolve } from 'node:path';
import { createServer } from 'vite';
import vue from '@vitejs/plugin-vue';
import { chromium } from 'playwright';

// Opt-in development acceptance only. IDs must be explicitly supplied for QA sessions.
if (process.argv.includes('--runtime')) {
    const ids = process.argv.slice(process.argv.indexOf('--runtime') + 1).map(Number);
    assert.ok(ids.length && ids.every((id) => Number.isSafeInteger(id) && id > 0));
    const base = process.env.TTX_QA_URL || 'http://127.0.0.1:8002';
    assert.equal(new URL(base).hostname, '127.0.0.1');
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.goto(base + '/login');
        await page.getByRole('textbox', { name: 'Email', exact: true }).fill('admin@demo.io');
        await page.getByRole('textbox', { name: 'Password', exact: true }).fill('demo1234');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await page.waitForURL('**/tenant/dashboard');
        page.on('dialog', (dialog) => dialog.accept());
        for (const id of ids) {
            await page.goto(base + '/ttx/sessions/' + id + '/debrief');
            await page.locator('.debrief-page').waitFor();
            assert.match(await page.locator('h1').last().innerText(), /QA/);
            const snapshot = await page.request.get(base + '/ttx/sessions/' + id + '/debrief', { headers: { Accept: 'application/json' } });
            const payload = await snapshot.json();
            if (payload.session.status === 'completed') {
                console.log('Authenticated development QA: session ' + id + ' already completed, verifying participant Result below.');
                continue;
            }
            assert.equal(payload.session.status, 'debrief');
            const version = payload.session.response_contract_version;
            const required = payload.dimensions.filter((item) => item.required);
            if (id !== 5) {
                assert.equal(required.length, version >= 2 ? 2 : 6);
                const cards = page.locator('.evaluation-card').filter({ has: page.locator('.dimension-title') });
                for (const item of required) {
                    const index = payload.dimensions.findIndex((entry) => entry.dimension === item.dimension);
                    if (version >= 2) {
                        await cards.nth(index).locator('textarea').nth(0).fill('QA observed: no official team response for the released situation.');
                        await cards.nth(index).locator('textarea').nth(1).fill('QA finding: response ownership needs explicit confirmation.');
                    }
                    await cards.nth(index).locator('select').selectOption('developing');
                }
                await page.getByRole('textbox', { name: 'Ringkasan keseluruhan', exact: true }).fill('QA safe summary: clarify team handoffs.');
                await page.getByRole('textbox', { name: 'Action', exact: true }).fill('QA clarify handoff');
                await page.getByRole('button', { name: 'Simpan Evaluasi', exact: true }).click();
                await page.getByText('Evaluasi kualitatif disimpan.').waitFor();
                assert.equal(await page.getByRole('textbox', { name: 'Ringkasan keseluruhan', exact: true }).inputValue(), 'QA safe summary: clarify team handoffs.');
                assert.equal(await page.getByRole('textbox', { name: 'Action', exact: true }).inputValue(), 'QA clarify handoff');
                await page.getByRole('textbox', { name: 'Pemilik', exact: true }).fill('SOC Lead');
                await page.getByRole('combobox', { name: 'Kategori (opsional)', exact: true }).selectOption('corrective_action');
                await page.getByRole('combobox', { name: 'Capability (opsional)', exact: true }).selectOption('EX-1');
                if (version >= 2) await page.getByRole('combobox', { name: 'Fase Playbook (opsional)', exact: true }).selectOption('detection_validation');
                await page.getByRole('button', { name: 'Tambah', exact: true }).click();
                await page.getByText('Action item ditambahkan.').waitFor();
                await page.getByRole('textbox', { name: 'Kekuatan', exact: true }).fill('QA clear scenario context.');
                await page.getByRole('textbox', { name: 'Area perbaikan', exact: true }).fill('QA improve handoff confirmation.');
                await page.getByRole('textbox', { name: 'Pelajaran utama', exact: true }).fill('QA document receipt of responsibility.');
                await page.getByRole('button', { name: 'Simpan Ringkasan', exact: true }).click();
                await page.getByText('After-Action Summary disimpan.').waitFor();
            }
            await page.reload();
            await page.getByRole('button', { name: 'Selesaikan Sesi', exact: true }).click();
            try { await page.waitForURL('**/ttx/sessions/' + id + '/result'); }
            catch (error) { throw new Error('Finalization session ' + id + ': ' + await page.locator('.debrief-page').innerText(), { cause: error }); }
            await page.getByText('Debrief telah final').waitFor();
            assert.equal(await page.locator('input, textarea, select').count(), 0);
            if (version >= 2) assert.match(await page.locator('.report-fields').first().innerText(), /Finding/);
            const cookie = (await page.context().cookies()).find((item) => item.name === 'XSRF-TOKEN');
            const locked = await page.request.put(base + '/tenant/ttx/sessions/' + id + '/evaluation', {
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) },
                data: { evaluations: [{ dimension: 'detection_triage', rating: 'strong', finding: 'Forbidden tamper' }] },
            });
            assert.equal(locked.status(), 422);
            console.log('Authenticated development QA: session ' + id + ' V' + version + ' save/reload/finalize/result + completed-write rejection PASS');
        }
        const participant = await browser.newPage();
        await participant.goto(base + '/login');
        await participant.getByRole('textbox', { name: 'Email', exact: true }).fill('budi@demo.io');
        await participant.getByRole('textbox', { name: 'Password', exact: true }).fill('demo1234');
        await participant.getByRole('button', { name: 'Masuk', exact: true }).click();
        await participant.waitForURL('**/me/dashboard');
        for (const id of ids) {
            await participant.goto(base + '/ttx/sessions/' + id + '/participant-result');
            await participant.getByText('After-Action Summary', { exact: true }).waitFor();
            const response = await participant.request.get(base + '/ttx/sessions/' + id, { headers: { Accept: 'application/json' } });
            const body = await response.json();
            assert.equal(body.teams.length, 1);
            assert.ok(!JSON.stringify(body).includes('QA finding:'));
            assert.ok(!('dimensions' in body) && !('action_items' in body));
            console.log('Authenticated participant QA: own-team safe Result session ' + id + ' PASS');
        }
    } finally { await browser.close(); }
    process.exit(0);
}

const phases = [{ key: 'escalation', title: 'Escalation & Ownership', guidance: 'Review escalation effectiveness.', capability_codes: ['EX-2'] }];
const fixture = () => ({
    session: {
        id: 1, title: 'FINAL-C browser exercise', status: 'debrief', response_contract_version: 2,
        scenario: 'Snapshot Scenario', scenario_snapshot: { scenario: 'Historical incident context.', capability_codes: ['EX-1', 'EX-2'] },
        playbook: { title: 'Snapshot Playbook', structured_phases: phases }, duration_minutes: 30,
        teams: [{ id: 1, name: 'SOC', participants: [{ id: 2, name: 'SOC participant' }], responsibility_assignments: [{ id: 1, role: 'primary', playbook_phase_key: 'escalation' }] },
            { id: 2, name: 'IT Operations', participants: [], responsibility_assignments: [{ id: 2, role: 'support', playbook_phase_key: 'escalation' }] }],
        injects: [{ id: 1, order: 1, snapshot: { title: 'Released inject', situation: 'Observed incident.' }, team_responses: [
            { team: { id: 1, name: 'SOC' }, response: { decision: 'Contain account', rationale: 'Confirmed abuse', immediate_actions: 'Revoke session', coordination_handoff: 'SOC hands recovery to Operations', submitted_at: '2026-09-28T01:00:00Z' } },
            { team: { id: 2, name: 'IT Operations' }, response: null },
        ] }],
    },
    dimensions: ['detection_triage', 'escalation_ownership', 'containment_decision', 'cross_functional_coordination', 'incident_communication', 'recovery_improvement'].map((dimension, i) => ({
        dimension, code: 'EX-' + (i + 1), label: dimension, required: i < 2, has_evaluation: i === 5,
        rating: i === 5 ? 'developing' : null, evidence: null, finding: i === 5 ? 'Historical outside-subset finding' : null,
    })),
    rating_options: [{ value: 'effective', label: 'Efektif' }, { value: 'developing', label: 'Berkembang' }],
    action_items: [{ id: 1, title: 'Existing action', owner: 'SOC', priority: 'medium', status: 'open', category: null, capability_code: null, playbook_phase_key: null, due_date: '2026-10-10' }],
    after_action_summary: null, editable: true,
});
const main = `
import { createApp, h, reactive, ref } from 'vue';
import '@/../css/app.css';
import Debrief from '@/Pages/Tenant/Ttx/Sessions/Debrief.vue';
window.route = (name) => '/api/' + name;
const props = reactive({ sessionId: 1, resultMode: false });
const key = ref(0);
window.__result = () => { props.resultMode = true; key.value++; };
const app = createApp({ setup: () => () => h(Debrief, { ...props, key: key.value }) });
app.config.globalProperties.route = window.route;
app.mount('#app');
`;
const inertia = `import { h } from 'vue'; export const Head = () => null; export const Link = { setup(_, { attrs, slots }) { return () => h('a', attrs, slots.default?.()); } }; export const router = { visit() { window.__result(); } };`;
const layout = `import { h } from 'vue'; export default { setup(_, { slots }) { return () => h('main', { style: 'padding:24px;max-width:1200px;margin:auto;min-width:0' }, slots.default?.()); } };`;
const server = await createServer({
    configFile: false, root: process.cwd(),
    resolve: { alias: [{ find: '@inertiajs/vue3', replacement: 'virtual:inertia' }, { find: '@/Layouts/AppLayout.vue', replacement: 'virtual:layout' }, { find: '@', replacement: resolve('resources/js') }] },
    optimizeDeps: { exclude: ['virtual:inertia', 'virtual:layout'] },
    plugins: [{
        name: 'final-c-fixtures',
        resolveId(id) { if (['virtual:main', 'virtual:inertia', 'virtual:layout'].includes(id)) return '\0' + id; },
        load(id) { return { '\0virtual:main': main, '\0virtual:inertia': inertia, '\0virtual:layout': layout }[id]; },
        configureServer(vite) { vite.middlewares.use('/__final_c', (_, res) => { res.setHeader('Content-Type', 'text/html'); res.end('<!doctype html><html lang="id"><meta name="viewport" content="width=device-width,initial-scale=1"><body><div id="app"></div><script type="module" src="/@id/virtual:main"></script></body></html>'); }); },
    }, vue()],
    server: { host: '127.0.0.1', port: 5188, strictPort: true },
});
await server.listen();
const browser = await chromium.launch();
let checks = 0;
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));
    let state = fixture();
    const writes = [];
    await page.route('**/api/**', async (route) => {
        const request = route.request();
        const body = request.postDataJSON();
        const url = request.url();
        if (request.method() === 'GET') return route.fulfill({ json: state });
        writes.push({ url, body });
        if (url.endsWith('evaluation.update')) {
            for (const entry of body.evaluations) Object.assign(state.dimensions.find((item) => item.dimension === entry.dimension), entry);
        } else if (url.endsWith('aar.update')) state.after_action_summary = body;
        else if (url.endsWith('action-items.store')) {
            const item = { id: 2, ...body }; state.action_items.push(item);
            return route.fulfill({ status: 201, json: item });
        } else if (url.endsWith('action-items.update')) {
            Object.assign(state.action_items[0], body);
            if (body.due_date) state.action_items[0].due_date = body.due_date + 'T00:00:00.000000Z';
            return route.fulfill({ json: state.action_items[0] });
        } else if (url.endsWith('complete')) {
            assert.ok(state.dimensions.filter((item) => item.required).every((item) => item.evidence && item.finding && item.rating));
            state.session.status = 'completed'; state.editable = false;
        }
        await route.fulfill({ json: state });
    });
    await page.goto('http://127.0.0.1:5188/__final_c');
    await page.locator('.debrief-page').waitFor();
    assert.match(await page.locator('body').innerText(), /2 dimensi wajib/);
    assert.match(await page.locator('body').innerText(), /Primary[\s\S]*Support/);
    assert.match(await page.locator('body').innerText(), /SOC hands recovery to Operations/);
    assert.match(await page.locator('body').innerText(), /Tidak ada respons/);
    await page.getByText('Lihat fase panduan').click();
    assert.match(await page.locator('body').innerText(), /Review escalation effectiveness/);
    checks += 5;
    const cards = page.locator('.evaluation-card').filter({ has: page.locator('.dimension-title') });
    const aar = page.locator('.aar-grid textarea');
    const action = page.locator('.action-list .action-card').first();
    await aar.nth(0).fill('Unsaved AAR draft');
    await action.locator('input').nth(0).fill('Unsaved action draft');
    for (let i = 0; i < 2; i++) {
        await cards.nth(i).locator('textarea').nth(0).fill('Observed evidence ' + i);
        await cards.nth(i).locator('textarea').nth(1).fill('Finding effectiveness ' + i);
        await cards.nth(i).locator('select').selectOption('effective');
    }
    await page.getByRole('button', { name: 'Simpan Evaluasi', exact: true }).click();
    await page.getByText('Evaluasi kualitatif disimpan.').waitFor();
    assert.equal(await aar.nth(0).inputValue(), 'Unsaved AAR draft');
    assert.equal(await action.locator('input').nth(0).inputValue(), 'Unsaved action draft');
    assert.equal(writes[0].body.evaluations[0].finding, 'Finding effectiveness 0');
    assert.equal(await cards.nth(5).locator('textarea').nth(1).inputValue(), 'Historical outside-subset finding');
    checks += 4;
    await cards.nth(0).locator('textarea').nth(1).fill('Updated finding draft');
    for (let i = 1; i < 4; i++) await aar.nth(i).fill('Safe AAR section ' + i);
    await page.getByRole('button', { name: 'Simpan Ringkasan' }).click();
    await page.getByText('After-Action Summary disimpan.').waitFor();
    assert.equal(await cards.nth(0).locator('textarea').nth(1).inputValue(), 'Updated finding draft');
    assert.equal(await action.locator('input').nth(0).inputValue(), 'Unsaved action draft');
    checks += 2;
    await aar.nth(0).fill('Second unsaved AAR draft');
    await action.locator('select').nth(2).selectOption('playbook_improvement');
    await action.locator('select').nth(3).selectOption('EX-2');
    await action.locator('select').nth(4).selectOption('escalation');
    await action.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.getByText('Action item diperbarui.').waitFor();
    assert.equal(await cards.nth(0).locator('textarea').nth(1).inputValue(), 'Updated finding draft');
    assert.equal(await aar.nth(0).inputValue(), 'Second unsaved AAR draft');
    assert.equal(state.action_items[0].category, 'playbook_improvement');
    assert.equal(await action.locator('input[type="date"]').inputValue(), '2026-10-10');
    assert.equal(writes.find((entry) => entry.url.endsWith('action-items.update')).body.due_date, '2026-10-10');
    checks += 5;
    const newAction = page.locator('.new-action');
    await newAction.locator('input').nth(0).fill('Optional unlinked action');
    await newAction.locator('input').nth(1).fill('Management');
    await newAction.getByRole('button', { name: 'Tambah', exact: true }).click();
    await page.getByText('Action item ditambahkan.').waitFor();
    assert.equal(await aar.nth(0).inputValue(), 'Second unsaved AAR draft');
    assert.equal(await cards.nth(0).locator('textarea').nth(1).inputValue(), 'Updated finding draft');
    checks += 2;
    await page.getByRole('button', { name: 'Simpan Evaluasi', exact: true }).click();
    await page.getByText('Evaluasi kualitatif disimpan.').waitFor();
    await page.getByRole('button', { name: 'Simpan Ringkasan' }).click();
    await page.getByText('After-Action Summary disimpan.').waitFor();
    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Selesaikan Sesi' }).click();
    await page.getByText('Debrief telah final').waitFor();
    assert.equal(await page.locator('input, textarea, select').count(), 0);
    assert.match(await page.locator('body').innerText(), /Updated finding draft/);
    assert.match(await page.locator('body').innerText(), /Perbaikan Playbook/);
    checks += 3;
    for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1));
        checks++;
    }
    state = fixture(); state.session.response_contract_version = 1; state.dimensions.forEach((item) => item.required = true);
    state.session.playbook = { title: 'Legacy Playbook', content: 'Legacy plain text reference' };
    await page.reload(); await page.locator('.debrief-page').waitFor();
    assert.match(await page.locator('body').innerText(), /6 dimensi wajib/);
    await page.getByText('Lihat fase panduan').click();
    assert.match(await page.locator('body').innerText(), /Legacy plain text reference/);
    assert.deepEqual(errors, []);
    checks += 3;
    console.log('TTX-FINAL-C browser: ' + checks + ' checks passed (real Vue surface; isolated HTTP fixtures).');
} finally {
    await browser.close();
    await server.close();
}
