/**
 * public/assets/js/main.js
 * -----------------------------------------------------------------------------
 * JavaScript progresivo del sitio. La aplicación funciona sin JS; esto solo
 * añade pequeñas mejoras de experiencia de usuario.
 * -----------------------------------------------------------------------------
 */
(function () {
    'use strict';

    // Oculta automáticamente los mensajes flash tras unos segundos.
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s ease';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // -------------------------------------------------------------------------
    // Alternar tema claro/oscuro. El tema por defecto es claro (blanco). La
    // preferencia se guarda en localStorage y se aplica temprano desde el
    // <head> para evitar parpadeos. Aquí solo gestionamos el clic y el icono.
    // -------------------------------------------------------------------------
    // -------------------------------------------------------------------------
    // Menú colapsable en móvil. En escritorio el CSS muestra los enlaces
    // siempre y oculta el botón, así que este bloque no interfiere.
    // -------------------------------------------------------------------------
    var navToggle = document.getElementById('navToggle');
    var mainNav   = document.getElementById('mainNav');

    if (navToggle && mainNav) {
        var navIcon = navToggle.querySelector('.nav-toggle__icon');

        var setNavOpen = function (open) {
            mainNav.classList.toggle('is-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Cerrar menú' : 'Abrir menú');
            if (navIcon) { navIcon.textContent = open ? '✕' : '☰'; }
        };

        var navIsOpen = function () { return mainNav.classList.contains('is-open'); };

        navToggle.addEventListener('click', function () {
            setNavOpen(!navIsOpen());
        });

        // Escape cierra el menú y devuelve el foco al botón que lo abrió.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && navIsOpen()) {
                setNavOpen(false);
                navToggle.focus();
            }
        });

        // Un toque fuera del menú lo cierra.
        document.addEventListener('click', function (e) {
            if (!navIsOpen()) { return; }
            if (mainNav.contains(e.target) || navToggle.contains(e.target)) { return; }
            setNavOpen(false);
        });

        // Al pasar a escritorio se descarta el estado "abierto": si no, al
        // volver a móvil el menú aparecería desplegado sin haberlo pedido.
        var wide = window.matchMedia('(min-width: 721px)');
        var onWideChange = function (e) { if (e.matches) { setNavOpen(false); } };

        if (wide.addEventListener) {
            wide.addEventListener('change', onWideChange);
        } else if (wide.addListener) {
            wide.addListener(onWideChange); // Safari antiguo.
        }
    }

    var toggle = document.getElementById('themeToggle');
    if (toggle) {
        var icon = toggle.querySelector('.theme-toggle__icon');

        var syncIcon = function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (icon) { icon.textContent = dark ? '☀' : '☾'; }
            toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        };
        syncIcon();

        toggle.addEventListener('click', function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            var next = dark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('tema', next); } catch (e) {}
            syncIcon();
        });
    }
})();
