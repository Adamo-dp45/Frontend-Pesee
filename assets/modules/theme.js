export const initTheme = () => {
    const themeBtn = document.getElementById('themeBtn')
    if (!themeBtn) return

    const sunIcon  = document.getElementById('sun-i')
    const moonIcon = document.getElementById('moon-i')
    const themeLbl = document.getElementById('theme-lbl')

    const applyTheme = (isDark) => {
        document.documentElement.classList.toggle('dark', isDark)
        if (sunIcon)  sunIcon.style.display  = isDark ? 'none' : ''
        if (moonIcon) moonIcon.style.display = isDark ? '' : 'none'
        if (themeLbl) themeLbl.textContent   = isDark ? 'Light' : 'Dark'
    }

    const onThemeClick = () => {
        const isDark = !document.documentElement.classList.contains('dark')
        localStorage.setItem('theme', isDark ? 'dark' : 'light')
        applyTheme(isDark)
    }

    const savedTheme = localStorage.getItem('theme')
    applyTheme(savedTheme === 'dark')

    themeBtn.addEventListener('click', onThemeClick)
}