/* La forme d'une pesée telle que l'API la renvoie sur '/api/operations'. */

export type StatutReglement = 'NON_PAYE' | 'PARTIEL' | 'SOLDE'

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
    montantpaye: number | null
    statutReglement: StatutReglement | null
}
