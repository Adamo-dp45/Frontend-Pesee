/*
    - Les menus déroulants de l'en-tête. Tout passe par des écouteurs posés sur 'document', et ils
      sont posés UNE SEULE FOIS pour la session — d'où l'appel au démarrage dans 'app.js', et non
      dans le 'turbo:load'.

    - Pourquoi une seule fois : 'document' survit au remplacement de la page par Turbo, alors que
      les modules sont relancés à chaque visite. Les reposer à chaque page les empilerait, et le
      même clic serait traité deux fois, puis trois. Mesuré avant correction : trois visites
      suffisaient à tripler les quatre écouteurs de ce fichier.

    - Le corollaire : un écouteur posé une fois ne doit retenir AUCUN élément. Les boutons et les
      panneaux sont retrouvés au moment du clic, car ceux de la première page sont détachés dès
      la deuxième.
*/

/* 'bouton déclencheur' => 'panneau qu'il ouvre' */
const DECLENCHEURS = {
    profileBtn: 'profileDrop',
    // notifBtn: 'notifDrop',
}

export const closeAllDropdowns = () => {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'))
}

const basculer = (id) => {
    const drop    = document.getElementById(id)
    const wasOpen = drop?.classList.contains('open')
    closeAllDropdowns()
    if (!wasOpen) drop?.classList.add('open')
}

export const initDropdowns = () => {
    // ✅ Ferme les dropdowns sur les événements Turbo plutôt que sur le clic document
    document.addEventListener('turbo:click', closeAllDropdowns)
    document.addEventListener('turbo:submit-start', closeAllDropdowns)

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeAllDropdowns()
    })

    document.addEventListener('click', e => {
        const declencheur = Object.keys(DECLENCHEURS).find(id => e.target.closest(`#${id}`))

        if (!declencheur) {
            // ✅ Ferme si clic en dehors d'un dropdown
            if (!e.target.closest('.dropdown-anchor')) closeAllDropdowns()
            return
        }

        e.stopPropagation()
        basculer(DECLENCHEURS[declencheur])
    })
}
