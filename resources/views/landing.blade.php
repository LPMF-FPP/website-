<!doctype html>
<html lang="id" class="scroll-smooth">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>LPMF LIMS | The Pulse of Evidence</title>
        <meta
            name="description"
            content="Sistem Informasi Manajemen Laboratorium Forensik Pusdokkes Polri. Aman. Presisi. Real-time."
        />

        <!-- Fonts -->
        <link
            href="https://api.fontshare.com/v2/css?f[]=cabinet-grotesk@800,700,500&f[]=satoshi@400,500,700&display=swap"
            rel="stylesheet"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap"
            rel="stylesheet"
        />

        <style>
            :root {
                /* Clinical Precision Palette (Light Theme) */
                --bg-body: #f3f5f7;
                --text-primary: #111827;
                --text-secondary: #64748b;
                --text-tertiary: #94a3b8;

                --brand-primary: #2e5cff;
                --brand-secondary: #00cc88;
                --surface-white: #ffffff;
                --surface-glass: rgba(255, 255, 255, 0.8);

                /* Typography */
                --font-display: "Cabinet Grotesk", sans-serif;
                --font-body: "Satoshi", sans-serif;
                --font-mono: "JetBrains Mono", monospace;

                /* Easing */
                --ease-out-expo: cubic-bezier(0.19, 1, 0.22, 1);
            }

            /* Reset */
            *,
            *::before,
            *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                background-color: var(--bg-body);
                color: var(--text-primary);
                font-family: var(--font-body);
                line-height: 1.6;
                overflow-x: hidden;
                -webkit-font-smoothing: antialiased;
            }

            ::selection {
                background: var(--brand-primary);
                color: white;
            }

            /* Typography */
            h1,
            h2,
            h3,
            h4 {
                font-family: var(--font-display);
                line-height: 1.1;
                letter-spacing: -0.02em;
            }

            h1 {
                font-size: clamp(3.5rem, 8vw, 6rem);
                font-weight: 800;
                color: var(--text-primary);
            }

            h2 {
                font-size: clamp(2rem, 5vw, 3.5rem);
                font-weight: 700;
            }

            .text-mono {
                font-family: var(--font-mono);
            }
            .text-brand {
                color: var(--brand-primary);
            }

            /* Utilities */
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 2rem;
            }
            .flex {
                display: flex;
            }
            .flex-col {
                flex-direction: column;
            }
            .items-center {
                align-items: center;
            }
            .justify-between {
                justify-content: space-between;
            }
            .justify-center {
                justify-content: center;
            }
            .grid {
                display: grid;
            }
            .gap-4 {
                gap: 1rem;
            }
            .gap-8 {
                gap: 2rem;
            }
            .gap-12 {
                gap: 3rem;
            }
            .h-screen {
                height: 100vh;
            }
            .w-full {
                width: 100%;
            }
            .relative {
                position: relative;
            }
            .absolute {
                position: absolute;
            }
            .overflow-hidden {
                overflow: hidden;
            }

            /* Components */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 1rem 2rem;
                font-family: var(--font-body);
                font-weight: 700;
                font-size: 1rem;
                text-decoration: none;
                transition: all 0.3s ease;
                border-radius: 8px;
                border: 1px solid transparent;
            }

            .btn-primary {
                background: var(--brand-primary);
                color: white;
                box-shadow: 0 10px 20px -5px rgba(46, 92, 255, 0.4);
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 30px -5px rgba(46, 92, 255, 0.5);
            }

            .btn-outline {
                background: white;
                color: var(--text-primary);
                border-color: #e2e8f0;
            }
            .btn-outline:hover {
                border-color: var(--brand-primary);
                color: var(--brand-primary);
            }

            .glass-panel {
                background: var(--surface-glass);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 9999px;
                color: var(--text-secondary);
                font-size: 0.875rem;
                font-weight: 500;
            }

            /* Animations */
            .reveal-text {
                opacity: 0;
                transform: translateY(20px);
                transition:
                    opacity 0.8s var(--ease-out-expo),
                    transform 0.8s var(--ease-out-expo);
            }
            .reveal-text.visible {
                opacity: 1;
                transform: translateY(0);
            }

            /* --- SECTION: CANVAS & HERO --- */
            #network-canvas {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: -1;
            }

            .hero-section {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
            }

            /* --- SECTION: WHATSAPP SIMULATOR --- */
            .chat-container {
                background: white;
                border-radius: 24px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
                max-width: 480px;
                margin: 0 auto;
                overflow: hidden;
                border: 1px solid #f1f5f9;
            }

            .chat-header {
                background: #f8fafc;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            .chat-avatar {
                width: 40px;
                height: 40px;
                background: var(--brand-primary);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
            }

            .chat-content {
                height: 400px;
                padding: 1.5rem;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 1rem;
                background: #ffffff;
            }

            .msg-row {
                display: flex;
                flex-direction: column;
                max-width: 85%;
            }
            .msg-row.out {
                align-self: flex-end;
                align-items: flex-end;
            }
            .msg-row.in {
                align-self: flex-start;
                align-items: flex-start;
            }

            .msg-bubble {
                padding: 0.75rem 1.25rem;
                border-radius: 16px;
                font-size: 0.95rem;
                line-height: 1.5;
                position: relative;
            }

            /* User (Right) - Green */
            .msg-row.out .msg-bubble {
                background: #dcf8c6;
                color: #111827;
                border-bottom-right-radius: 4px;
            }

            /* System (Left) - White/Gray */
            .msg-row.in .msg-bubble {
                background: #f1f5f9;
                color: #111827;
                border-bottom-left-radius: 4px;
            }

            .msg-meta {
                font-size: 0.7rem;
                color: var(--text-tertiary);
                margin-top: 0.25rem;
            }

            /* --- SECTION: SENSOR CARDS --- */
            .sensor-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem;
            }

            .sensor-card {
                background: white;
                padding: 2rem;
                border-radius: 16px;
                border: 1px solid #f1f5f9;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }
            .sensor-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                border-color: var(--brand-primary);
            }
            .sensor-value {
                font-family: var(--font-display);
                font-size: 3rem;
                font-weight: 700;
                line-height: 1;
                margin: 1rem 0;
                color: var(--text-primary);
            }

            /* --- FOOTER --- */
            footer {
                background: white;
                border-top: 1px solid #f1f5f9;
                padding: 4rem 0;
            }
        </style>
    </head>
    <body>
        <!-- Background Canvas -->
        <canvas id="network-canvas"></canvas>

        <!-- Navigation -->
        <nav class="fixed top-0 w-full z-50 glass-panel">
            <div
                class="container flex justify-between items-center"
                style="height: 80px"
            >
                <div class="flex items-center gap-4">
                    <img
                        src="https://storage.pusdokkes.polri.go.id/pusdokkes/logo.png"
                        alt="Pusdokkes Polri"
                        style="height: 45px; width: auto"
                    />
                    <div style="font-family: var(--font-display); font-weight: 800; font-size: 1.25rem; color: var(--text-primary); letter-spacing: -0.01em;">
                        FARMAPOL PUSDOKKES POLRI
                    </div>
                </div>

                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="btn btn-primary"
                        style="padding: 0.75rem 1.5rem"
                        >Dashboard</a
                    >
                @else
                    <a
                        href="{{ route('login') }}"
                        class="btn btn-primary"
                        style="padding: 0.75rem 1.5rem"
                        >Masuk Sistem</a
                    >
                @endauth
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero-section container relative z-10">
            <div style="max-width: 800px">
                <div
                    class="flex items-center justify-center gap-2 mb-6 reveal-text"
                    style="
                        transition-delay: 100ms;
                        color: var(--text-secondary);
                        font-weight: 500;
                    "
                >
                    <img
                        src="https://storage.pusdokkes.polri.go.id/pusdokkes/logo.png"
                        alt="Logo"
                        style="height: 24px; opacity: 0.8"
                    />
                    <span>Powered by Pusdokkes Polri</span>
                </div>

                <h1 class="mb-6 reveal-text" style="transition-delay: 200ms">
                    The Pulse of <br />
                    <span class="text-brand">Evidence.</span>
                </h1>
                <p
                    class="text-xl reveal-text"
                    style="
                        color: var(--text-secondary);
                        max-width: 600px;
                        margin: 0 auto 3rem auto;
                        transition-delay: 300ms;
                    "
                >
                    Sistem Informasi Manajemen Laboratorium Forensik dengan
                    Chain of Custody yang aman, audit trail yang tidak dapat
                    diubah, dan telemetri real-time.
                </p>
                <div
                    class="flex justify-center gap-4 reveal-text"
                    style="transition-delay: 400ms"
                >
                    <a href="{{ route('public.tracking') }}" class="btn btn-primary"
                        >Lacak Permintaan</a
                    >
                    <a href="#network" class="btn btn-outline">Lihat Demo</a>
                </div>
            </div>

            <!-- Scroll Indicator -->
            <div
                class="absolute bottom-10 left-0 w-full flex justify-center text-sm text-secondary"
                style="opacity: 0.7"
            >
                Scroll untuk menjelajahi
            </div>
        </header>

        <!-- Section: Narrative/Mission -->
        <section id="mission" class="py-32 relative z-10 bg-white">
            <div class="container grid gap-12 md:grid-cols-2 items-center">
                <div>
                    <div class="badge mb-4">Misi Utama</div>
                    <h2 class="mb-6">Akuntabilitas Absolut.</h2>
                    <p class="text-lg text-secondary mb-8">
                        Dalam forensik, kebenaran bersifat biner. Sistem kami
                        menghilangkan ambiguitas dengan pelacakan tingkat militer
                        dari TKP hingga ruang sidang. Setiap transfer, setiap
                        analisis, setiap mikrogram tercatat.
                    </p>
                    <ul class="text-sm grid gap-4 font-medium text-secondary">
                        <li class="flex items-center gap-4">
                            <span class="text-brand">✔</span>
                            Chain of Custody Otomatis
                        </li>
                        <li class="flex items-center gap-4">
                            <span class="text-brand">✔</span>
                            Metadata Tersangka Terenkripsi
                        </li>
                        <li class="flex items-center gap-4">
                            <span class="text-brand">✔</span>
                            Audit Log yang Tidak Dapat Diubah
                        </li>
                    </ul>
                </div>
                <div
                    class="p-8 rounded-2xl border border-gray-100 shadow-lg bg-gray-50 font-mono text-xs"
                >
                    <div
                        class="mb-4 text-tertiary border-b border-gray-200 pb-2 font-bold"
                    >
                        LOG_TRANSAKSI_TERBARU
                    </div>
                    <div class="grid gap-3">
                        <div class="flex justify-between">
                            <span class="text-brand">14:02:22</span>
                            <span class="text-primary"
                                >SAMPLE_RCV :: 24-0091-B</span
                            >
                        </div>
                        <div class="flex justify-between text-secondary">
                            <span>14:01:45</span>
                            <span>AUTH_VERIFIED :: IPDA_J_DOE</span>
                        </div>
                        <div class="flex justify-between text-secondary">
                            <span>13:58:12</span>
                            <span>TRANSFER_REQ :: LAB_CHEM_01</span>
                        </div>
                        <div class="flex justify-between text-tertiary">
                            <span>13:45:00</span>
                            <span>SYS_CHECK :: ALL_GREEN</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section: Network/WhatsApp Simulator -->
        <section
            id="network"
            class="py-32 relative z-10"
            style="background: #f8fafc"
        >
            <div class="container">
                <div class="text-center mb-16">
                    <div class="badge mb-4" style="background: white">
                        Komunikasi Aman
                    </div>
                    <h2 class="mb-4">The Neural Link.</h2>
                    <p class="text-secondary max-w-xl mx-auto">
                        Antarmuka langsung dengan GOWA Bot untuk pengecekan
                        status instan. Dapat diakses di mana saja, aman di mana
                        saja.
                    </p>
                </div>

                <div class="chat-container">
                    <div class="chat-header">
                        <div class="chat-avatar">L</div>
                        <div>
                            <div class="font-bold text-sm">LPMF Bot</div>
                            <div class="text-xs text-brand">● Online</div>
                        </div>
                    </div>
                    <div class="chat-content" id="chat-content">
                        <!-- Messages injected via JS -->
                    </div>
                </div>
            </div>
        </section>

        <!-- Section: Telemetry -->
        <section id="telemetry" class="py-32 relative z-10">
            <div class="container">
                <h2 class="mb-12">Telemetri Langsung.</h2>
                <div class="sensor-grid">
                    <!-- Sensor 1 -->
                    <div class="sensor-card">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-sm font-bold text-secondary"
                                >Penyimpanan A - Cold Chain</span
                            >
                            <span
                                style="
                                    width: 8px;
                                    height: 8px;
                                    background: var(--brand-secondary);
                                    border-radius: 50%;
                                "
                            ></span>
                        </div>
                        <div class="sensor-value">24.5°C</div>
                        <div class="text-sm text-secondary">
                            Stabilitas: <span class="text-brand">99.8%</span>
                        </div>
                    </div>
                    <!-- Sensor 2 -->
                    <div class="sensor-card">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-sm font-bold text-secondary"
                                >Kelembaban Lab</span
                            >
                            <span
                                style="
                                    width: 8px;
                                    height: 8px;
                                    background: var(--brand-secondary);
                                    border-radius: 50%;
                                "
                            ></span>
                        </div>
                        <div class="sensor-value">45%</div>
                        <div class="text-sm text-secondary">
                            Atmosfer: <span class="text-brand">Optimal</span>
                        </div>
                    </div>
                    <!-- Sensor 3 -->
                    <div class="sensor-card" style="border-color: #fee2e2">
                        <div class="flex justify-between items-start mb-4">
                            <span class="text-sm font-bold text-red-500"
                                >Inventaris: Alkohol 96%</span
                            >
                            <span
                                style="
                                    width: 8px;
                                    height: 8px;
                                    background: #ef4444;
                                    border-radius: 50%;
                                "
                            ></span>
                        </div>
                        <div class="sensor-value text-red-500">LOW</div>
                        <div class="text-sm text-secondary">
                            Alert Terkirim:
                            <span class="font-medium">Restock Diperlukan</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <div
                class="container flex flex-col md:flex-row justify-between gap-8"
            >
                <div>
                    <h4 class="text-xl font-bold mb-2">LPMF LIMS</h4>
                    <p class="text-secondary text-sm max-w-xs">
                        Sistem Informasi Laboratorium Forensik Pusdokkes Polri.
                        Akses Terbatas.
                    </p>
                </div>
                <div class="flex gap-12 text-sm text-secondary">
                    <ul class="grid gap-2">
                        <li class="font-bold text-primary mb-2">Sistem</li>
                        <li>
                            <a
                                href="{{ route('login') }}"
                                class="hover:text-brand"
                                style="text-decoration: none; color: inherit"
                                >Login</a
                            >
                        </li>
                        <li>
                            <a
                                href="{{ route('public.tracking') }}"
                                class="hover:text-brand"
                                style="text-decoration: none; color: inherit"
                                >Pelacakan</a
                            >
                        </li>
                    </ul>
                    <ul class="grid gap-2">
                        <li class="font-bold text-primary mb-2">Legal</li>
                        <li>
                            <a
                                href="#"
                                class="hover:text-brand"
                                style="text-decoration: none; color: inherit"
                                >Privasi</a
                            >
                        </li>
                        <li>
                            <a
                                href="#"
                                class="hover:text-brand"
                                style="text-decoration: none; color: inherit"
                                >Syarat</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </footer>

        <script>
            // --- 1. Network Nodes Canvas (Clinical Theme) ---
            const canvas = document.getElementById("network-canvas");
            const ctx = canvas.getContext("2d");
            let width, height;

            function resize() {
                width = window.innerWidth;
                height = window.innerHeight;
                canvas.width = width;
                canvas.height = height;
            }

            // Particles - Denser network for high-impact look
            const particles = [];
            const particleCount = Math.min(Math.floor(window.innerWidth * 0.08), 120); // Responsive, max 120
            const connectionDistance = 180; // Increased for more connections

            class Particle {
                constructor() {
                    this.x = Math.random() * width;
                    this.y = Math.random() * height;
                    this.vx = (Math.random() - 0.5) * 0.5;
                    this.vy = (Math.random() - 0.5) * 0.5;
                    this.size = Math.random() * 2 + 1;
                }

                update() {
                    this.x += this.vx;
                    this.y += this.vy;

                    // Bounce
                    if (this.x < 0 || this.x > width) this.vx *= -1;
                    if (this.y < 0 || this.y > height) this.vy *= -1;
                }

                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fillStyle = "rgba(46, 92, 255, 0.5)"; // Blue
                    ctx.fill();
                }
            }

            function initParticles() {
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle());
                }
            }

            function animate() {
                ctx.clearRect(0, 0, width, height);

                // Draw connections
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dx = particles[i].x - particles[j].x;
                        const dy = particles[i].y - particles[j].y;
                        const dist = Math.sqrt(dx * dx + dy * dy);

                        if (dist < connectionDistance) {
                            ctx.beginPath();
                            ctx.strokeStyle = `rgba(46, 92, 255, ${1 - dist / connectionDistance})`;
                            ctx.lineWidth = 0.5;
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            ctx.stroke();
                        }
                    }
                }

                // Update and draw particles
                particles.forEach((p) => {
                    p.update();
                    p.draw();
                });

                requestAnimationFrame(animate);
            }

            window.addEventListener("resize", resize);
            resize();
            initParticles();
            animate();

            // --- 2. Scroll Reveal ---
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("visible");
                        }
                    });
                },
                { threshold: 0.1 },
            );
            document
                .querySelectorAll(".reveal-text")
                .forEach((el) => observer.observe(el));

            // --- 3. WhatsApp Simulator (Natural Timing) ---
            const chatContainer = document.getElementById("chat-content");
            const messages = [
                {
                    text: "/resi LPMF-001",
                    type: "out",
                    delay: 1000,
                },
                {
                    text: "Halo! Sistem sedang melacak permintaan Anda...",
                    type: "in",
                    delay: 1500,
                },
                {
                    text: "✅ Permintaan Ditemukan\n\nNomor Resi: LPMF-001\nStatus: Tahap Analisis (2/5)\nEstimasi Selesai: 24 Jam",
                    type: "in",
                    delay: 2000,
                },
                {
                    text: "Terima kasih informasinya.",
                    type: "out",
                    delay: 1500,
                },
            ];

            let msgIndex = 0;

            function addMessage(msg) {
                const row = document.createElement("div");
                row.className = `msg-row ${msg.type}`;

                const bubble = document.createElement("div");
                bubble.className = "msg-bubble";
                bubble.innerText = msg.text;
                row.appendChild(bubble);

                // Time
                const time = new Date().toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                });
                const meta = document.createElement("div");
                meta.className = "msg-meta";
                meta.innerText = time;
                meta.style.textAlign = msg.type === "out" ? "right" : "left";
                row.appendChild(meta);

                chatContainer.appendChild(row);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }

            function runChat() {
                if (msgIndex < messages.length) {
                    const msg = messages[msgIndex];
                    setTimeout(() => {
                        addMessage(msg);
                        msgIndex++;
                        runChat();
                    }, msg.delay);
                }
            }

            // Start chat when visible
            const chatObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting && msgIndex === 0) {
                        runChat();
                    }
                });
            });
            chatObserver.observe(document.querySelector(".chat-container"));
        </script>
    </body>
</html>
