/*
    - Le thème de l'interface : clair, sombre, ou celui du système.

    - « Système » n'est pas un troisième thème, c'est l'ABSENCE de choix : on suit alors la
      préférence du système d'exploitation, et on la suit EN DIRECT — un poste qui bascule en sombre
      au coucher du soleil fait basculer l'écran avec lui, sans rechargement.

    - Le choix est le seul état stocké ('light', 'dark' ou 'system') ; ce qui est peint s'en déduit.
      Enregistrer la couleur obtenue plutôt que le choix ferait perdre « système » au premier
      changement : on ne saurait plus distinguer « il a demandé sombre » de « son système l'était ».

    - Attention au partage avec le script du '<head>' de 'base.html.twig' : lui aussi lit cette clé,
      et avant le premier rendu, pour éviter le flash blanc. Les deux lectures doivent donner le
      même résultat — une clé absente et 'system' valent toutes deux « suivre le système ».
*/

const CLE = 'theme'
const CHOIX = ['light', 'dark', 'system']

const systeme = () => window.matchMedia('(prefers-color-scheme: dark)')

/* Une valeur inconnue — clé absente, ou écrite par une version précédente — vaut « système ». */
const choixCourant = () => {
    const valeur = localStorage.getItem(CLE)

    return CHOIX.includes(valeur) ? valeur : 'system'
}

const estSombre = (choix) => choix === 'dark' || (choix === 'system' && systeme().matches)

/*
    - Peint la page et remet le menu d'accord avec elle. Appelée à chaque visite, et à chaque fois
      que le système bascule : c'est le seul endroit qui touche à la classe 'dark'.
*/
export const peindreLeTheme = () => {
    const choix = choixCourant()
    const sombre = estSombre(choix)

    document.documentElement.classList.toggle('dark', sombre)

    /* L'icône annonce ce qu'on VOIT, pas ce qu'on a choisi : en « système », c'est la couleur du moment qui parle */
    const soleil = document.getElementById('sun-i')
    const lune = document.getElementById('moon-i')

    if(soleil) {
        soleil.style.display = sombre ? 'none' : ''
    }

    if(lune) {
        lune.style.display = sombre ? '' : 'none'
    }

    const etiquette = document.getElementById('theme-lbl')

    if(etiquette) {
        etiquette.textContent = { light: 'Clair', dark: 'Sombre', system: 'Système' }[choix]
    }

    // La coche suit le CHOIX et non la couleur : sinon « Système » ne serait jamais cochée
    document.querySelectorAll('#themeDrop [data-theme]').forEach(item => {
        item.classList.toggle('actif', item.dataset.theme === choix)
        item.setAttribute('aria-checked', String(item.dataset.theme === choix))
    })
}

/*
    - UNE SEULE FOIS pour la session : l'écouteur du système porte sur un objet qui survit à Turbo,
      le reposer à chaque visite l'empilerait. Le clic sur les entrées du menu est délégué à
      'document' pour la même raison, ce qui dispense de recâbler quoi que ce soit page après page.
*/
export const initTheme = () => {
    systeme().addEventListener('change', () => {
        if(choixCourant() === 'system') {
            peindreLeTheme()
        }
    })

    document.addEventListener('click', event => {
        const choisi = event.target.closest('#themeDrop [data-theme]')

        if(!choisi) {
            return
        }

        localStorage.setItem(CLE, choisi.dataset.theme)
        peindreLeTheme()
    })
}
