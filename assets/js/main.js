// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {

    // Navbar Scroll Effect
    const nav = document.querySelector('nav');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            nav.classList.add('scrolled');
        } else {
            nav.classList.remove('scrolled');
        }
    });

    // Intersection Observer for general fade-in animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

    // Skills section: show ALL cards the moment the section enters the viewport
    const skillsSection = document.getElementById('skills');
    if (skillsSection) {
        const skillsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    document.querySelectorAll('.skill-card').forEach((card, i) => {
                        setTimeout(() => card.classList.add('visible'), i * 80);
                    });
                    skillsObserver.disconnect();
                }
            });
        }, { threshold: 0.05, rootMargin: '0px 0px 0px 0px' });
        skillsObserver.observe(skillsSection);
    }

    // Parallax Effect for floating elements (if any)
    document.addEventListener('mousemove', (e) => {
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;

        document.querySelectorAll('.parallax').forEach(el => {
            const speed = el.getAttribute('data-speed') || 20;
            const x = (window.innerWidth - e.pageX * speed) / 100;
            const y = (window.innerHeight - e.pageY * speed) / 100;

            el.style.transform = `translateX(${x}px) translateY(${y}px)`;
        });
    });

    // Smooth Scroll for Internal Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // ---- Fellowship Filter Tabs (per-fellowship group) ----
    document.querySelectorAll('.ftab').forEach(tab => {
        tab.addEventListener('click', () => {
            const group = tab.dataset.group;

            if (group) {
                // Scope to this fellowship's group only
                document.querySelectorAll(`.ftab[data-group="${group}"]`).forEach(t => t.classList.remove('active'));
                // Hide all panels belonging to this group
                // Panels are siblings: their IDs start with group prefix
                const container = tab.closest('.fellowship-content-col');
                if (container) {
                    container.querySelectorAll('.ftab-panel').forEach(p => p.classList.remove('active'));
                }
            } else {
                // Legacy fallback: global deactivate
                document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.ftab-panel').forEach(p => p.classList.remove('active'));
            }

            tab.classList.add('active');
            const targetId = tab.dataset.target;
            const target = document.getElementById(targetId);
            if (target) target.classList.add('active');
        });
    });

});

