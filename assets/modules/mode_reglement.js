/*
    - L'écran de versement sert deux gestes qui n'ont presque rien en commun : remettre des billets,
      et envoyer de l'argent sur un téléphone. Le numéro et le réseau n'ont de sens que pour le
      second, et les laisser à l'écran en espèces fait hésiter l'opérateur — il croit devoir les
      remplir, et il a un planteur en face de lui.

    - Ce module ne fait que MASQUER : il ne vide aucun champ, il ne porte aucune règle. Sans
      JavaScript la page reste utilisable — les deux champs s'affichent, l'opérateur les ignore, et
      le serveur les ignore aussi quand le mode est « espèces ». C'est le serveur qui décide, ici on
      ne fait que ranger l'écran.

    - Balisage : <select data-mode-reglement> pilote les <div data-si-mobile>.
*/
export const initModeReglement = () => {
    const mode = document.querySelector('[data-mode-reglement]')
    if(!mode) {
        return
    }

    const blocs = document.querySelectorAll('[data-si-mobile]')

    const appliquer = () => {
        const mobile = mode.value === 'MOBILE_MONEY'
        blocs.forEach(bloc => { bloc.hidden = !mobile })
    }

    /*
        - L'écouteur est posé sur le champ lui-même, pas sur le document : Turbo remplace le corps
          de la page à chaque navigation, donc le <select> retrouvé ici est neuf à chaque appel et
          repart sans écouteur. Un écouteur délégué sur le document, lui, s'empilerait à chaque
          'turbo:load'.
    */
    mode.addEventListener('change', appliquer)

    // L'état d'ouverture compte autant que le changement : le mode arrive parfois pré-choisi en espèces
    appliquer()
}
