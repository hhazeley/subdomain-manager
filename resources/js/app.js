const media = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(theme) {
    const dark = theme === 'dark' || (theme === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', dark);
}

const themeSwitcher = document.querySelector('#theme-switcher');

if (themeSwitcher) {
    themeSwitcher.value = localStorage.getItem('theme') || 'system';
    themeSwitcher.addEventListener('change', () => {
        localStorage.setItem('theme', themeSwitcher.value);
        applyTheme(themeSwitcher.value);
    });
}

media.addEventListener('change', () => {
    if ((localStorage.getItem('theme') || 'system') === 'system') {
        applyTheme('system');
    }
});

const priorityTypes = ['MX', 'SRV', 'URI'];
const structuredTypes = ['CERT', 'DNSKEY', 'DS', 'HTTPS', 'LOC', 'NAPTR', 'SMIMEA', 'SRV', 'SSHFP', 'SVCB', 'TLSA', 'URI'];

document.querySelectorAll('[data-dns-form]').forEach((form) => {
    const type = form.querySelector('[data-dns-type]');
    const priority = form.querySelector('[data-dns-priority]');
    const structured = form.querySelector('[data-dns-structured]');

    const updateFields = () => {
        const selectedType = type.value;
        priority.hidden = !priorityTypes.includes(selectedType);
        priority.querySelector('input').disabled = priority.hidden;
        structured.hidden = !structuredTypes.includes(selectedType);
        structured.querySelector('textarea').disabled = structured.hidden;
    };

    type.addEventListener('change', updateFields);
    updateFields();
});
