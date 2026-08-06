const KEY = 'klask-theme';
const apply = t => {
    document.body.dataset.theme = t;
    localStorage.setItem(KEY, t);
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.textContent = t === 'dark' ? '☀️' : '🌙';
};

apply(localStorage.getItem(KEY) || 'standard');
document.getElementById('theme-toggle')
    ?.addEventListener('click', () => apply(document.body.dataset.theme === 'dark' ? 'standard' : 'dark'));
