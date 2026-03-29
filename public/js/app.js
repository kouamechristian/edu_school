/**
 * EDU-SCHOOL - Script principal
 * Gestion du thème, animations, toasts, modales, sidebar mobile
 */

(function () {
    'use strict';

    /* ==================================================================
       1. THEME TOGGLE (clair / sombre) avec localStorage
       ================================================================== */
    const THEME_KEY = 'edu-school-theme';

    function getPreferredTheme() {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        const icon = document.getElementById('themeIcon');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    applyTheme(getPreferredTheme());

    document.addEventListener('DOMContentLoaded', function () {
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-bs-theme');
                applyTheme(current === 'dark' ? 'light' : 'dark');
            });
        }
    });

    /* ==================================================================
       2. SIDEBAR TOGGLE (desktop collapse + mobile show)
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('sidebarOverlay');

        if (!toggleBtn || !sidebar) return;

        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                overlay.classList.remove('active');
            });
        }
    });

    /* ==================================================================
       3. SUBMENU ACCORDÉON (sidebar)
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                var target = document.querySelector(this.getAttribute('data-bs-target'));
                var isExpanded = this.getAttribute('aria-expanded') === 'true';

                document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]').forEach(function (other) {
                    if (other !== el) {
                        var otherTarget = document.querySelector(other.getAttribute('data-bs-target'));
                        if (otherTarget && otherTarget.classList.contains('show')) {
                            otherTarget.classList.remove('show');
                            other.setAttribute('aria-expanded', 'false');
                        }
                    }
                });

                if (isExpanded) {
                    target.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    target.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            });
        });

        /* auto-expand active submenu */
        document.querySelectorAll('.sidebar .collapse .nav-link.active').forEach(function (activeLink) {
            var submenu = activeLink.closest('.collapse');
            if (submenu) {
                submenu.classList.add('show');
                var toggler = document.querySelector('[data-bs-target="#' + submenu.id + '"]');
                if (toggler) toggler.setAttribute('aria-expanded', 'true');
            }
        });
    });

    /* ==================================================================
       4. COMPTEURS ANIMÉS (cards statistiques)
       ================================================================== */
    function animateCounters() {
        document.querySelectorAll('.counter').forEach(function (el) {
            if (el.dataset.animated) return;
            var target = parseInt(el.dataset.target, 10) || 0;
            if (target === 0) { el.textContent = '0'; el.dataset.animated = '1'; return; }

            var duration = 1500;
            var step = Math.ceil(target / (duration / 16));
            var current = 0;

            function tick() {
                current += step;
                if (current >= target) {
                    el.textContent = target.toLocaleString('fr-FR');
                    el.dataset.animated = '1';
                } else {
                    el.textContent = current.toLocaleString('fr-FR');
                    requestAnimationFrame(tick);
                }
            }
            requestAnimationFrame(tick);
        });
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) animateCounters();
            });
        }, { threshold: 0.3 });

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.counter').forEach(function (el) {
                observer.observe(el);
            });
        });
    } else {
        document.addEventListener('DOMContentLoaded', animateCounters);
    }

    /* ==================================================================
       5. TOAST NOTIFICATIONS (flash messages)
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toast-container .toast').forEach(function (toastEl) {
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
        });
    });

    /* ==================================================================
       6. MODALE DE CONFIRMATION DE SUPPRESSION
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-delete');
            if (!btn) return;
            e.preventDefault();

            var url = btn.dataset.deleteUrl;
            var form = document.getElementById('deleteForm');
            if (form && url) {
                form.action = url;
                var modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                modal.show();
            }
        });
    });

    /* ==================================================================
       7. ANIMATION DES CARDS AU SCROLL
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        var cards = document.querySelectorAll('.card, .stat-card');
        if ('IntersectionObserver' in window) {
            var cardObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        cardObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            cards.forEach(function (c) { cardObserver.observe(c); });
        } else {
            cards.forEach(function (c) { c.classList.add('animate-in'); });
        }
    });

    /* ==================================================================
       8. SCROLL AUTO VERS ERREURS DE FORMULAIRE
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        var firstError = document.querySelector('.is-invalid, .invalid-feedback, .form-error-message');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            var input = firstError.closest('.mb-3, .form-group');
            if (input) {
                input.classList.add('shake-error');
                setTimeout(function () { input.classList.remove('shake-error'); }, 600);
            }
        }
    });

    /* ==================================================================
       9. SKELETON LOADING : masquer quand la page est prête
       ================================================================== */
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            document.querySelectorAll('.skeleton-loader').forEach(function (el) {
                el.style.display = 'none';
                var real = el.nextElementSibling;
                if (real && real.classList.contains('real-content')) {
                    real.style.display = '';
                }
            });
        }, 400);
    });

})();
