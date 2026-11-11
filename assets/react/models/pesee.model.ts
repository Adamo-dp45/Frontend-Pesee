/* La forme d'une pesée telle que l'API la renvoie sur '/api/operations'. */

export type StatutReglement = 'NON_PAYE' | 'PARTIEL' | 'SOLDE' | 'RETENU'

export interface Pesee {
    id: number
    numticket: string | null
    /* Le compteur du poste : une pesée en cours n'a pas encore de ticket, mais elle a ce numéro. */
    codepesee: string | null
    peseeAt1: string
    peseeAt2: string | null
    statut: 'EN_COURS' | 'TERMINEE'
    site: { id: number; libellesite: string } | null
    produit: { id: number; libelle: string } | null
    /* Le planteur imbriqué porte de quoi décider d'un versement sans ouvrir sa fiche : son numéro,
       son réseau, et le motif qui l'empêche d'être payé — tous calculés par l'API. */
    fournisseur: {
        id: number
        nomComplet: string
        contact1: string | null
        reseau: string | null
        motifNonPayable: string | null
    } | null
    immatriculation: string | null
    chauffeur: string | null
    client: string | null
    poids1: number | null
    poids2: number | null
    poidsnet: number | null
    prixunitaire: number | null
    montantdu: number | null
    /* Ce qui part sur l'épargne du planteur, et le taux au kilo qui l'a produit. Nuls tant que la
       pesée n'a pas été arbitrée ; zéro quand elle l'a été sans prélèvement. */
    retenue: number | null
    tauxRetenue: number | null
    /* Ce qu'on remettra vraiment au planteur au guichet : montant dû moins retenue. C'est LUI qui
       borne un versement — 'montantdu' reste ce que la pesée vaut, et ne bouge jamais. */
    montantpayable: number | null
    /* Le seul montant CONFIRMÉ par la passerelle. Tant qu'un versement est en vol, il ne bouge pas. */
    montantpaye: number | null
    /* Ce qui a quitté la caisse sans réponse de la passerelle, et 'resteaengager' ce que l'API
       acceptera encore. Sans ces deux-là, une pesée qu'on vient de payer affiche le même reste
       qu'avant le versement, et on la repaie. */
    montantenvol: number | null
    resteaengager: number | null
    statutReglement: StatutReglement | null
    /* Ce qui empêche de verser sur CETTE pesée, planteur compris : produit jamais tarifé, pesée déjà
       soldée, camion encore sur le pont. Calculé par l'API, qui refusera le versement pour la même
       raison — l'écran ne redécide rien, il dit à l'avance ce que le serveur répondrait. */
    motifNonPayable: string | null
}
