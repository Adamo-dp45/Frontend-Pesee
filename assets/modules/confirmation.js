/*
    - Une confirmation avant les gestes qu'on ne rattrape pas : supprimer, bloquer un poste,
      suspendre un compte, renouveler des identifiants.

    - Le principe tient en deux temps : le premier clic est retenu et ouvre la question ; une fois
      acceptée, le MÊME clic est rejoué sur le même bouton, marqué comme confirmé. Le navigateur
      fait alors exactement ce qu'il aurait fait sans nous — soumission native, jeton CSRF,
      interception par Turbo — sans que nous ayons à réimplémenter la chaîne.

    - Balisage : <button data-confirmer="Supprimer ce produit ?"
                         data-confirmer-detail="Ce qu'il advient."
                         data-confirmer-action="Supprimer">
*/

const ID = 'confirmation-dialogue'

/* Le bouton en attente de réponse. */
let cible = null

/*
    - Le dialogue est retrouvé dans le document à chaque fois, jamais gardé en mémoire : une
      navigation Turbo remplace le corps de la page, et un nœud mis en cache s'en trouve détaché.
      'showModal()' sur un nœud détaché échoue, et la confirmation ne s'ouvrirait plus jamais.
*/
function dialogue() {
    const existant = document.getElementById(ID)
    if (existant) {
        return existant
    }

    const d = document.createElement('dialog')
    d.id = ID
    d.className = 'confirmation'
    d.innerHTML = `
        <div class="confirmation-corps">
            <h2 class="confirmation-titre"></h2>
            <p class="confirmation-detail"></p>
            <div class="confirmation-actions">
                <button type="button" class="btn btn-secondary" data-role="annuler">Annuler</button>
                <button type="button" class="btn btn-danger" data-role="confirmer"></button>
            </div>
        </div>`

    d.addEventListener('click', (event) => {
        const role = event.target.closest('[data-role]')?.dataset.role
        if(!role) {
            return
        }

        d.close()
        role === 'confirmer' ? rejouer() : (cible = null)
    })

    // Échap ferme la question : c'est un refus
    d.addEventListener('cancel', () => { cible = null })

    document.body.appendChild(d)

    return d
}

/*
    - Le clic est rejoué immédiatement, sans attendre de trame. Différer le geste le sortait du
      contexte d'activation du navigateur : le bouton était bien cliqué, mais le formulaire ne
      partait pas, et rien ne le signalait.
*/
function rejouer() {
    const bouton = cible
    cible = null

    if(!bouton) {
        return
    }

    bouton.dataset.confirme = 'oui'
    bouton.click()
    delete bouton.dataset.confirme
}

export function initConfirmation() {
    // Un seul écouteur pour toute la session : les boutons peuvent apparaître après le chargement
    document.addEventListener('click', (event) => {
        const bouton = event.target.closest('[data-confirmer]')
        if(!bouton || bouton.dataset.confirme === 'oui') {
            return
        }

        event.preventDefault()
        event.stopPropagation()

        const d = dialogue()
        d.querySelector('.confirmation-titre').textContent = bouton.dataset.confirmer
        d.querySelector('.confirmation-detail').textContent = bouton.dataset.confirmerDetail || ''
        d.querySelector('.confirmation-detail').hidden = !bouton.dataset.confirmerDetail
        d.querySelector('[data-role="confirmer"]').textContent = bouton.dataset.confirmerAction || 'Confirmer'

        cible = bouton
        d.showModal()
    })
}
