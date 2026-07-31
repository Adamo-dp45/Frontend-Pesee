import { closeAllDropdowns } from './dropdown.js'

export const initSidebar = () => {
    const sidebar = document.getElementById('sidebar')
    const overlay = document.getElementById('sidebarOverlay')
    const toggle  = document.getElementById('sidebarToggle')
    if (!sidebar || !overlay || !toggle) return

    const isMobile = () => window.innerWidth <= 768

    const closeSidebar = () => {
        sidebar.classList.remove('mobile-open')
        overlay.classList.remove('visible')
    }

    const repositionUserDrop = (collapsed) => {
        const drop = document.getElementById('sidebarUserDrop')
        if (!drop) return
        drop.style.cssText = collapsed
            ? 'left:calc(100% + .5rem);right:auto;bottom:0;top:auto'
            : 'left:0;right:0;bottom:calc(100% + .375rem);top:auto'
    }

    // Pour la persistance
    const applyCollapsed = (collapsed) => {
        sidebar.classList.toggle('collapsed', collapsed)
        repositionUserDrop(collapsed)
        document.documentElement.classList.remove('sidebar-pre-collapsed')
    }

    const toggleSidebar = () => {
        if (isMobile()) {
            const open = sidebar.classList.toggle('mobile-open')
            overlay.classList.toggle('visible', open)
        } else {
            const collapsed = sidebar.classList.toggle('collapsed')
            localStorage.setItem('sidebar-collapsed', collapsed)
            repositionUserDrop(collapsed)
        }
    }

    const closeOtherSubmenus = (currentId) => {
        document.querySelectorAll('.submenu.open').forEach(m => {
            if (m.id !== currentId) {
                m.classList.remove('open')
                m.previousElementSibling?.classList.remove('open')
            }
        })
    }

    const onSubmenuClick = (e) => {
        e.preventDefault()
        const trigger = e.currentTarget
        const id      = trigger.dataset.submenu
        const menu    = document.getElementById(id)
        if (!menu) return

        const isOpen = menu.classList.contains('open')
        closeOtherSubmenus(id)
        menu.classList.toggle('open', !isOpen)
        trigger.classList.toggle('open', !isOpen)
    }

    const onUserBtnClick = (e) => {
        e.stopPropagation()
        const drop    = document.getElementById('sidebarUserDrop')
        const wasOpen = drop?.classList.contains('open')
        closeAllDropdowns()
        if (wasOpen || !drop) return

        drop.style.cssText = isMobile()
            ? 'position:absolute;left:0;right:0;bottom:calc(100% + .375rem);top:auto;min-width:0;width:100%'
            : ''
        drop.classList.add('open')
    }

    const openActiveSubmenu = () => {
        const activeSubLink = document.querySelector('.sub-link.active')
        if (!activeSubLink) return
        const submenu = activeSubLink.closest('.submenu')
        if (!submenu) return
        submenu.classList.add('open')
        submenu.previousElementSibling?.classList.add('open')
    }

    // Persistance de l'état collapsed
    const savedCollapsed = localStorage.getItem('sidebar-collapsed') === 'true'
    if (!isMobile() && savedCollapsed) applyCollapsed(true)

    toggle.addEventListener('click', toggleSidebar)
    overlay.addEventListener('click', closeSidebar)
    window.addEventListener('resize', () => { if (!isMobile()) closeSidebar() })

    document.querySelectorAll('[data-nav]').forEach(l => l.addEventListener('click', () => { if (isMobile()) closeSidebar() }))
    document.querySelectorAll('[data-submenu]').forEach(t => t.addEventListener('click', onSubmenuClick))
    document.querySelectorAll('[data-subnav]').forEach(l => l.addEventListener('click', () => { if (isMobile()) closeSidebar() }))

    document.getElementById('sidebarUserBtn')?.addEventListener('click', onUserBtnClick)

    openActiveSubmenu()
}
