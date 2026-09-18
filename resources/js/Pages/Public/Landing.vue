<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const theme = ref('dark');
const activeJourney = ref(0);
const activeMode = ref(0);
const revealedSections = ref(new Set());
const navOpen = ref(false);
const journeyTabs = ref([]);
const modeTabs = ref([]);
let observer;

const isAuthenticated = computed(() => !!page.props.auth?.user);
const ctaText = computed(() => isAuthenticated.value ? 'Masuk Dashboard' : 'Masuk ke Platform');
const ctaHref = computed(() => isAuthenticated.value ? '/dashboard' : '/login');

const journey = [
    { label: 'ASSESS', title: 'Mulai dari baseline yang jujur', text: 'Pretest memetakan pengetahuan awal tanpa asumsi.', metric: 'Pretest', value: '54', accent: 'brand' },
    { label: 'LEARN', title: 'Belajar dalam konteks nyata', text: 'Modul singkat membantu tim memahami keputusan yang tepat.', metric: 'Progress', value: '68%', accent: 'ok' },
    { label: 'PRACTICE', title: 'Uji respons sebelum insiden', text: 'Case study dan CTF mengubah teori menjadi kebiasaan.', metric: 'Scenario', value: '12', accent: 'warn' },
    { label: 'SIMULATE', title: 'Simulasikan tekanan sebenarnya', text: 'Phishing dan tabletop melatih koordinasi lintas peran.', metric: 'Response', value: '82%', accent: 'danger' },
    { label: 'MEASURE', title: 'Lihat kesiapan secara menyeluruh', text: 'Reporting menghubungkan aktivitas dengan learning gain.', metric: 'Readiness', value: '82', accent: 'brand' },
];

const learningModes = [
    { label: 'LEARN', title: 'Bangun pemahaman yang melekat', text: 'Modul mikro berbasis skenario membuat pembelajaran relevan dengan pekerjaan sehari-hari.', type: 'module', stat: '8 / 10', statLabel: 'modul selesai' },
    { label: 'DECIDE', title: 'Latih keputusan, bukan hafalan', text: 'Case study bercabang menunjukkan konsekuensi dari setiap pilihan.', type: 'decision', stat: 'B', statLabel: 'pilihan saat ini' },
    { label: 'PRACTICE', title: 'Berlatih di ruang yang aman', text: 'CTF dan tantangan keamanan memberi ruang untuk mencoba, gagal, lalu memperbaiki.', type: 'challenge', stat: '03:42', statLabel: 'waktu tersisa' },
    { label: 'RESPOND TOGETHER', title: 'Respons sebagai satu tim', text: 'Tabletop exercise menyatukan peran, komunikasi, dan keputusan ketika tekanan meningkat.', type: 'tabletop', stat: '4 / 4', statLabel: 'fase aktif' },
];

const currentJourney = computed(() => journey[activeJourney.value]);
const currentMode = computed(() => learningModes[activeMode.value]);

const scrollTo = (id) => {
    navOpen.value = false;
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && navOpen.value) {
        navOpen.value = false;
    }
};

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;
    localStorage.setItem('theme', theme.value);
};

const selectJourney = (index) => {
    activeJourney.value = index;
    nextTick(() => journeyTabs.value[index]?.focus());
};

const selectMode = (index) => {
    activeMode.value = index;
    nextTick(() => modeTabs.value[index]?.focus());
};

const moveJourney = (event, direction) => {
    event.preventDefault();
    selectJourney((activeJourney.value + direction + journey.length) % journey.length);
};

const moveMode = (event, direction) => {
    event.preventDefault();
    selectMode((activeMode.value + direction + learningModes.length) % learningModes.length);
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    const saved = localStorage.getItem('theme') || 'dark';
    theme.value = saved;
    document.documentElement.dataset.theme = saved;

    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                revealedSections.value = new Set([...revealedSections.value, entry.target.id]);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('[data-reveal]').forEach((section) => observer.observe(section));
});

onUnmounted(() => {
    observer?.disconnect();
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Head title="Awareness — Cybersecurity Awareness & Readiness Platform" />

    <div class="landing-shell">
        <nav class="site-nav" aria-label="Navigasi utama">
            <div class="nav-inner">
                <button class="brand-mark" aria-label="Kembali ke beranda" @click="scrollTo('top')">
                    <span class="brand-symbol">A</span>
                    <span class="brand-name">Awareness<span>.</span></span>
                </button>
                <div class="desktop-nav">
                    <button @click="scrollTo('journey')">Perjalanan</button>
                    <button @click="scrollTo('features')">Platform</button>
                    <button @click="scrollTo('security')">Keamanan</button>
                </div>
                <div class="nav-actions">
                    <button class="theme-toggle" :aria-label="`Ganti ke tema ${theme === 'dark' ? 'terang' : 'gelap'}`" @click="toggleTheme">
                        <svg v-if="theme === 'dark'" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.41M7.05 16.95l-1.41 1.41M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                        <svg v-else aria-hidden="true" viewBox="0 0 24 24"><path d="M20.35 15.35A9 9 0 0 1 8.65 3.65 9 9 0 1 0 20.35 15.35Z"/></svg>
                    </button>
                    <Link :href="ctaHref" class="nav-cta">{{ ctaText }}</Link>
                    <button class="menu-toggle" :aria-expanded="navOpen" aria-label="Buka navigasi" @click="navOpen = !navOpen">
                        <span></span><span></span>
                    </button>
                </div>
            </div>
            <div v-if="navOpen" class="mobile-nav">
                <button @click="scrollTo('journey')">Perjalanan</button>
                <button @click="scrollTo('features')">Platform</button>
                <button @click="scrollTo('security')">Keamanan</button>
            </div>
        </nav>

        <main id="top">
            <section class="hero-section section-wrap">
                <div class="hero-copy fade-in">
                    <p class="eyebrow"><span class="eyebrow-dot"></span> Cybersecurity awareness &amp; readiness</p>
                    <h1>Bangun kesiapan keamanan, <em>bukan sekadar</em> menyelesaikan pelatihan.</h1>
                    <p class="hero-lede">Awareness membantu organisasi mengubah pengetahuan menjadi keputusan yang lebih aman — lalu mengukurnya dengan jelas.</p>
                    <div class="hero-actions">
                        <Link :href="ctaHref" class="primary-action">{{ ctaText }} <span>↗</span></Link>
                        <button class="text-action" @click="scrollTo('journey')">Jelajahi platform <span>↓</span></button>
                    </div>
                    <div class="hero-note">Untuk tim yang ingin siap sebelum insiden terjadi</div>
                </div>
                <div class="hero-visual" aria-label="Pratinjau dashboard readiness" role="img">
                    <div class="hero-grid"></div>
                    <div class="orbit-line"></div>
                    <div class="readiness-surface">
                        <div class="surface-top"><span>Organization readiness</span><span class="live-status">Live view</span></div>
                        <div class="readiness-score"><strong>82</strong><span>/ 100<br><small>readiness index</small></span></div>
                        <div class="score-line"><span style="width: 82%"></span></div>
                        <div class="surface-meta"><span>Learning gain <b>+28</b></span><span>Completion <b>76%</b></span></div>
                        <div class="mini-bars"><i style="height: 38%"></i><i style="height: 52%"></i><i style="height: 45%"></i><i style="height: 68%"></i><i style="height: 58%"></i><i style="height: 82%"></i><i style="height: 74%"></i><i style="height: 92%"></i></div>
                    </div>
                    <div class="float-gain"><span>Learning gain</span><strong>+28</strong><small>Best posttest</small></div>
                    <div class="float-training"><span class="float-icon">✓</span><span><b>Phishing simulation</b><small>Completed by 92% of team</small></span></div>
                </div>
            </section>

            <section id="problem" class="editorial-section section-wrap reveal-section" :class="{ visible: revealedSections.has('problem') }" data-reveal>
                <div class="editorial-heading"><p class="eyebrow">Mengapa readiness penting</p><h2>Completion <em>bukan</em> ukuran kesiapan.</h2></div>
                <div class="editorial-intro"><p>Pelatihan selesai adalah awal, bukan akhir. Kesiapan terlihat ketika orang tahu apa yang harus dilakukan, bisa mempraktikkannya, dan organisasi dapat melihat kemajuannya.</p></div>
                <div class="principles">
                    <div class="principle"><span class="principle-number">01</span><div><h3>TRAINING</h3><p>Bangun pengetahuan yang relevan dengan pekerjaan sehari-hari.</p></div></div>
                    <div class="principle"><span class="principle-number">02</span><div><h3>PRACTICE</h3><p>Uji keputusan dan respons dalam skenario yang aman.</p></div></div>
                    <div class="principle"><span class="principle-number">03</span><div><h3>VISIBILITY</h3><p>Ukur learning gain dan lihat area yang masih perlu diperkuat.</p></div></div>
                </div>
            </section>

            <section id="journey" class="journey-section reveal-section" :class="{ visible: revealedSections.has('journey') }" data-reveal>
                <div class="section-wrap">
                    <div class="section-heading"><p class="eyebrow">Readiness journey</p><h2>Dari baseline menuju kesiapan.</h2><p>Setiap tahap terhubung. Setiap aktivitas punya tujuan yang dapat diukur.</p></div>
                    <div class="journey-track" role="tablist" aria-label="Tahap readiness journey">
                        <span class="journey-rail" aria-hidden="true"><i :style="{ width: `${(activeJourney / (journey.length - 1)) * 100}%` }"></i></span>
                        <button v-for="(item, index) in journey" :key="item.label" ref="journeyTabs" class="journey-node" :class="{ active: activeJourney === index }" role="tab" :aria-selected="activeJourney === index" :aria-controls="`journey-panel-${index}`" :tabindex="activeJourney === index ? 0 : -1" @click="selectJourney(index)" @keydown.left="moveJourney($event, -1)" @keydown.right="moveJourney($event, 1)" @keydown.home.prevent="selectJourney(0)" @keydown.end.prevent="selectJourney(journey.length - 1)">
                            <span class="node-dot">{{ String(index + 1).padStart(2, '0') }}</span><span>{{ item.label }}</span>
                        </button>
                    </div>
                    <div class="journey-panel" :id="`journey-panel-${activeJourney}`" role="tabpanel" :aria-label="currentJourney.label">
                        <div class="panel-copy"><p class="eyebrow">{{ currentJourney.metric }}</p><h3>{{ currentJourney.title }}</h3><p>{{ currentJourney.text }}</p></div>
                        <div class="journey-preview"><span class="preview-label">{{ currentJourney.label }}</span><strong :class="`accent-${currentJourney.accent}`">{{ currentJourney.value }}</strong><div class="preview-lines"><i></i><i></i><i></i><i></i></div><span class="preview-foot">Contoh tampilan produk</span></div>
                    </div>
                </div>
            </section>

            <section id="features" class="stories-section section-wrap reveal-section" :class="{ visible: revealedSections.has('features') }" data-reveal>
                <div class="section-heading narrow"><p class="eyebrow">Platform</p><h2>Lebih dari konten. Sebuah sistem latihan.</h2></div>
                <article class="feature-story"><div class="story-copy"><span class="story-index">01 / MEASURE LEARNING</span><h3>Assessment yang menunjukkan <em>perubahan</em>.</h3><p>Bandingkan pretest baseline dengan best posttest untuk memahami learning gain secara nyata — bukan sekadar angka completion.</p><button class="inline-link" @click="scrollTo('measurement')">Lihat cara mengukur <span>→</span></button></div><div class="assessment-visual"><div class="assessment-header"><span>LEARNING GAIN</span><b>+28</b></div><div class="compare-row"><div><small>PRETEST</small><strong>54</strong></div><span class="compare-arrow">→</span><div><small>BEST POSTTEST</small><strong>82</strong></div></div><div class="compare-track"><i></i></div><small class="visual-caption">best posttest score - pretest score</small></div></article>
                <article class="feature-story story-reverse"><div class="story-copy"><span class="story-index">02 / PRACTICE RESPONSE</span><h3>Latihan yang terasa seperti <em>keputusan nyata</em>.</h3><p>Phishing simulation, case study, CTF, dan tabletop exercise membuat respons menjadi kebiasaan yang bisa dilatih bersama.</p><button class="inline-link" @click="selectMode(2); scrollTo('modes')">Masuk ke ruang latihan <span>→</span></button></div><div class="simulation-visual"><div class="mail-row"><span class="mail-avatar">IT</span><span><b>Urgent: Review shared document</b><small>external-sender.example</small></span><em>Suspicious</em></div><div class="mail-divider"></div><div class="decision-row"><span>What would you do?</span><button>Report</button><button class="muted-choice">Open</button></div></div></article>
                <article class="feature-story"><div class="story-copy"><span class="story-index">03 / TEAM READINESS</span><h3>Respons bersama, bukan silo.</h3><p>Satukan peran dan percakapan lintas fungsi dalam tabletop exercise yang terstruktur dan mudah dievaluasi.</p><button class="inline-link" @click="selectMode(3); scrollTo('modes')">Lihat mode respons <span>→</span></button></div><div class="team-visual"><div class="team-line"><span>DETECT</span><i></i><span>DECIDE</span><i></i><span>RESPOND</span></div><div class="team-roles"><span>Security</span><span>People</span><span>Leadership</span></div></div></article>
                <div class="capability-rail"><span>Termasuk</span><b>Multi-tenant administration</b><b>Reporting & visibility</b><b>Private media</b><b>Audit logging</b></div>
            </section>

            <section id="modes" class="modes-section reveal-section" :class="{ visible: revealedSections.has('modes') }" data-reveal>
                <div class="section-wrap"><div class="section-heading"><p class="eyebrow">Learning modes</p><h2>Satu platform, empat cara untuk siap.</h2><p>Pilih mode yang sesuai dengan kebutuhan tim Anda.</p></div>
                    <div class="mode-tabs" role="tablist" aria-label="Mode pembelajaran"><button v-for="(mode, index) in learningModes" :key="mode.label" ref="modeTabs" role="tab" :aria-selected="activeMode === index" :aria-controls="`mode-panel-${index}`" :tabindex="activeMode === index ? 0 : -1" :class="{ active: activeMode === index }" @click="selectMode(index)" @keydown.left="moveMode($event, -1)" @keydown.right="moveMode($event, 1)" @keydown.home.prevent="selectMode(0)" @keydown.end.prevent="selectMode(learningModes.length - 1)">{{ mode.label }}</button></div>
                    <div class="mode-panel" :id="`mode-panel-${activeMode}`" role="tabpanel" :aria-label="currentMode.label"><div class="mode-copy"><span class="mode-count">0{{ activeMode + 1 }}</span><h3>{{ currentMode.title }}</h3><p>{{ currentMode.text }}</p></div><div class="mode-preview" :class="`mode-${currentMode.type}`"><div class="preview-window-top"><span>Awareness / {{ currentMode.label.toLowerCase() }}</span><span>•••</span></div><div v-if="currentMode.type === 'module'" class="module-view"><div class="module-progress"><i style="width: 80%"></i></div><span>Module 08 / Secure collaboration</span><h4>Recognize the signal.</h4><div class="module-options"><b>○</b> Verify the sender <b>○</b> Share immediately</div></div><div v-else-if="currentMode.type === 'decision'" class="decision-view"><span class="scenario-tag">CASE STUDY / 03</span><h4>Dokumen sensitif dikirim ke alamat eksternal.</h4><p>Langkah pertama yang paling tepat?</p><div class="decision-options"><span>A</span> Laporkan dan verifikasi melalui kanal resmi.<br><span>B</span> Balas untuk meminta konfirmasi.</div></div><div v-else-if="currentMode.type === 'challenge'" class="challenge-view"><span class="scenario-tag">CTF / NETWORK</span><h4>Find the signal in the noise_</h4><div class="code-lines">01  scan --target gateway<br>02  found: <b>03</b> open ports<br>03  _</div></div><div v-else class="tabletop-view"><span class="scenario-tag">TABLETOP / PHASE 04</span><h4>Containment decision</h4><div class="people-row"><span>SEC</span><span>HR</span><span>OPS</span><span>LEAD</span></div><p>Siapa yang mengomunikasikan langkah berikutnya?</p></div><div class="mode-stat"><strong>{{ currentMode.stat }}</strong><span>{{ currentMode.statLabel }}</span></div></div></div>
                </div>
            </section>

            <section id="measurement" class="measurement-section reveal-section" :class="{ visible: revealedSections.has('measurement') }" data-reveal><div class="section-wrap"><div class="measurement-intro"><p class="eyebrow">Measurement</p><h2>Belajar adalah perubahan yang bisa dilihat.</h2><p>Angka di bawah adalah ilustrasi bagaimana platform menghubungkan aktivitas belajar dengan hasilnya.</p></div><div class="measurement-story"><div class="measure-point"><span>PRETEST BASELINE</span><strong>54</strong><small>starting point</small></div><div class="measure-bridge"><span>+28 learning gain</span><i></i><i></i><i></i><i></i><i></i></div><div class="measure-point highlight"><span>BEST POSTTEST</span><strong>82</strong><small>highest demonstrated score</small></div></div><div class="supporting-metrics"><span><b>76%</b> completion</span><span><b>68%</b> competency</span><span><b>Illustrative values</b> bukan statistik pelanggan</span></div></div></section>

            <section id="visibility" class="visibility-section section-wrap reveal-section" :class="{ visible: revealedSections.has('visibility') }" data-reveal><div class="visibility-copy"><p class="eyebrow">ORGANIZATION VISIBILITY</p><h2>Begini rasanya mengoperasikan platform.</h2><p>Dari satu pandangan, tim dapat memahami siapa yang sudah siap, latihan apa yang berjalan, dan area mana yang membutuhkan perhatian.</p><button class="inline-link" @click="scrollTo('security')">Pelajari fondasinya <span>→</span></button></div><div class="dashboard-canvas"><div class="canvas-sidebar"><strong>A</strong><i></i><i></i><i></i><i></i></div><div class="canvas-main"><div class="canvas-header"><span>Organization overview</span><small>Last synced just now</small></div><div class="canvas-score"><span>Readiness index</span><strong>82 <small>+12%</small></strong><div class="canvas-bar"><i></i></div></div><div class="canvas-columns"><div><small>LEARNER PROGRESS</small><b>76%</b><span class="tiny-bars"><i></i><i></i><i></i><i></i><i></i></span></div><div><small>ACTIVE EXERCISES</small><b>04</b><span class="exercise-dot"><i></i> 2 in progress</span></div><div><small>COMPETENCY</small><b>68%</b><span class="competency-line"></span></div></div></div></div></section>

            <section id="security" class="security-section reveal-section" :class="{ visible: revealedSections.has('security') }" data-reveal><div class="section-wrap"><div class="section-heading narrow"><p class="eyebrow">SECURITY BY DESIGN</p><h2>Kepercayaan dibangun dari <em>lapisan</em> yang jelas.</h2><p>Fondasi platform membantu menjaga batas akses dan data di setiap langkah.</p></div><div class="security-flow"><div><b>RBAC</b><span>Role-based access</span></div><i></i><div><b>TENANT ISOLATION</b><span>Data boundaries</span></div><i></i><div><b>POSTGRESQL RLS</b><span>Database boundary</span></div><i></i><div><b>SERVER AUTHORIZATION</b><span>Validated actions</span></div><i></i><div><b>AUDIT & SANITIZATION</b><span>Traceable content</span></div></div><div class="security-facts"><span>Private media authorization</span><span>Rich-content sanitization</span><span>Audit logging</span><span>Server-side validation</span></div></div></section>

            <section id="final-cta" class="final-cta"><div class="cta-orb"></div><div class="section-wrap"><p class="eyebrow">READY WHEN YOU ARE</p><h2>Bangun awareness yang dapat dipelajari, dipraktikkan, dan diukur.</h2><Link :href="ctaHref" class="primary-action light">Mulai perjalanan readiness <span>↗</span></Link></div></section>
        </main>
        <footer class="site-footer section-wrap"><span class="brand-name">Awareness<span>.</span></span><span>Cybersecurity Awareness & Readiness Platform</span><button @click="scrollTo('top')">Kembali ke atas ↑</button></footer>
    </div>
</template>

<style scoped>
:global(html) { scroll-behavior: smooth; }
:global(body) { overflow-x: hidden; }
.landing-shell { --landing-max: 1240px; color: var(--ink); background: var(--bg); overflow: hidden; }
.section-wrap { width: min(calc(100% - 48px), var(--landing-max)); margin: 0 auto; }
.site-nav { position: fixed; inset: 0 0 auto; z-index: 50; border-bottom: 1px solid color-mix(in srgb, var(--line) 75%, transparent); background: color-mix(in srgb, var(--bg) 88%, transparent); backdrop-filter: blur(18px); }
.nav-inner { width: min(calc(100% - 48px), var(--landing-max)); height: 76px; margin: auto; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
.brand-mark, .desktop-nav button, .theme-toggle, .menu-toggle, .mobile-nav button, .site-footer button { border: 0; background: none; color: inherit; cursor: pointer; }
.brand-mark { display: flex; align-items: center; gap: 10px; padding: 0; }
.brand-symbol { display: grid; place-items: center; width: 31px; height: 31px; border-radius: 9px; color: var(--white); background: linear-gradient(140deg, var(--brand-mid), var(--brand-strong)); font: 700 14px var(--font-sans); }
.brand-name { font: 700 18px var(--font-sans); letter-spacing: -.04em; }
.brand-name span { color: var(--brand-mid); }
.desktop-nav { display: flex; gap: 32px; margin-left: auto; }
.desktop-nav button, .mobile-nav button { color: var(--muted); font-size: 14px; transition: color .18s ease; }
.desktop-nav button:hover, .desktop-nav button:focus-visible, .mobile-nav button:hover, .mobile-nav button:focus-visible { color: var(--ink); }
.nav-actions { display: flex; align-items: center; gap: 18px; }
.theme-toggle { display: grid; place-items: center; width: 34px; height: 34px; color: var(--muted); border-radius: 50%; transition: color .18s ease, background .18s ease; }
.theme-toggle:hover { color: var(--ink); background: var(--surface-2); }
.theme-toggle svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; }
.nav-cta { color: var(--white); background: var(--brand); border-radius: 7px; padding: 10px 16px; font-size: 14px; font-weight: 700; transition: transform .18s ease, background .18s ease; }
.nav-cta:hover { background: var(--brand-strong); transform: translateY(-1px); }
.menu-toggle { display: none; padding: 8px 0 8px 8px; }
.menu-toggle span { display: block; width: 22px; height: 1px; margin: 5px 0; background: var(--ink); }
.mobile-nav { display: none; }
.hero-section { min-height: 750px; padding-top: 170px; padding-bottom: 100px; display: grid; grid-template-columns: .9fr 1.1fr; align-items: center; gap: 70px; }
.eyebrow { display: flex; align-items: center; gap: 9px; margin: 0 0 22px; color: var(--brand-mid); font-size: 12px; font-weight: 800; letter-spacing: .16em; }
.eyebrow-dot, .pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--brand-mid); }
.hero-copy h1 { max-width: 670px; margin: 0; font: 700 clamp(3.2rem, 5.5vw, 5.8rem)/.98 var(--font-sans); letter-spacing: -.075em; }
.hero-copy h1 em, h2 em, h3 em { color: var(--brand-mid); font-style: normal; }
.hero-lede { max-width: 500px; margin: 27px 0 32px; color: var(--muted); font-size: 17px; line-height: 1.65; }
.hero-actions { display: flex; align-items: center; gap: 24px; }
.primary-action { display: inline-flex; align-items: center; gap: 17px; padding: 14px 19px; border-radius: 7px; color: var(--white); background: var(--brand); font-size: 14px; font-weight: 700; transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
.primary-action span { font-size: 18px; line-height: 0; }
.primary-action:hover { background: var(--brand-strong); box-shadow: 0 12px 30px color-mix(in srgb, var(--brand) 28%, transparent); transform: translateY(-2px); }
.text-action, .inline-link { border: 0; padding: 0; color: var(--muted); background: none; cursor: pointer; font-size: 14px; transition: color .18s ease; }
.text-action:hover, .inline-link:hover { color: var(--ink); }
.text-action span, .inline-link span { margin-left: 8px; color: var(--brand-mid); }
.hero-note { display: flex; align-items: center; gap: 8px; margin-top: 48px; color: var(--muted); font-size: 14px; }
.pulse-dot { background: var(--ok); box-shadow: 0 0 0 5px color-mix(in srgb, var(--ok) 12%, transparent); }
.hero-visual { position: relative; min-height: 490px; }
.hero-grid { position: absolute; inset: 4% -10% 7% 5%; opacity: .4; background-image: linear-gradient(color-mix(in srgb, var(--brand) 12%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--brand) 12%, transparent) 1px, transparent 1px); background-size: 44px 44px; mask-image: radial-gradient(ellipse, #000 20%, transparent 72%); }
.orbit-line { position: absolute; top: 8%; right: 3%; width: 72%; height: 75%; border: 1px solid color-mix(in srgb, var(--brand-mid) 32%, transparent); border-left-color: transparent; border-bottom-color: transparent; border-radius: 50%; transform: rotate(23deg); }
.readiness-surface { position: absolute; inset: 15% 7% 13% 10%; padding: 26px 30px; background: color-mix(in srgb, var(--surface) 93%, transparent); border: 1px solid var(--line); box-shadow: 20px 24px 70px rgba(0,0,0,.22); transform: rotate(-2deg); }
.surface-top, .surface-meta, .assessment-header { display: flex; align-items: center; justify-content: space-between; color: var(--muted); font-size: 12px; letter-spacing: .08em; }
.live-status { color: var(--ok); font-size: 12px; }.live-status i { display: inline-block; width: 5px; height: 5px; margin-right: 5px; border-radius: 50%; background: var(--ok); }
.readiness-score { display: flex; align-items: baseline; gap: 10px; margin: 42px 0 20px; }.readiness-score strong { font-size: clamp(5rem, 10vw, 8rem); line-height: .8; letter-spacing: -.1em; }.readiness-score span { color: var(--muted); font-size: 18px; }.readiness-score small { font-size: 12px; }
.score-line, .compare-track, .canvas-bar { height: 5px; overflow: hidden; background: var(--surface-2); }.score-line span, .compare-track i, .canvas-bar i { display: block; height: 100%; background: linear-gradient(90deg, var(--brand), var(--brand-mid)); }
.surface-meta { margin-top: 18px; font-size: 13px; }.surface-meta b { margin-left: 8px; color: var(--ink); }.mini-bars { display: flex; align-items: end; gap: 8px; height: 90px; margin-top: 36px; border-bottom: 1px solid var(--line); }.mini-bars i { flex: 1; display: block; background: linear-gradient(180deg, var(--brand-mid), color-mix(in srgb, var(--brand) 35%, transparent)); opacity: .7; }
.float-gain, .float-training { position: absolute; padding: 16px 18px; background: var(--surface); border: 1px solid var(--line); box-shadow: 10px 14px 35px rgba(0,0,0,.2); }.float-gain { top: 9%; right: -1%; transform: rotate(4deg); }.float-gain span, .float-training small { display: block; color: var(--muted); font-size: 12px; letter-spacing: .08em; }.float-gain strong { display: block; margin: 5px 0 2px; color: var(--ok); font-size: 28px; letter-spacing: -.06em; }.float-gain small { color: var(--muted); font-size: 13px; }.float-training { bottom: 4%; left: 0; display: flex; align-items: center; gap: 10px; transform: rotate(-3deg); }.float-training b { display: block; margin-bottom: 4px; font-size: 14px; }.float-icon { display: grid; place-items: center; width: 27px; height: 27px; color: var(--ok); background: var(--ok-bg); border-radius: 50%; }
.editorial-section { padding: 120px 0 112px; display: grid; grid-template-columns: 1.25fr .75fr; gap: 80px 12%; }.editorial-heading h2, .section-heading h2, .measurement-intro h2, .visibility-copy h2, .final-cta h2 { max-width: 700px; margin: 0; font-size: clamp(2.3rem, 5vw, 4.7rem); line-height: 1.02; letter-spacing: -.07em; }.editorial-intro { padding-top: 42px; }.editorial-intro p, .section-heading > p:not(.eyebrow), .visibility-copy > p, .measurement-intro > p:not(.eyebrow) { max-width: 440px; margin: 0; color: var(--muted); line-height: 1.75; font-size: 15px; }.principles { grid-column: 1 / -1; margin-top: 20px; border-top: 1px solid var(--line); }.principle { display: grid; grid-template-columns: 110px 1fr; gap: 25px; max-width: 870px; padding: 28px 0; border-bottom: 1px solid var(--line); }.principle-number, .story-index { color: var(--brand-mid); font: 700 11px var(--font-mono); letter-spacing: .12em; }.principle h3 { margin: 0 0 8px; font-size: 16px; letter-spacing: .1em; }.principle p { margin: 0; color: var(--muted); font-size: 16px; }
.journey-section { padding: 104px 0 120px; background: var(--surface-2); }.section-heading { margin-bottom: 55px; }.section-heading h2 { max-width: 660px; font-size: clamp(2.4rem, 4vw, 4rem); }.section-heading > p:last-child { margin-top: 20px; }.journey-track { position: relative; display: flex; justify-content: space-between; margin: 70px 0 55px; }.journey-rail { position: absolute; top: 17px; left: 2%; width: 96%; height: 1px; background: var(--line); }.journey-rail i { display: block; height: 100%; background: var(--brand-mid); transition: width .25s ease; }.journey-node { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: 13px; min-width: 80px; padding: 0; color: var(--muted); border: 0; background: none; cursor: pointer; font: 700 10px var(--font-sans); letter-spacing: .1em; transition: color .18s ease; }.journey-node:hover, .journey-node.active { color: var(--ink); }.node-dot { display: grid; place-items: center; width: 35px; height: 35px; border: 1px solid var(--line); border-radius: 50%; background: var(--surface-2); font-size: 15px; transition: background .2s, border .2s, color .2s; }.journey-node.active .node-dot { border-color: var(--brand-mid); color: var(--white); background: var(--brand); }.journey-panel { display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 70px; padding-top: 42px; border-top: 1px solid var(--line); }.panel-copy .eyebrow { margin-bottom: 17px; }.panel-copy h3 { margin: 0 0 14px; font-size: 31px; letter-spacing: -.05em; }.panel-copy > p:last-child { max-width: 430px; margin: 0; color: var(--muted); line-height: 1.75; }.journey-preview { position: relative; min-height: 180px; padding: 26px 30px; background: var(--surface); border-left: 2px solid var(--brand); }.preview-label, .preview-foot { display: block; color: var(--muted); font-size: 15px; letter-spacing: .12em; }.journey-preview strong { display: block; margin: 20px 0 8px; font-size: 68px; letter-spacing: -.08em; }.accent-ok { color: var(--ok); }.accent-warn { color: var(--warn); }.accent-danger { color: var(--danger); }.preview-lines { display: flex; gap: 5px; height: 25px; align-items: end; }.preview-lines i { width: 28px; height: 100%; background: var(--brand-soft); }.preview-lines i:nth-child(2) { height: 65%; }.preview-lines i:nth-child(3) { height: 85%; }.preview-lines i:nth-child(4) { height: 42%; }.preview-foot { position: absolute; right: 30px; bottom: 28px; }
.stories-section { padding: 120px 0 104px; }.section-heading.narrow { max-width: 760px; }.feature-story { display: grid; grid-template-columns: .85fr 1.15fr; align-items: center; gap: 12%; min-height: 450px; padding: 72px 0; border-bottom: 1px solid var(--line); }.feature-story.story-reverse { grid-template-columns: 1.15fr .85fr; }.story-reverse .story-copy { grid-column: 2; grid-row: 1; }.story-reverse .simulation-visual { grid-column: 1; grid-row: 1; }.story-copy h3 { max-width: 460px; margin: 18px 0; font-size: clamp(2rem, 3.5vw, 3.35rem); line-height: 1.04; letter-spacing: -.065em; }.story-copy p { max-width: 410px; margin: 0 0 25px; color: var(--muted); line-height: 1.75; font-size: 16px; }.assessment-visual, .simulation-visual, .team-visual { min-height: 270px; padding: 30px; background: var(--surface-2); border-top: 1px solid var(--line); }.assessment-header { color: var(--muted); }.assessment-header b { color: var(--ok); font-size: 22px; }.compare-row { display: flex; align-items: end; justify-content: space-between; margin: 53px 0 25px; }.compare-row small { display: block; color: var(--muted); font-size: 15px; letter-spacing: .1em; }.compare-row strong { display: block; margin-top: 7px; font-size: 63px; line-height: .8; letter-spacing: -.08em; }.compare-arrow { color: var(--brand-mid); font-size: 30px; }.compare-track { margin-bottom: 12px; }.compare-track i { width: 74%; }.visual-caption { color: var(--muted); font: 12px var(--font-mono); }.mail-row { display: flex; align-items: center; gap: 12px; }.mail-avatar { display: grid; place-items: center; width: 32px; height: 32px; color: var(--brand-mid); background: var(--brand-soft); border-radius: 50%; font-size: 15px; }.mail-row b, .mail-row small { display: block; }.mail-row b { font-size: 15px; }.mail-row small { margin-top: 4px; color: var(--muted); font-size: 15px; }.mail-row em { margin-left: auto; padding: 5px 7px; color: var(--danger); background: var(--danger-bg); font-size: 15px; font-style: normal; }.mail-divider { margin: 25px 0; border-top: 1px solid var(--line); }.decision-row { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 16px; }.decision-row span { margin-right: auto; }.decision-row button { border: 0; padding: 8px 12px; border-radius: 5px; color: var(--white); background: var(--brand); font-size: 15px; cursor: pointer; }.decision-row .muted-choice { color: var(--muted); background: var(--surface); }.team-line { display: flex; align-items: center; color: var(--ink); font: 700 12px var(--font-sans); }.team-line i { flex: 1; height: 1px; margin: 0 14px; background: var(--brand-mid); }.team-roles { display: flex; justify-content: space-between; margin-top: 80px; color: var(--muted); font-size: 16px; }.capability-rail { display: flex; flex-wrap: wrap; align-items: center; gap: 18px 28px; padding: 28px 0; color: var(--muted); font-size: 16px; }.capability-rail span { color: var(--brand-mid); font: 700 12px var(--font-mono); letter-spacing: .12em; }.capability-rail b { font-weight: 500; }
.modes-section { padding: 112px 0 120px; background: var(--surface-2); }.mode-tabs { display: flex; gap: 24px; overflow-x: auto; margin-bottom: 45px; border-bottom: 1px solid var(--line); }.mode-tabs button { flex: 0 0 auto; position: relative; padding: 0 0 17px; border: 0; color: var(--muted); background: none; cursor: pointer; font: 700 12px var(--font-sans); letter-spacing: .1em; transition: color .18s ease; }.mode-tabs button::after { content: ''; position: absolute; right: 0; bottom: -1px; left: 0; height: 2px; background: transparent; }.mode-tabs button.active, .mode-tabs button:hover { color: var(--ink); }.mode-tabs button.active::after { background: var(--brand-mid); }.mode-panel { display: grid; grid-template-columns: .7fr 1.3fr; gap: 10%; align-items: center; }.mode-count { color: var(--brand-mid); font: 700 12px var(--font-mono); }.mode-copy h3 { max-width: 390px; margin: 22px 0 17px; font-size: clamp(1.9rem, 3.2vw, 2.8rem); line-height: 1.05; letter-spacing: -.06em; }.mode-copy > p { max-width: 360px; margin: 0; color: var(--muted); line-height: 1.75; font-size: 16px; }.mode-status { display: none; }.mode-status i { width: 5px; height: 5px; border-radius: 50%; background: var(--ok); }.mode-preview { position: relative; min-height: 355px; padding: 24px; background: var(--surface); border: 1px solid var(--line); box-shadow: 15px 20px 50px rgba(0,0,0,.12); }.preview-window-top { display: flex; justify-content: space-between; color: var(--muted); font: 12px var(--font-mono); }.module-view, .decision-view, .challenge-view, .tabletop-view { max-width: 560px; margin: 60px auto 0; }.module-progress { height: 3px; margin-bottom: 25px; background: var(--surface-2); }.module-progress i { display: block; height: 100%; background: var(--brand-mid); }.module-view > span, .scenario-tag { color: var(--brand-mid); font: 12px var(--font-mono); letter-spacing: .08em; }.module-view h4, .decision-view h4, .challenge-view h4, .tabletop-view h4 { margin: 18px 0; font-size: clamp(1.5rem, 2.6vw, 2rem); letter-spacing: -.05em; }.module-options, .decision-view p, .tabletop-view p { color: var(--muted); font-size: 15px; line-height: 2; }.module-options b { margin-right: 5px; color: var(--brand-mid); }.decision-options { padding: 15px; border-left: 1px solid var(--brand-mid); color: var(--muted); font-size: 15px; line-height: 2; }.decision-options span { color: var(--brand-mid); }.code-lines { padding: 17px; color: var(--muted); background: var(--surface-2); font: 12px/2 var(--font-mono); }.code-lines b { color: var(--ok); }.people-row { display: flex; gap: 10px; }.people-row span { padding: 8px 12px; color: var(--brand-mid); background: var(--brand-soft); font: 12px var(--font-mono); }.mode-stat { position: absolute; right: 24px; bottom: 24px; text-align: right; }.mode-stat strong { display: block; color: var(--brand-mid); font-size: 24px; }.mode-stat span { color: var(--muted); font-size: 15px; }
.measurement-section { padding: 120px 0 112px; color: var(--on-hero); background: var(--brand-dark-2); }.measurement-section .eyebrow { color: var(--on-hero-muted); }.measurement-intro { display: flex; justify-content: space-between; gap: 50px; }.measurement-intro h2 { max-width: 610px; }.measurement-intro > p:last-child { max-width: 260px; margin-top: 12px; color: var(--on-hero-muted); }.measurement-story { display: flex; align-items: center; gap: 8%; margin-top: 105px; }.measure-point span { display: block; color: var(--on-hero-muted); font: 12px var(--font-mono); letter-spacing: .1em; }.measure-point strong { display: block; margin: 17px 0 7px; color: var(--on-hero); font-size: clamp(5rem, 10vw, 8rem); line-height: .8; letter-spacing: -.1em; }.measure-point small { color: var(--on-hero-muted); font-size: 16px; }.measure-point.highlight strong { color: #d8c7ff; }.measure-bridge { flex: 1; position: relative; padding-top: 26px; }.measure-bridge span { position: absolute; top: 0; color: var(--brand-mid); font: 11px var(--font-mono); }.measure-bridge::before { content: ''; position: absolute; top: 48px; right: 0; left: 0; height: 1px; background: color-mix(in srgb, var(--brand-mid) 45%, transparent); }.measure-bridge i { display: inline-block; position: relative; z-index: 1; width: 6px; height: 6px; margin: 45px 9%; border-radius: 50%; background: var(--brand-mid); }.supporting-metrics { display: flex; gap: 40px; margin-top: 64px; padding-top: 24px; border-top: 1px solid color-mix(in srgb, var(--on-hero-muted) 25%, transparent); color: var(--on-hero-muted); font-size: 16px; }.supporting-metrics b { margin-right: 6px; color: var(--on-hero); font-size: 20px; }
.visibility-section { display: grid; grid-template-columns: .65fr 1.35fr; align-items: center; gap: 10%; padding-top: 120px; padding-bottom: 128px; }.visibility-copy h2 { font-size: clamp(2.4rem, 4vw, 4rem); }.visibility-copy > p { margin: 24px 0; }.dashboard-canvas { display: grid; grid-template-columns: 54px 1fr; min-height: 345px; background: var(--surface); border: 1px solid var(--line); box-shadow: 0 18px 50px rgba(0,0,0,.13); }.canvas-sidebar { display: flex; flex-direction: column; align-items: center; gap: 25px; padding: 24px 0; border-right: 1px solid var(--line); color: var(--brand-mid); }.canvas-sidebar i { width: 15px; height: 2px; background: var(--line); }.canvas-main { padding: 28px; }.canvas-header { display: flex; justify-content: space-between; padding-bottom: 25px; border-bottom: 1px solid var(--line); font-size: 15px; }.canvas-header small { color: var(--muted); font-size: 15px; }.canvas-score { padding: 28px 0; }.canvas-score span, .canvas-columns small { display: block; color: var(--muted); font: 9px var(--font-mono); letter-spacing: .1em; }.canvas-score strong { display: block; margin: 12px 0 17px; font-size: 52px; letter-spacing: -.08em; }.canvas-score strong small { margin-left: 10px; color: var(--ok); font: 11px var(--font-sans); letter-spacing: 0; }.canvas-bar { height: 4px; }.canvas-bar i { width: 82%; }.canvas-columns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding-top: 22px; border-top: 1px solid var(--line); }.canvas-columns b { display: block; margin: 11px 0; font-size: 24px; }.tiny-bars { display: flex; align-items: end; gap: 3px; height: 19px; }.tiny-bars i { flex: 1; height: 80%; background: var(--brand-soft); }.tiny-bars i:nth-child(2) { height: 55%; }.tiny-bars i:nth-child(3) { height: 70%; }.tiny-bars i:nth-child(4) { height: 95%; }.tiny-bars i:nth-child(5) { height: 75%; }.exercise-dot { color: var(--muted); font-size: 15px; }.exercise-dot i { display: inline-block; width: 5px; height: 5px; margin-right: 3px; border-radius: 50%; background: var(--warn); }.competency-line { display: block; width: 75%; height: 3px; background: var(--ok); }
.security-section { padding: 120px 0; background: var(--surface-2); }.security-flow { display: flex; align-items: center; margin-top: 75px; }.security-flow > div { flex: 1; }.security-flow b, .security-flow span { display: block; }.security-flow b { color: var(--ink); font-size: 16px; }.security-flow span { margin-top: 9px; color: var(--muted); font-size: 15px; line-height: 1.4; }.security-flow > i { width: 35px; height: 1px; background: var(--brand-mid); }.security-facts { display: flex; flex-wrap: wrap; gap: 10px 28px; margin-top: 55px; color: var(--muted); font-size: 16px; }.security-facts span::before { content: '↳'; margin-right: 8px; color: var(--brand-mid); }
.final-cta { position: relative; overflow: hidden; padding: 120px 0 128px; color: var(--white); background: linear-gradient(125deg, var(--brand-dark-1), var(--brand-strong)); }.final-cta .section-wrap { position: relative; z-index: 1; }.final-cta .eyebrow { color: #d8c7ff; }.final-cta h2 { max-width: 850px; margin-bottom: 38px; }.primary-action.light { color: var(--brand-strong); background: var(--white); }.primary-action.light:hover { background: #f2edff; }.cta-orb { position: absolute; top: -180px; right: 7%; width: 500px; height: 500px; border: 1px solid rgba(255,255,255,.18); border-radius: 50%; box-shadow: 0 0 0 80px rgba(255,255,255,.025), 0 0 0 160px rgba(255,255,255,.02); }.site-footer { display: flex; align-items: center; justify-content: space-between; gap: 25px; padding-top: 30px; padding-bottom: 30px; color: var(--muted); font-size: 15px; }.site-footer button { color: var(--muted); font-size: 15px; }.site-footer button:hover { color: var(--ink); }
.reveal-section { opacity: 0; transform: translateY(18px); transition: opacity .5s ease, transform .5s ease; }.reveal-section.visible { opacity: 1; transform: none; }
button:focus-visible, a:focus-visible { outline: 2px solid var(--brand-mid); outline-offset: 4px; }
@media (max-width: 900px) { .desktop-nav { display: none; }.menu-toggle { display: block; }.mobile-nav { display: flex; flex-direction: column; gap: 18px; padding: 20px 24px 24px; border-top: 1px solid var(--line); }.hero-section, .editorial-section, .visibility-section { grid-template-columns: 1fr; }.hero-section { padding-top: 135px; gap: 45px; }.hero-visual { min-height: 430px; }.editorial-intro { padding-top: 0; }.journey-panel, .mode-panel { grid-template-columns: 1fr; gap: 38px; }.feature-story, .feature-story.story-reverse { grid-template-columns: 1fr; gap: 40px; }.story-reverse .story-copy, .story-reverse .simulation-visual { grid-column: auto; grid-row: auto; }.story-reverse .story-copy { order: 1; }.story-reverse .simulation-visual { order: 2; }.measurement-intro { display: block; }.measurement-intro > p:last-child { margin-top: 25px; }.measurement-story { gap: 4%; }.security-flow { align-items: stretch; flex-direction: column; gap: 0; }.security-flow > div { padding: 16px 0; border-bottom: 1px solid var(--line); }.security-flow > i { width: 1px; height: 20px; margin-left: 10px; }.security-facts { margin-top: 32px; }.canvas-columns { gap: 8px; }.canvas-main { padding: 20px; } }
@media (max-width: 560px) { .section-wrap, .nav-inner { width: min(calc(100% - 36px), var(--landing-max)); }.nav-inner { height: 66px; }.brand-name { font-size: 16px; }.nav-cta { padding: 9px 11px; font-size: 14px; }.hero-section { min-height: auto; padding-top: 125px; padding-bottom: 85px; }.hero-copy h1 { font-size: clamp(2.85rem, 14vw, 4.2rem); }.hero-lede { font-size: 16px; line-height: 1.7; }.hero-actions { align-items: flex-start; flex-direction: column; gap: 16px; }.hero-note { margin-top: 32px; }.hero-visual { min-height: 330px; margin: 0 -8px; transform: scale(.92); transform-origin: top center; }.readiness-surface { inset: 12% 4% 10% 5%; padding: 19px; }.readiness-score { margin-top: 30px; }.readiness-score strong { font-size: 5rem; }.float-gain { right: -2%; }.float-training { left: -4%; }.editorial-section, .stories-section, .visibility-section { padding-top: 72px; padding-bottom: 80px; }.editorial-section { gap: 45px; }.editorial-heading h2, .section-heading h2, .measurement-intro h2, .visibility-copy h2, .final-cta h2 { font-size: 2.7rem; }.principle { grid-template-columns: 55px 1fr; gap: 15px; }.principle-number { font-size: 15px; }.journey-section, .modes-section, .security-section { padding: 72px 0 80px; }.journey-track { align-items: flex-start; flex-direction: column; gap: 16px; margin: 40px 0; }.journey-rail { top: 17px; bottom: 17px; left: 17px; width: 1px; height: auto; }.journey-rail i { width: 100% !important; height: 0; }.journey-node { flex-direction: row; gap: 12px; }.journey-node.active { color: var(--ink); }.journey-panel { padding-top: 30px; }.journey-preview { min-height: 155px; padding: 20px; }.journey-preview strong { font-size: 55px; }.feature-story { padding: 60px 0; }.assessment-visual, .simulation-visual, .team-visual { min-height: 235px; padding: 20px; }.compare-row { margin-top: 45px; }.compare-row strong { font-size: 47px; }.capability-rail { gap: 13px 20px; }.mode-tabs { gap: 20px; margin-bottom: 32px; }.mode-panel { gap: 32px; }.mode-copy h3 { font-size: clamp(1.5rem, 2.6vw, 2rem); }.mode-preview { min-height: 330px; padding: 17px; }.module-view, .decision-view, .challenge-view, .tabletop-view { margin-top: 45px; }.module-view h4, .decision-view h4, .challenge-view h4, .tabletop-view h4 { font-size: 24px; }.measurement-section { padding: 80px 0; }.measurement-story { align-items: flex-start; flex-direction: column; gap: 32px; margin-top: 56px; }.measure-bridge { width: 100%; padding: 24px 0; }.measure-bridge::before { top: 31px; }.measure-bridge i { margin: 28px 7%; }.supporting-metrics { align-items: flex-start; flex-direction: column; gap: 16px; margin-top: 40px; }.dashboard-canvas { grid-template-columns: 40px 1fr; min-height: 300px; }.canvas-sidebar { padding-top: 20px; gap: 21px; }.canvas-main { padding: 18px 14px; }.canvas-header { gap: 10px; }.canvas-columns { grid-template-columns: 1fr 1fr; }.canvas-columns > div:last-child { grid-column: span 2; }.final-cta { padding: 80px 0 105px; }.cta-orb { right: -55%; width: 400px; height: 400px; }.site-footer { align-items: flex-start; flex-direction: column; gap: 12px; padding-top: 25px; padding-bottom: 25px; } }
@media (prefers-reduced-motion: reduce) { :global(html) { scroll-behavior: auto; }.reveal-section { opacity: 1; transform: none; transition: none; }.primary-action:hover { transform: none; }.journey-rail i { transition: none; } }
</style>
