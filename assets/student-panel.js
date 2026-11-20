(function () {
    const menu = document.getElementById('student-menu');
    const toggle = document.getElementById('student-menu-toggle');
    const close = document.getElementById('student-menu-close');
    const backdrop = document.getElementById('student-menu-backdrop');

    if (!menu || !toggle || !close || !backdrop) {
        return;
    }

    function setOpen(open) {
        menu.classList.toggle('is-open', open);
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        backdrop.hidden = !open;
        document.body.classList.toggle('student-menu-open', open);
        if (open) {
            close.focus();
        } else {
            toggle.focus();
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    close.addEventListener('click', function () {
        setOpen(false);
    });
    backdrop.addEventListener('click', function () {
        setOpen(false);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
        }
    });
})();
