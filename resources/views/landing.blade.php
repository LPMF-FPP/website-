<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LPMF LIMS | The Pulse of Evidence</title>
    <meta name="description" content="Sistem Informasi Manajemen Laboratorium Forensik Pusdokkes Polri. Chain of Custody. Immutable Audit. Real-time Telemetry.">

    <!-- Typography: Cabinet Grotesk (Display), Satoshi (Body), JetBrains Mono (Tech) -->
    <link href="https://api.fontshare.com/v2/css?f[]=cabinet-grotesk@800,700,500&f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Palette: "The Glass Evidence" - Dark, Sharp, Amber Accents */
            --bg-deep: #050505;
            --bg-panel: #0a0a0a;
            --text-primary: #ffffff;
            --text-secondary: #888888;
            --text-muted: #9CA3AF;
            
            /* Accent: Forensic Amber */
            --accent: #FFB800; 
            --accent-glow: rgba(255, 184, 0, 0.15);
            --border-light: rgba(255, 255, 255, 0.1);
            --border-active: rgba(255, 255, 255, 0.3);

            /* Spacing & Layout */
            --container: 1400px;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* RESET & BASE */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 4px;
        }

        /* Skip to main content link */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .sr-only:focus {
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: auto;
            height: auto;
            padding: 1rem 2rem;
            margin: 0;
            overflow: visible;
            clip: auto;
            white-space: normal;
            background: var(--accent);
            color: var(--bg-deep);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            text-decoration: none;
            z-index: 10000;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        body {
            background-color: var(--bg-deep);
            color: var(--text-primary);
            font-family: 'Satoshi', sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            line-height: 1.5;
        }

        /* TYPOGRAPHY */
        h1, h2, h3, h4 {
            font-family: 'Cabinet Grotesk', sans-serif;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }

        h1 {
            font-size: clamp(3rem, 8vw, 8rem);
            color: var(--text-primary);
        }

        h2 {
            font-size: clamp(2rem, 5vw, 4rem);
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: -0.02em;
        }

        .text-accent { color: var(--accent); }
        .text-secondary { color: var(--text-secondary); }

        /* CUSTOM CURSOR */
        #cursor {
            position: fixed;
            top: 0;
            left: 0;
            width: 20px;
            height: 20px;
            border: 1px solid var(--accent);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 9999;
            mix-blend-mode: difference;
            transition: width 0.3s, height 0.3s;
        }
        
        /* The "Forensic Light" Effect */
        #flashlight {
            position: fixed;
            top: 0;
            left: 0;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, rgba(0,0,0,0) 60%);
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 9998;
        }

        /* GRID BACKGROUND */
        .grid-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 100px 100px;
            z-index: -1;
            mask-image: radial-gradient(circle at var(--x, 50%) var(--y, 50%), black 0%, transparent 40%);
            -webkit-mask-image: radial-gradient(circle at var(--x, 50%) var(--y, 50%), black 0%, transparent 40%);
        }

        /* COMPONENTS */
        .container {
            max-width: var(--container);
            margin: 0 auto;
            padding: 0 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 2.5rem;
            background: var(--text-primary);
            color: var(--bg-deep);
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            text-decoration: none;
            border: 1px solid var(--text-primary);
            transition: all 0.3s var(--ease-out);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--accent);
            transform: translateX(-100%);
            transition: transform 0.3s var(--ease-out);
            z-index: 0;
        }

        .btn span {
            position: relative;
            z-index: 1;
        }

        .btn:hover {
            border-color: var(--accent);
        }

        .btn:hover::before {
            transform: translateX(0);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-active);
        }
        .btn-outline:hover {
            background: var(--border-light);
            border-color: var(--text-primary);
        }

        /* HEADER */
        header {
            position: fixed;
            top: 0; left: 0; width: 100%;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            mix-blend-mode: difference;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-family: 'Cabinet Grotesk', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -0.01em;
        }

        /* HERO SECTION */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            padding-top: 100px;
        }

        .hero-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            max-width: 80%;
        }

        .reveal-wrap {
            overflow: hidden;
        }

        .reveal-text {
            transform: translateY(100%);
            opacity: 0;
            animation: slideUp 1s var(--ease-out) forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.4s; }

        @keyframes slideUp {
            to { transform: translateY(0); opacity: 1; }
        }

        .meta-tag {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--accent);
            border: 1px solid var(--accent);
            padding: 0.5rem 1rem;
            display: inline-block;
            margin-bottom: 1rem;
        }

        /* SECTIONS */
        section {
            padding: 10rem 0;
            border-top: 1px solid var(--border-light);
        }

        /* TELEMETRY HUD */
        .hud-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-bottom: 1px solid var(--border-light);
        }

        .hud-item {
            padding: 2rem;
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            transition: background 0.3s;
        }

        .hud-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .hud-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .hud-value {
            font-family: 'Cabinet Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
        }

        .blink {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; box-shadow: 0 0 0 0 rgba(255, 184, 0, 0.7); }
            70% { opacity: 0.5; box-shadow: 0 0 0 10px rgba(255, 184, 0, 0); }
            100% { opacity: 1; box-shadow: 0 0 0 0 rgba(255, 184, 0, 0); }
        }

        /* NARRATIVE */
        .narrative-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .scramble-text {
            font-family: 'JetBrains Mono', monospace;
            color: var(--text-secondary);
        }

        /* CHAT SIMULATOR (FIELD DEVICE STYLE) */
        .device-frame {
            border: 1px solid var(--border-active);
            background: #000;
            padding: 1rem;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            max-width: 400px;
            position: relative;
        }
        
        .device-frame::before {
            content: 'SECURE_CHANNEL_V2.0';
            position: absolute;
            top: -25px;
            left: 0;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .chat-line {
            margin-bottom: 1rem;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.5s ease;
        }

        .chat-line.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .chat-line.system { color: var(--accent); }
        .chat-line.user { color: var(--text-primary); text-align: right; }

        /* FEATURE CARDS */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border-light); /* Gap color */
            border: 1px solid var(--border-light);
        }

        .feature-card {
            background: var(--bg-deep);
            padding: 4rem 2rem;
            transition: background 0.5s;
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            background: var(--bg-panel);
        }

        .feature-card:hover h3 {
            color: var(--accent);
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 2rem;
            display: block;
        }

        /* FOOTER */
        footer {
            padding: 4rem 0;
            border-top: 1px solid var(--border-light);
            text-align: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* UTILS */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s var(--ease-out), transform 0.8s var(--ease-out);
        }
        .scroll-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            h1 { font-size: 3.5rem; }
            .narrative-grid, .hud-grid, .feature-grid { grid-template-columns: 1fr; }
            .hud-item { border-right: none; border-bottom: 1px solid var(--border-light); }
            .hero-content { max-width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            #cursor, #flashlight { display: none; }
            .scroll-reveal { transition: none; opacity: 1; transform: none; }
            .animate-pulse { animation: none; }
        }
    </style>
</head>
<body>
    <!-- Skip to main content for keyboard users -->
    <a href="#main-content" class="sr-only">
        Lewati ke konten utama
    </a>

    <div id="cursor"></div>
    <div id="flashlight"></div>
    <div class="grid-bg"></div>

    <header>
        <div class="logo" style="width: 100%; justify-content: center;">
            <img src="https://storage.pusdokkes.polri.go.id/pusdokkes/logo.png" alt="Logo Pusdokkes Polri" style="height: 40px;">
            <span>Farmapol Pusdokkes Polri</span>
        </div>
        <nav style="position: absolute; right: 2rem;">
            <a href="{{ route('login') }}" class="btn-outline" style="padding: 0.75rem 1.5rem; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;">
                SECURE LOGIN
            </a>
        </nav>
    </header>

    <main id="main-content">
        <!-- HERO -->
        <section class="hero container" style="border-top: none; justify-content: center; text-align: center;">
            <div class="hero-content" style="align-items: center; max-width: 100%;">
                <div class="reveal-wrap">
                    <div class="meta-tag reveal-text delay-1">
                        <span class="blink"></span> SYSTEM OPERATIONAL
                    </div>
                </div>
                <div class="reveal-wrap">
                    <h1 class="reveal-text delay-2">
                        The Pulse of <br>
                        <span style="color: transparent; -webkit-text-stroke: 1px var(--text-primary);">Evidence.</span>
                    </h1>
                </div>
                <div class="reveal-wrap">
                    <p class="reveal-text delay-3 text-secondary" style="font-size: 1.5rem; max-width: 700px; margin-top: 1rem; margin-left: auto; margin-right: auto;">
                        Sistem Manajemen Laboratorium Forensik dengan Chain of Custody yang tak terbantahkan. Presisi mutlak untuk Pusdokkes Polri.
                    </p>
                </div>
                <div class="reveal-wrap" style="margin-top: 2rem;">
                    <div class="reveal-text delay-3" style="display: flex; gap: 1rem;">
                        <a href="{{ route('login') }}" class="btn">
                            <span>AKSES SISTEM</span>
                        </a>
                        <a href="#capabilities" class="btn btn-outline">
                            <span>PELAJARI</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- LIVE TELEMETRY -->
        <div class="hud-grid" style="border-top: 1px solid var(--border-light);">
            <div class="hud-item">
                <span class="hud-label">System Status</span>
                <span class="hud-value text-accent">SECURE</span>
            </div>
            <div class="hud-item">
                <span class="hud-label">Active Samples</span>
                <span class="hud-value">10<span style="font-size: 1rem; color: var(--text-muted);">/17</span></span>
            </div>
            <div class="hud-item">
                <span class="hud-label">Avg. Turnaround</span>
                <span class="hud-value">2.4 <span style="font-size: 1rem;">Days</span></span>
            </div>
            <div class="hud-item">
                <span class="hud-label">Audit Logs</span>
                <span class="hud-value">142</span>
            </div>
        </div>

        <!-- NARRATIVE -->
        <section class="container narrative-grid scroll-reveal-trigger">
            <div class="scroll-reveal">
                <h2 style="margin-bottom: 2rem;">From Crime Scene<br>To Courtroom.</h2>
                <p class="text-secondary" style="font-size: 1.125rem; margin-bottom: 2rem; max-width: 400px;">
                    Dalam forensik, integritas data adalah segalanya. Kami menghilangkan ambiguitas dengan pencatatan digital tingkat militer.
                </p>
                <ul class="font-mono text-secondary" style="display: grid; gap: 1rem; font-size: 0.9rem;">
                    <li style="display: flex; gap: 1rem;">
                        <span class="text-accent">[01]</span> Chain of Custody Otomatis
                    </li>
                    <li style="display: flex; gap: 1rem;">
                        <span class="text-accent">[02]</span> Enkripsi Metadata Tersangka
                    </li>
                    <li style="display: flex; gap: 1rem;">
                        <span class="text-accent">[03]</span> Log Aktivitas Immutable
                    </li>
                </ul>
            </div>
            <div class="scroll-reveal" style="display: flex; justify-content: center;">
                <!-- Chat Simulator -->
                <div class="device-frame">
                    <div id="chat-stream">
                        <!-- JS injected -->
                    </div>
                    <div style="border-top: 1px solid #333; margin-top: 1rem; padding-top: 0.5rem; color: #555;">
                        > _
                    </div>
                </div>
            </div>
        </section>

        <!-- CAPABILITIES -->
        <section id="capabilities" class="scroll-reveal-trigger">
            <div class="container" style="margin-bottom: 4rem;">
                <span class="meta-tag">CORE CAPABILITIES</span>
                <h2>Standar Emas Forensik Digital</h2>
            </div>
            
            <div class="feature-grid">
                <div class="feature-card scroll-reveal">
                    <span class="feature-icon text-accent">◈</span>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Digital Chain of Custody</h3>
                    <p class="text-secondary">Pelacakan bukti end-to-end. Setiap pemindahan, analisis, dan pengambilan sampel dicatat dengan timestamp presisi milidetik.</p>
                </div>
                <div class="feature-card scroll-reveal" style="transition-delay: 0.1s;">
                    <span class="feature-icon text-accent">❖</span>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Immutable Audit Trail</h3>
                    <p class="text-secondary">Log aktivitas yang transparan dan terkunci secara kriptografis. Memastikan akuntabilitas absolut setiap personel.</p>
                </div>
                <div class="feature-card scroll-reveal" style="transition-delay: 0.2s;">
                    <span class="feature-icon text-accent">⚡</span>
                    <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Workflow Automation</h3>
                    <p class="text-secondary">Otomatisasi alur kerja dari penerimaan hingga pelaporan. Mengurangi human-error dan mempercepat time-to-justice.</p>
                </div>
            </div>
        </section>

        <!-- CONVERSION -->
        <section class="container" style="text-align: center; padding: 15rem 0;">
            <div class="scroll-reveal-trigger">
                <h2 class="scroll-reveal" style="margin-bottom: 2rem;">Ready for Deployment?</h2>
                <div class="scroll-reveal" style="transition-delay: 0.1s;">
                    <a href="{{ route('login') }}" class="btn">
                        <span>MASUK KE SISTEM</span>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div style="margin-bottom: 1rem;">LPMF LIMS // PUSDOKKES POLRI</div>
            <div style="color: #333;">&copy; 2026 Laboratorium Forensik. Restricted Access.</div>
        </div>
    </footer>

    <script>
        // CURSOR LOGIC
        const cursor = document.getElementById('cursor');
        const flashlight = document.getElementById('flashlight');
        const gridBg = document.querySelector('.grid-bg');

        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.addEventListener('mousemove', (e) => {
                const x = e.clientX;
                const y = e.clientY;
                
                cursor.style.left = x + 'px';
                cursor.style.top = y + 'px';
                
                flashlight.style.left = x + 'px';
                flashlight.style.top = y + 'px';

                // Update grid mask position
                gridBg.style.setProperty('--x', x + 'px');
                gridBg.style.setProperty('--y', y + 'px');
            });
        }

        // Hover states for cursor
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.querySelectorAll('a, button').forEach(el => {
                el.addEventListener('mouseenter', () => {
                    cursor.style.width = '50px';
                    cursor.style.height = '50px';
                    cursor.style.borderColor = 'transparent';
                    cursor.style.backgroundColor = 'rgba(255, 184, 0, 0.2)';
                });
                el.addEventListener('mouseleave', () => {
                    cursor.style.width = '20px';
                    cursor.style.height = '20px';
                    cursor.style.borderColor = 'var(--accent)';
                    cursor.style.backgroundColor = 'transparent';
                });
            });
        }

        // SCROLL OBSERVER
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.querySelectorAll('.scroll-reveal').forEach((el, i) => {
                        setTimeout(() => {
                            el.classList.add('active');
                        }, i * 150);
                    });
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.scroll-reveal-trigger').forEach(el => observer.observe(el));

        // CHAT SIMULATOR
        const messages = [
            { t: 'system', m: 'CONNECTING TO LPMF_BOT...' },
            { t: 'user', m: '/status 24-0091-B' },
            { t: 'system', m: 'SEARCHING DATABASE...' },
            { t: 'system', m: 'FOUND: REQUEST #141' },
            { t: 'system', m: 'STATUS: ANALYSIS_PHASE' },
            { t: 'system', m: 'EST. COMPLETION: 24 HRS' }
        ];

        const chatStream = document.getElementById('chat-stream');
        let msgIndex = 0;

        function typeWriter(text, element, callback) {
            let i = 0;
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, 30);
                } else if (callback) {
                    callback();
                }
            }
            type();
        }

        const chatObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && msgIndex === 0) {
                    runChat();
                }
            });
        });
        chatObserver.observe(document.querySelector('.device-frame'));

        function runChat() {
            if (msgIndex >= messages.length) return;
            
            const data = messages[msgIndex];
            const div = document.createElement('div');
            div.className = `chat-line ${data.t}`;
            chatStream.appendChild(div);
            
            setTimeout(() => {
                div.classList.add('visible');
                typeWriter(data.m, div, () => {
                    msgIndex++;
                    setTimeout(runChat, 800);
                });
            }, 100);
        }
    </script>
</body>
</html>
