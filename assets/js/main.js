// assets/js/main.js — DevOps Professional Theme

document.addEventListener('DOMContentLoaded', () => {

    // ========================================
    // Navbar Scroll Effect with Glow
    // ========================================
    const nav = document.querySelector('nav');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // ========================================
    // Intersection Observer — Fade-in
    // ========================================
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

    // ========================================
    // Skills Section — Staggered Card Reveal
    // ========================================
    const skillsSection = document.getElementById('skills');
    if (skillsSection) {
        const skillsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    document.querySelectorAll('.skill-card').forEach((card, i) => {
                        setTimeout(() => card.classList.add('visible'), i * 100);
                    });
                    skillsObserver.disconnect();
                }
            });
        }, { threshold: 0.05 });
        skillsObserver.observe(skillsSection);
    }

    // ========================================
    // Smooth Scroll for Internal Links
    // ========================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // ========================================
    // Fellowship Filter Tabs
    // ========================================
    document.querySelectorAll('.ftab').forEach(tab => {
        tab.addEventListener('click', () => {
            const group = tab.dataset.group;

            if (group) {
                document.querySelectorAll(`.ftab[data-group="${group}"]`).forEach(t => t.classList.remove('active'));
                const container = tab.closest('.fellowship-content-col');
                if (container) {
                    container.querySelectorAll('.ftab-panel').forEach(p => p.classList.remove('active'));
                }
            } else {
                document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.ftab-panel').forEach(p => p.classList.remove('active'));
            }

            tab.classList.add('active');
            const targetId = tab.dataset.target;
            const target = document.getElementById(targetId);
            if (target) target.classList.add('active');
        });
    });

    // ========================================
    // Matrix/Particle Rain — Hero Background
    // ========================================
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
        const canvas = document.createElement('canvas');
        canvas.id = 'hero-particles';
        canvas.style.cssText = 'position:absolute;inset:0;z-index:0;pointer-events:none;';
        heroSection.style.position = 'relative';
        heroSection.insertBefore(canvas, heroSection.firstChild);

        const ctx = canvas.getContext('2d');
        let particles = [];
        const CHARS = '01αβγδ{}[]<>/=;:$#@%&*+~_|';
        const MAX_PARTICLES = 35;

        function resizeCanvas() {
            canvas.width = heroSection.offsetWidth;
            canvas.height = heroSection.offsetHeight;
        }

        function createParticle() {
            return {
                x: Math.random() * canvas.width,
                y: Math.random() * -canvas.height,
                speed: 0.3 + Math.random() * 0.8,
                char: CHARS[Math.floor(Math.random() * CHARS.length)],
                opacity: 0.03 + Math.random() * 0.08,
                size: 10 + Math.random() * 6
            };
        }

        function initParticles() {
            particles = [];
            for (let i = 0; i < MAX_PARTICLES; i++) {
                const p = createParticle();
                p.y = Math.random() * canvas.height;
                particles.push(p);
            }
        }

        function drawParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = '14px "JetBrains Mono", monospace';

            particles.forEach(p => {
                ctx.fillStyle = `rgba(0, 212, 255, ${p.opacity})`;
                ctx.font = `${p.size}px "JetBrains Mono", monospace`;
                ctx.fillText(p.char, p.x, p.y);
                p.y += p.speed;

                if (p.y > canvas.height + 20) {
                    p.y = -20;
                    p.x = Math.random() * canvas.width;
                    p.char = CHARS[Math.floor(Math.random() * CHARS.length)];
                }
            });

            requestAnimationFrame(drawParticles);
        }

        resizeCanvas();
        initParticles();
        drawParticles();

        // Debounced resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                resizeCanvas();
                initParticles();
            }, 200);
        });
    }

    // ========================================
    // Parallax for Floating Elements
    // ========================================
    document.addEventListener('mousemove', (e) => {
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;

        document.querySelectorAll('.floating-badge').forEach((badge, i) => {
            const speed = 8 + i * 4;
            const x = (0.5 - mouseX) * speed;
            const y = (0.5 - mouseY) * speed;
            badge.style.transform = `translate(${x}px, ${y}px)`;
        });
    });

    // ========================================
    // Animated counter for skill percentages
    // ========================================
    const counters = document.querySelectorAll('.skill-percentage');
    if (counters.length > 0) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const text = el.textContent;
                    const match = text.match(/(\d+)/);
                    if (match) {
                        const target = parseInt(match[1]);
                        let current = 0;
                        const suffix = text.replace(/\d+/, '').trim();
                        const duration = 1200;
                        const step = target / (duration / 16);

                        const timer = setInterval(() => {
                            current += step;
                            if (current >= target) {
                                current = target;
                                clearInterval(timer);
                            }
                            el.textContent = Math.floor(current) + '% ' + suffix;
                        }, 16);
                    }
                    counterObserver.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(c => counterObserver.observe(c));
    }

});
