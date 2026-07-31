export const closeAllDropdowns = () => {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'))
}

export const initDropdowns = () => {
    const toggleDropdown = (id, e) => {
        e.stopPropagation()
        const drop    = document.getElementById(id)
        const wasOpen = drop?.classList.contains('open')
        closeAllDropdowns()
        if (!wasOpen) drop?.classList.add('open')
    }
    // ✅ Ferme les dropdowns sur les événements Turbo plutôt que sur le clic document
    document.addEventListener('turbo:click', closeAllDropdowns)
    document.addEventListener('turbo:submit-start', closeAllDropdowns)
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllDropdowns()
    })
    // ✅ Ferme si clic en dehors d'un dropdown
    document.addEventListener('click', e => {
        if (!e.target.closest('.dropdown-anchor')) closeAllDropdowns()
    })
    // document.getElementById('notifBtn')?.addEventListener('click', e => toggleDropdown('notifDrop', e));
    document.getElementById('profileBtn')?.addEventListener('click', e => toggleDropdown('profileDrop', e))
}
