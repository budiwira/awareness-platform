// Isolated tests of the real Vue surfaces. No Laravel server, database,
// migration, fixture command, or existing E2E global setup is invoked.
import assert from "node:assert/strict";
import { resolve } from "node:path";
import { createServer } from "vite";
import vue from "@vitejs/plugin-vue";
import { chromium } from "playwright";

const root = process.cwd();
const phases = [
    {
        key: "detection_validation",
        title: "Detection & Validation",
        guidance: "FACILITATOR GUIDANCE",
        participant_summary: "Validasi fakta yang tersedia.",
        capability_codes: ["EX-1"],
        team_role: "primary",
    },
    {
        key: "escalation_ownership",
        title: "Escalation & Ownership",
        guidance: "FACILITATOR ESCALATION",
        participant_summary: "Koordinasikan respons organisasi.",
        capability_codes: ["EX-2"],
        team_role: "support",
    },
];
const capabilities = [
    {
        code: "EX-1",
        label: "Detection & Triage",
        description: "Mendeteksi indikasi insiden.",
    },
    {
        code: "EX-2",
        label: "Escalation & Ownership",
        description: "Menetapkan ownership insiden.",
    },
];
const teams = [
    {
        id: 1,
        name: "Security / SOC",
        responsibilities: null,
        description: null,
        participant_count: 2,
        participants: [{ name: "Anggota SOC" }, { name: "Rekan SOC" }],
        responsibility_assignments: [
            {
                id: 1,
                playbook_phase_key: "detection_validation",
                role: "primary",
            },
            {
                id: 2,
                playbook_phase_key: "escalation_ownership",
                role: "support",
            },
        ],
    },
    {
        id: 2,
        name: "Operations",
        responsibilities: null,
        description: null,
        participant_count: 1,
        participants: [{ name: "Anggota Operations" }],
        responsibility_assignments: [
            {
                id: 3,
                playbook_phase_key: "escalation_ownership",
                role: "primary",
            },
        ],
    },
];
const snapshot = {
    title: "Credential incident",
    scenario:
        "Aktivitas autentikasi mencurigakan memerlukan koordinasi organisasi.",
    objectives: "Validasi dan eskalasi.",
    scope: "45 menit",
};
const initial = {
    id: 1,
    title: "FINAL-B browser fixture",
    status: "in_progress",
    response_contract_version: 2,
    scenario: snapshot.title,
    scenario_snapshot: snapshot,
    capabilities,
    participant_count: 3,
    facilitator_name: "Tenant Admin",
    participant_team: teams[0],
    teams: [teams[0]],
    playbook: {
        title: "Historical Playbook",
        description: "Panduan organisasi.",
        structured_phases: phases.map(({ guidance, ...phase }) => phase),
    },
    progress: { current: 1, total: 1 },
    injects: [
        {
            id: 10,
            order: 1,
            status: "active",
            snapshot: {
                title: "Suspicious authentication",
                description: "Situasi aktual untuk diskusi tim.",
            },
            response: null,
        },
    ],
};
const preparation = {
    session: { id: 1, title: initial.title, status: "draft" },
    exercise_title: snapshot.title,
    exercise_context: snapshot,
    playbook: { ...initial.playbook, structured_phases: phases },
    capabilities,
    relevant_playbook_phase_keys: phases.map((phase) => phase.key),
    inject_count: 2,
    facilitator: { name: "Tenant Admin" },
    teams,
    participants: [
        { id: 1, assignment_id: 1, name: "Anggota SOC", team_id: 1 },
    ],
    assignable_users: [],
    permissions: { can_manage_roster: true, can_mark_ready: true },
    readiness: {
        has_exercise_snapshot: true,
        has_playbook: true,
        has_facilitator: true,
        has_teams: true,
        participants_have_valid_team: true,
        responsibility_coverage_complete: true,
        uses_structured_responsibility_ownership: true,
        has_injects: true,
        all_injects_pending: true,
        uncovered_relevant_phase_keys: [],
    },
};
const main = `
import { createApp, h, reactive, ref, onMounted } from 'vue';
import '@/../css/app.css';
import Prepare from '@/Pages/Tenant/Ttx/Sessions/Prepare.vue';
import Participant from '@/Pages/Tenant/Ttx/Sessions/ParticipantWorkspace.vue';
import Facilitator from '@/Pages/Tenant/Ttx/Sessions/FacilitatorConsole.vue';
import Editor from '@/Pages/Tenant/Ttx/Sessions/Partials/ResponseEditor.vue';
window.route = (name) => '/api/' + name;
const surfaces = { prepare: Prepare, participant: Participant, facilitator: Facilitator, editor: Editor };
const props = reactive(window.__PROPS__); window.__props = props;
const app = createApp({ setup() { const target = ref(null); onMounted(() => { window.__editor = target.value; });
return () => h(surfaces[window.__SURFACE__], { ...props, ref: target, onSave: (value) => { window.__saved = value; } }); } });
app.config.globalProperties.route = window.route;
app.mount('#app');
`;
const inertia = `import { h } from 'vue'; export const Head = () => null; export const Link = { setup(_, { attrs, slots }) { return () => h('a', attrs, slots.default?.()); } };`;
const layout = `import { h } from 'vue'; export default { setup(_, { slots }) { return () => h('div', { style: 'padding:24px;max-width:1200px;margin:auto;min-width:0' }, slots.default?.()); } };`;
const toast = `export const useToast = () => ({ success() {}, error() {} });`;
const server = await createServer({
    configFile: false,
    root,
    resolve: {
        alias: [
            { find: "@inertiajs/vue3", replacement: "virtual:ttx-inertia" },
            {
                find: "@/Layouts/AppLayout.vue",
                replacement: "virtual:ttx-layout",
            },
            {
                find: "@/Composables/useToast",
                replacement: "virtual:ttx-toast",
            },
            { find: "@", replacement: resolve(root, "resources/js") },
        ],
    },
    optimizeDeps: {
        exclude: [
            "virtual:ttx-inertia",
            "virtual:ttx-layout",
            "virtual:ttx-toast",
        ],
    },
    plugins: [
        {
            name: "ttx-isolated-fixtures",
            resolveId(id) {
                if (id === "virtual:ttx-final-b") return "\0ttx-main";
                if (id === "virtual:ttx-inertia") return "\0ttx-inertia";
                if (id === "virtual:ttx-layout") return "\0ttx-layout";
                if (id === "virtual:ttx-toast") return "\0ttx-toast";
                if (id === "@inertiajs/vue3") return "\0ttx-inertia";
                if (id.endsWith("/Layouts/AppLayout.vue"))
                    return "\0ttx-layout";
                if (id.endsWith("/Composables/useToast")) return "\0ttx-toast";
            },
            load(id) {
                return {
                    "\0ttx-main": main,
                    "\0ttx-inertia": inertia,
                    "\0ttx-layout": layout,
                    "\0ttx-toast": toast,
                }[id];
            },
            configureServer(vite) {
                vite.middlewares.use("/__ttx_ui", (_req, res) => {
                    res.setHeader("Content-Type", "text/html");
                    res.end(
                        '<!doctype html><html lang="id"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div id="app"></div><script type="module" src="/@id/virtual:ttx-final-b"></script></body></html>',
                    );
                });
            },
        },
        vue(),
    ],
    server: { host: "127.0.0.1", port: 5187, strictPort: true },
});
await server.listen();
const browser = await chromium.launch();
let checks = 0;
async function load(
    surface,
    props,
    payload = initial,
    width = 1440,
    theme = "light",
) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    page.setDefaultTimeout(10000);
    const pageErrors = [];
    page.on("pageerror", (error) => pageErrors.push(error.message));
    await page.addInitScript(
        ({ surface, props, theme }) => {
            window.__SURFACE__ = surface;
            window.__PROPS__ = props;
            document.addEventListener("DOMContentLoaded", () =>
                document.documentElement.setAttribute("data-theme", theme),
            );
        },
        { surface, props, theme },
    );
    await page.route("**/api/**", async (route) =>
        route.fulfill({ json: payload }),
    );
    await page.goto("http://127.0.0.1:5187/__ttx_ui");
    try {
        await page
            .locator(
                surface === "editor"
                    ? ".response-editor"
                    : surface === "prepare"
                      ? ".prepare-page"
                      : surface === "participant"
                        ? ".exercise-room"
                        : ".console-header",
            )
            .waitFor({ timeout: 15000 });
    } catch (error) {
        throw new Error(
            `${surface}: ${pageErrors.join("; ")}; ${await page.locator("body").innerText()}`,
            { cause: error },
        );
    }
    assert.deepEqual(pageErrors, [], "Vue runtime errors");
    return page;
}
async function noOverflow(page) {
    const result = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        client: document.documentElement.clientWidth,
    }));
    assert.ok(result.scroll <= result.client + 1, JSON.stringify(result));
    checks++;
}
try {
    for (const width of [390, 768, 1440])
        for (const theme of ["light", "dark"]) {
            for (const surface of ["prepare", "participant", "facilitator"]) {
                const payload =
                    surface === "facilitator"
                        ? {
                              ...initial,
                              teams,
                              playbook: preparation.playbook,
                              injects: [
                                  {
                                      ...initial.injects[0],
                                      response_summary: {
                                          responded: 0,
                                          total: 2,
                                      },
                                      team_responses: teams.map((team) => ({
                                          team,
                                          response: null,
                                      })),
                                  },
                              ],
                          }
                        : initial;
                const page = await load(
                    surface,
                    surface === "prepare" ? preparation : { sessionId: 1 },
                    payload,
                    width,
                    theme,
                );
                await noOverflow(page);
                if (surface === "facilitator") {
                    assert.equal(await page.locator("textarea").count(), 0);
                    await page
                        .getByRole("button", {
                            name: /Lanjut ke Inject Berikutnya|Selesaikan Exercise & Buka Debrief/,
                        })
                        .click();
                    await page.locator(".missing-teams").waitFor();
                    assert.match(
                        await page.locator(".missing-teams").innerText(),
                        /Security \/ SOC/,
                    );
                    assert.match(
                        await page.locator(".missing-teams").innerText(),
                        /Operations/,
                    );
                    checks++;
                }
                await page.close();
            }
        }
    const briefing = await load(
        "participant",
        { sessionId: 1 },
        {
            ...initial,
            status: "ready",
            injects: [],
            progress: { current: null, total: 0 },
        },
    );
    await briefing.getByText("Exercise Briefing", { exact: true }).waitFor();
    assert.equal(await briefing.locator("textarea").count(), 0);
    assert.doesNotMatch(
        await briefing.locator("body").innerText(),
        /FACILITATOR GUIDANCE|Suspicious authentication|Operations/,
    );
    await briefing.getByRole("button", { name: "Enter Exercise" }).click();
    await briefing.getByText("Briefing selesai").waitFor();
    checks++;
    await briefing.close();

    const editor = await load(
        "editor",
        {
            injectStatus: "active",
            injectId: 10,
            contractVersion: 2,
            teamName: "Security / SOC",
        },
        initial,
        390,
    );
    const labels = [
        "Keputusan Tim",
        "Alasan Keputusan",
        "Tindakan Segera",
        "Koordinasi / Handoff",
    ];
    assert.equal(await editor.locator("textarea:visible").count(), 4);
    assert.equal(
        await editor
            .getByRole("button", { name: "Kirim Keputusan Tim" })
            .isDisabled(),
        true,
    );
    for (const label of labels)
        await editor
            .getByLabel(label, { exact: true })
            .fill(`${label} isi keputusan`);
    await editor.getByRole("button", { name: "Kirim Keputusan Tim" }).click();
    assert.match(
        await editor.evaluate(() => window.__saved.draft.coordination_handoff),
        /isi keputusan/,
    );
    await editor.evaluate(() =>
        window.__editor.setConflictError(
            "Keputusan tim telah diperbarui oleh anggota lain.",
        ),
    );
    assert.match(
        await editor.getByLabel("Keputusan Tim", { exact: true }).inputValue(),
        /isi keputusan/,
    );
    await editor
        .getByRole("button", { name: "Muat Versi Tim Terbaru" })
        .waitFor();
    checks++;
    await editor.evaluate(() =>
        window.__editor.setValidationError({
            coordination_handoff: ["Handoff wajib diisi."],
        }),
    );
    assert.equal(
        await editor
            .getByLabel("Koordinasi / Handoff")
            .getAttribute("aria-invalid"),
        "true",
    );
    assert.ok(
        await editor
            .getByLabel("Koordinasi / Handoff")
            .getAttribute("aria-describedby"),
    );
    checks++;
    await editor.evaluate(() => {
        window.__props.response = {
            id: 1,
            revision: 1,
            decision: "Submitted decision",
            rationale: "Rationale",
            immediate_actions: "Actions",
            coordination_handoff: "Handoff",
            notes: "Optional notes",
            last_edited_by_name: "Rekan SOC",
            last_edited_at: "2026-09-28T02:42:00Z",
        };
    });
    await editor.getByText("KEPUTUSAN DIKIRIM", { exact: true }).waitFor();
    assert.match(
        await editor.locator("body").innerText(),
        /Rekan SOC|Optional notes/,
    );
    await editor.getByRole("button", { name: "Edit Keputusan Tim" }).click();
    await editor.getByLabel("Keputusan Tim", { exact: true }).waitFor();
    checks++;
    await editor.close();
    const legacy = await load(
        "editor",
        { injectStatus: "active", injectId: 10, contractVersion: 1 },
        initial,
        390,
    );
    assert.equal(await legacy.getByLabel("Koordinasi / Handoff").count(), 0);
    await legacy
        .getByLabel("Keputusan Tim", { exact: true })
        .fill("Legacy decision");
    assert.equal(
        await legacy
            .getByRole("button", { name: "Kirim Keputusan Tim" })
            .isEnabled(),
        true,
    );
    await legacy
        .getByLabel("Penanggung Jawab", { exact: true })
        .fill("Legacy owner");
    checks++;
    await legacy.close();
    const noResponse = await load(
        "editor",
        { injectStatus: "locked", injectId: 10, contractVersion: 1 },
        initial,
        390,
    );
    assert.equal(await noResponse.locator("textarea").count(), 0);
    assert.match(
        await noResponse.locator("body").innerText(),
        /Tim tidak mengirim keputusan/,
    );
    checks++;
    await noResponse.close();
    console.log(
        `FINAL-B isolated Chromium UI: ${checks} checks passed (390/768/1440, light/dark, briefing, V2/V1, conflict, 422, submitted state, facilitator read-only).`,
    );
} finally {
    await browser.close();
    await server.close();
}
