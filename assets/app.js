import './stimulus_bootstrap.js';

import './styles/app.css';
import { initTheme } from './modules/theme.js';
import { initDropdowns } from './modules/dropdown.js';
import { initSidebar, initSidebarResize } from './modules/sidebar.js';
import { initGraphiques } from './modules/graphiques.js';
import { initConfirmation } from './modules/confirmation.js';
import { initModeReglement } from './modules/mode_reglement.js';

/*
    - UNE SEULE FOIS pour toute la session. Ces modules posent leurs écouteurs sur 'document' ou
      sur 'window', qui survivent au remplacement de la page par Turbo. Les relancer à chaque
      'turbo:load' empilerait les mêmes écouteurs page après page : au troisième écran visité, un
      clic serait traité trois fois.
    - Le prix à payer : aucun de ces écouteurs ne garde d'élément en mémoire. Ils retrouvent ce
      dont ils ont besoin au moment du geste, car les nœuds de la page précédente sont détachés.
*/
initConfirmation()
initDropdowns()
initSidebarResize()

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

/*
    - À CHAQUE PAGE. Ces modules câblent des éléments du corps de la page, que Turbo remplace à
      chaque visite : les écouteurs de la page précédente partent avec elle, il n'y a rien à
      empiler, et ceux-ci doivent être reposés sur les nouveaux nœuds.
*/
document.addEventListener('turbo:load', () => {
    initTheme()
    initSidebar()
    initGraphiques()
    initModeReglement()
})
