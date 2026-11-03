/*!
 * Color mode - Force Light Mode by default
 */
(() => {
    'use strict'
    try {
        localStorage.removeItem('theme');
    } catch (e) {}
    document.documentElement.setAttribute('data-bs-theme', 'light');
})()
