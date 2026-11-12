/* Les ressources de gestion, telles que l'API les renvoie. */

export type Statut = 'ACTIF' | 'SUSPENDU'
export type StatutSite = 'ACTIF' | 'BLOQUE'
export type ReseauMobile = 'ORANGE' | 'MTN' | 'MOOV' | 'WAVE'

/* Les deux natures d'argent : les espèces du tiroir et le flotte mobile money. Elles ne se
   convertissent pas l'une en l'autre, et chacune tient sa propre chaîne de grand livre. */
export type TypeCaisse = 'PHYSIQUE' | 'NUMERIQUE'

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
    prixtransport: number | null
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
    /* Les deux caisses du poste. 'solde' en est le cumul : il se lit, il ne se dépense pas — un
       versement va chercher celle de son mode de règlement. */
    soldePhysique: number
    soldeNumerique: number
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
    soldePhysique: number
    soldeNumerique: number
    solde: number
    statut: 'ACTIVE' | 'DESACTIVEE'
    /* Compteurs calculés en SQL par l'API : la liste ne reçoit jamais les collections. */
    sitesCount: number
    comptesCount: number
    createdAt: string | null
}

export type StatutPaiement = 'EN_ATTENTE' | 'ENVOYE' | 'REUSSI' | 'ECHOUE' | 'ANNULE'

/* Espèces ou mobile money. Le mode décide de la caisse qui se vide, de la nécessité d'un numéro,
   et du moment où le versement est confirmé : les espèces le sont d'emblée. */
export type ModeReglement = 'ESPECES' | 'MOBILE_MONEY'

export interface Paiement {
    id: number
    reference: string
    montant: number
    statut: StatutPaiement
    modeReglement: ModeReglement
    modeReglementLibelle: string
    /* Nuls pour un règlement en espèces : des billets ne vont à aucun numéro. */
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

/*
    - La demande de mise en compte. Pas de « taux accordé » en face du taux demandé : contrairement
      à une demande de solde, l'administrateur ouvre au taux réclamé ou refuse — le chiffre vient du
      planteur, pas de lui.
*/
export interface DemandePrelevement {
    id: number
    /* En francs PAR KILO : sur une pesée de 500 kg, un taux de 20 met 10 000 F de côté. */
    tauxDemande: number
    /* Le produit sur lequel porte le prélèvement. C'est son prix au kilo qui borne le taux : un
       planteur peut avoir plusieurs prélèvements, un par culture, à des taux différents. */
    produit: { id: number; libelle: string; prix: number } | null
    motif: string | null
    motifRejet: string | null
    statut: StatutDemande
    fournisseur: { id: number; nomComplet: string; soldeEpargne: number } | null
    site: SiteBref | null
    demandePar: UserBref | null
    traitePar: UserBref | null
    traiteAt: string | null
    createdAt: string
    /* Le libellé du statut vient de l'API : les deux écrans ne peuvent pas diverger. */
    statutLibelle: string
}

export interface DemandeSolde {
    id: number
    montantDemande: number
    montantAccorde: number | null
    motif: string | null
    motifRejet: string | null
    /* La caisse réclamée : être à court de billets pendant que le flotte déborde est courant. */
    caisse: TypeCaisse
    caisseLibelle: string
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
    /* 'compte' dit de qui est la caisse, 'caisse' laquelle de ses deux : les deux ensemble
       désignent un solde, et un seul. Sans quoi deux chaînes entrelacées paraissent rompues. */
    caisse: TypeCaisse
    caisseLibelle: string
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
