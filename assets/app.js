import './stimulus_bootstrap.js';

import './styles/app.css';
import { initTheme } from './modules/theme.js';
import { initDropdowns } from './modules/dropdown.js';
import { initSidebar } from './modules/sidebar.js';
import { initGraphiques } from './modules/graphiques.js';
import { initConfirmation } from './modules/confirmation.js';

/* Un seul écouteur pour toute la session : il survit aux navigations Turbo. */
initConfirmation()

document.addEventListener('turbo:before-render', (event) => {
    if(!document.startViewTransition) { /*
            - L'api 'ViewTransition'
        */
        return
    }
    event.preventDefault()

    /*
        - 'resume()' rend la main à Turbo pour qu'il remplace la page. Tant qu'il n'est pas appelé,
          rien ne s'affiche.
        - Une transition peut être avortée par le navigateur — c'est le cas juste après la
          fermeture d'une fenêtre modale, qui quitte la couche supérieure au même instant. Sans
          ce rattrapage, l'échec emportait la navigation avec lui : le formulaire partait, le
          serveur répondait, et la page restait figée sans le moindre message.
    */
    let repris = false
    const reprendre = () => {
        if(repris) {
            return
        }
        repris = true
        event.detail.resume()
    }

    try {
        const transition = document.startViewTransition(reprendre)
        transition.ready.catch(reprendre)
        transition.finished.catch(() => {}) // Déjà traité, on évite juste un rejet non capturé
    } catch {
        reprendre()
    }
})

document.addEventListener('turbo:load', () => {
    initTheme()
    initDropdowns()
    initSidebar()
    initGraphiques()
})
