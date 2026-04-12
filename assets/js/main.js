// assets/js/main.js — TinkerHub-Inspired Editorial Theme

document.addEventListener('DOMContentLoaded', () => {

    // ========================================
    // Navbar Scroll Effect
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
                        setTimeout(() => card.classList.add('visible'), i * 120);
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
    // Floating dot grid particles — TinkerHub-style
    // Subtle background dots in hero area
    // ========================================
    const heroSection = document.querySelector('.hero');
    if (heroSection) {
        const canvas = document.createElement('canvas');
        canvas.id = 'hero-particles';
        canvas.style.cssText = 'position:absolute;inset:0;z-index:0;pointer-events:none;';
        heroSection.style.position = 'relative';
        heroSection.insertBefore(canvas, heroSection.firstChild);

        const ctx = canvas.getContext('2d');
        let dots = [];
        const DOT_COUNT = 40;

        function resizeCanvas() {
            canvas.width = heroSection.offsetWidth;
            canvas.height = heroSection.offsetHeight;
        }

        function initDots() {
            dots = [];
            for (let i = 0; i < DOT_COUNT; i++) {
                dots.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    vx: (Math.random() - 0.5) * 0.3,
                    vy: (Math.random() - 0.5) * 0.3,
                    r: 1.5 + Math.random() * 2,
                    opacity: 0.06 + Math.random() * 0.08
                });
            }
        }

        function drawDots() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            dots.forEach(d => {
                ctx.beginPath();
                ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(17, 17, 17, ${d.opacity})`;
                ctx.fill();

                d.x += d.vx;
                d.y += d.vy;

                if (d.x < 0 || d.x > canvas.width) d.vx *= -1;
                if (d.y < 0 || d.y > canvas.height) d.vy *= -1;
            });

            // Draw faint connecting lines between close dots
            for (let i = 0; i < dots.length; i++) {
                for (let j = i + 1; j < dots.length; j++) {
                    const dx = dots[i].x - dots[j].x;
                    const dy = dots[i].y - dots[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 120) {
                        ctx.beginPath();
                        ctx.moveTo(dots[i].x, dots[i].y);
                        ctx.lineTo(dots[j].x, dots[j].y);
                        ctx.strokeStyle = `rgba(17, 17, 17, ${0.03 * (1 - dist / 120)})`;
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }

            requestAnimationFrame(drawDots);
        }

        resizeCanvas();
        initDots();
        drawDots();

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                resizeCanvas();
                initDots();
            }, 200);
        });
    }

    // ========================================
    // Parallax for Floating Badges (subtle)
    // ========================================
    document.addEventListener('mousemove', (e) => {
        document.querySelectorAll('.floating-badge').forEach((badge, i) => {
            const speed = 6 + i * 3;
            const x = (0.5 - e.clientX / window.innerWidth) * speed;
            const y = (0.5 - e.clientY / window.innerHeight) * speed;
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

    // ========================================
    // Tilt effect on cards (TinkerHub scrapbook)
    // ========================================
    document.querySelectorAll('.project-card, .skill-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transition = 'transform 0.3s, box-shadow 0.3s';
        });
    });

    // ========================================
    // Timeline: Scroll, Arrows, Drag
    // ========================================
    const tlScroll = document.getElementById('tlScrollArea');
    const tlLeft = document.getElementById('tlArrowLeft');
    const tlRight = document.getElementById('tlArrowRight');

    if (tlScroll) {
        const SCROLL_AMOUNT = 240;

        // Arrow buttons
        if (tlLeft) tlLeft.addEventListener('click', () => {
            tlScroll.scrollBy({ left: -SCROLL_AMOUNT, behavior: 'smooth' });
        });
        if (tlRight) tlRight.addEventListener('click', () => {
            tlScroll.scrollBy({ left: SCROLL_AMOUNT, behavior: 'smooth' });
        });

        // Drag to scroll
        let isDragging = false, startX, scrollLeft;
        tlScroll.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.pageX - tlScroll.offsetLeft;
            scrollLeft = tlScroll.scrollLeft;
            tlScroll.style.cursor = 'grabbing';
        });
        tlScroll.addEventListener('mouseleave', () => {
            isDragging = false;
            tlScroll.style.cursor = 'grab';
        });
        tlScroll.addEventListener('mouseup', () => {
            isDragging = false;
            tlScroll.style.cursor = 'grab';
        });
        tlScroll.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.pageX - tlScroll.offsetLeft;
            const walk = (x - startX) * 1.5;
            tlScroll.scrollLeft = scrollLeft - walk;
        });

        // Auto-scroll to current month on load
        const currentMonthEl = tlScroll.querySelector('.tl-current-month');
        if (currentMonthEl) {
            setTimeout(() => {
                const offset = currentMonthEl.offsetLeft - tlScroll.offsetWidth / 2;
                tlScroll.scrollTo({ left: Math.max(0, offset), behavior: 'smooth' });
            }, 600);
        }
    }

});
