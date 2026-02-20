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

    // Intersection Observer for Fade-in Animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: "0px"
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');

                // Animate Progress Bars if it's a skill card
                if (entry.target.classList.contains('skill-card')) {
                    const progressBar = entry.target.querySelector('.progress');
                    const width = progressBar.getAttribute('data-width');
                    progressBar.style.width = width + '%';
                }

                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const fadeElements = document.querySelectorAll('.fade-in, .skill-card');
    fadeElements.forEach(el => observer.observe(el));

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

