/* Les ressources de gestion, telles que l'API les renvoie. */

export type Statut = 'ACTIF' | 'SUSPENDU'
export type StatutSite = 'ACTIF' | 'BLOQUE'
export type ReseauMobile = 'ORANGE' | 'MTN' | 'MOOV' | 'WAVE'

/* Les relations arrivent imbriquées, réduites aux champs utiles à l'affichage. */
export interface SiteBref {
    id: number
    code: string
    libellesite: string
}

/* Compteur calculé en SQL par l'API : la liste ne reçoit jamais la collection, seulement son total. */
interface AvecPesees {
    peseesCount: number
}

export interface UserBref {
    id: number
    nomComplet: string
    email?: string
}

export interface Produit extends AvecPesees {
    id: number
    codeproduit: string | null
    libelle: string
    prix: number
    actif: boolean
    site: SiteBref | null
    createdAt: string | null
}

export interface Fournisseur extends AvecPesees {
    id: number
    codefournisseur: string | null
    nom: string
    prenom: string | null
    nomComplet: string
    contact1: string | null
    contact2: string | null
    reseau: ReseauMobile | null
    prixspeciale: number | null
    statut: Statut
    site: SiteBref | null
    /* Renseigné quand le planteur ne peut pas être payé : numéro absent, réseau inconnu… */
    motifNonPayable: string | null
    createdAt: string | null
}

export interface Site extends AvecPesees {
    id: number
    code: string
    libellesite: string
    localite: string | null
    entreprise: { id: number; nom: string } | null
    operateur: UserBref | null
    solde: number
    statut: StatutSite
    /* Le libellé du statut vient de l'API : les deux écrans ne peuvent pas diverger. */
    statutLibelle: string
    createdAt: string | null
}

export interface Utilisateur {
    id: number
    email: string
    roles: string[]
    nom: string
    prenom: string | null
    nomComplet: string
    telephone: string | null
    statut: Statut
    /* Le compte qui a inscrit l'entreprise : ni suspendable ni supprimable par un pair. */
    fondateur: boolean
    entreprise: { id: number; nom: string; codeentreprise?: string } | null
    sitesGeres: string[]
    /* Libellés calculés par l'API : le dictionnaire des rôles et des statuts n'existe qu'à un seul endroit. */
    roleLibelle: string
    statutLibelle: string
    createdAt: string | null
}

export interface Entreprise {
    id: number
    nom: string
    codeentreprise: string
    adresse: string | null
    contact1: string | null
    contact2: string | null
    email: string | null
    solde: number
    statut: 'ACTIVE' | 'DESACTIVEE'
    /* Compteurs calculés en SQL par l'API : la liste ne reçoit jamais les collections. */
    sitesCount: number
    comptesCount: number
    createdAt: string | null
}

export type StatutPaiement = 'EN_ATTENTE' | 'ENVOYE' | 'REUSSI' | 'ECHOUE' | 'ANNULE'

export interface Paiement {
    id: number
    reference: string
    montant: number
    statut: StatutPaiement
    numeroDestinataire: string | null
    reseau: ReseauMobile | null
    messageErreur: string | null
    confirmeAt: string | null
    createdAt: string
    site: SiteBref | null
    fournisseur: { id: number; nomComplet: string } | null
    operation: { id: number; numticket: string | null } | null
}

export type StatutDemande = 'EN_ATTENTE' | 'APPROUVEE' | 'REJETEE' | 'ANNULEE'

export interface DemandeSolde {
    id: number
    montantDemande: number
    montantAccorde: number | null
    motif: string | null
    motifRejet: string | null
    statut: StatutDemande
    site: SiteBref | null
    demandePar: UserBref | null
    traitePar: UserBref | null
    traiteAt: string | null
    createdAt: string
    /* Le libellé du statut vient de l'API : les deux écrans ne peuvent pas diverger. */
    statutLibelle: string
}

export type SensMouvement = 'CREDIT' | 'DEBIT'

export interface MouvementCaisse {
    id: number
    compte: 'ENTREPRISE' | 'SITE'
    sens: SensMouvement
    type: string
    montant: number
    soldeAvant: number
    soldeApres: number
    motif: string | null
    site: SiteBref | null
    effectuePar: UserBref | null
    createdAt: string
}

/*
    - Un lot d'envoi reçu d'un poste de pesée. 'erreurs' porte le détail de ce qui a été refusé :
      c'est la seule chose qu'on vient chercher dans le journal.
*/
export interface SynchronisationLot {
    id: number
    site: SiteBref | null
    nbRecus: number
    nbCrees: number
    nbModifiees: number
    nbErreurs: number
    erreurs: { id: string | null; message: string }[] | null
    ip: string | null
    dureeMs: number | null
    createdAt: string
}

/*
    - Une ligne du journal des décisions. 'libelle' est une PHRASE composée au moment du geste, et non
      un gabarit rejoué à l'affichage : elle reste lisible même si le produit qu'elle nomme a été
      renommé ou purgé depuis. C'est tout l'intérêt d'un journal.
    - 'cibleType' et 'cibleId' ne sont pas une relation : ils survivent volontairement à la
      disparition de ce qu'ils désignent.
*/
export interface Activite {
    id: number
    type: string
    typeLibelle: string | null
    libelle: string
    cibleType: string | null
    cibleId: number | null
    auteurNom: string | null
    createdAt: string
}

/*
    - Une ligne de la corbeille. Ce n'est pas l'entité supprimée : c'est ce qu'il faut savoir pour
      décider de son sort. Les deux motifs disent POURQUOI un geste est impossible — un bouton grisé
      sans explication n'aide personne.
*/
export interface ElementCorbeille {
    type: string
    typeLibelle: string
    id: number
    libelle: string
    contexte: string | null
    entreprise: string | null
    entrepriseId: number | null
    supprimeLe: string | null
    supprimePar: string | null
    peseesCount: number
    purgeable: boolean
    motifNonPurgeable: string | null
    restaurable: boolean
    motifNonRestaurable: string | null
}
