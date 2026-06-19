(function() {
    const sidebar = document.querySelector('.admin-sidebar');
    const logo = sidebar ? sidebar.querySelector('.admin-sidebar-logo') : null;
    const menu = sidebar ? sidebar.querySelector('.admin-menu-list') : null;

    if (!sidebar || !logo || !menu) return;

    let toggle = logo.querySelector('.admin-mobile-menu-toggle');

    if (!toggle) {
        toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'admin-mobile-menu-toggle';
        toggle.setAttribute('aria-controls', 'admin-mobile-menu-list');
        toggle.innerHTML = '<i data-lucide="menu"></i><span>Menu</span>';
        logo.appendChild(toggle);
    }

    menu.id = 'admin-mobile-menu-list';
    sidebar.classList.add('is-collapsible');

    const mobileQuery = window.matchMedia('(max-width: 900px)');

    function setOpen(isOpen, remember) {
        sidebar.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (mobileQuery.matches) {
            menu.style.setProperty('display', isOpen ? 'flex' : 'none', 'important');
        } else {
            menu.style.removeProperty('display');
        }

        if (remember) {
            sessionStorage.setItem('adminMenuOpen', isOpen ? '1' : '0');
        }
    }

    function syncMenu() {
        if (mobileQuery.matches) {
            setOpen(sessionStorage.getItem('adminMenuOpen') === '1', false);
        } else {
            setOpen(false, false);
        }
    }

    toggle.addEventListener('click', function(event) {
        event.preventDefault();
        event.stopPropagation();
        setOpen(!sidebar.classList.contains('is-open'), true);
    });

    syncMenu();
    mobileQuery.addEventListener?.('change', syncMenu);
    window.lucide?.createIcons();
})();