import * as React from 'react'
import { MoreHorizontal } from 'lucide-react'
import { Badge } from '@/assets/components/ui/badge'
import { Button } from '@/assets/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/assets/components/ui/dropdown-menu'

/*
    - Les briques d'affichage partagées par toutes les tables de gestion : un montant se formate
      partout pareil, un statut porte partout la même couleur.
*/

type Variante = 'default' | 'secondary' | 'destructive' | 'outline'

export const montant = (valeur: number | null | undefined, unite = 'F'): string =>
    valeur === null || valeur === undefined ? '—' : `${valeur.toLocaleString('fr-FR')} ${unite}`

export const texte = (valeur: string | null | undefined): string =>
    valeur === null || valeur === undefined || valeur === '' ? '—' : valeur

export const dateHeure = (valeur: string | null | undefined): string =>
    valeur ? new Date(valeur).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—'

/* Un seul dictionnaire pour tous les états du domaine : ils ne se contredisent jamais d'un écran à l'autre. */
const ETATS: Record<string, { libelle: string; variant: Variante }> = {
    ACTIF: { libelle: 'Actif', variant: 'default' },
    SUSPENDU: { libelle: 'Suspendu', variant: 'destructive' },
    ACTIVE: { libelle: 'Active', variant: 'default' },
    DESACTIVEE: { libelle: 'Désactivée', variant: 'destructive' },
    BLOQUE: { libelle: 'Bloqué', variant: 'destructive' },
    // L'état d'une pesée : le camion est-il ressorti ? Même libellé et même couleur que côté Twig
    EN_COURS: { libelle: 'En cours', variant: 'secondary' },
    TERMINEE: { libelle: 'Terminée', variant: 'default' },
    EN_ATTENTE: { libelle: 'En attente', variant: 'secondary' },
    ENVOYE: { libelle: 'Envoyé', variant: 'secondary' },
    REUSSI: { libelle: 'Réussi', variant: 'default' },
    ECHOUE: { libelle: 'Échoué', variant: 'destructive' },
    ANNULE: { libelle: 'Annulé', variant: 'outline' },
    APPROUVEE: { libelle: 'Approuvée', variant: 'default' },
    REJETEE: { libelle: 'Rejetée', variant: 'destructive' },
    ANNULEE: { libelle: 'Annulée', variant: 'outline' },
    NON_PAYE: { libelle: 'Non payée', variant: 'outline' },
    PARTIEL: { libelle: 'Partielle', variant: 'secondary' },
    SOLDE: { libelle: 'Soldée', variant: 'default' },
}

export function Etat({ statut }: { statut: string | null | undefined }) {
    if (!statut) {
        return <span>—</span>
    }

    const etat = ETATS[statut] ?? { libelle: statut, variant: 'outline' as Variante }

    return <Badge variant={etat.variant}>{etat.libelle}</Badge>
}

/*
    - Les actions de ligne sont de simples liens vers des pages dédiées, pas des fenêtres modales :
      chaque action a besoin de contexte (le solde avant de doter, le motif avant de rejeter), et
      une page se partage, se recharge et fonctionne au clavier sans rien de plus.
*/
export function Lien({ href, children }: { href: string; children: React.ReactNode }) {
    return (
        <a href={href} className="text-sm font-medium underline-offset-4 hover:underline">
            {children}
        </a>
    )
}

/*
    - Le menu d'actions de fin de ligne. Les actions sont DÉCLARÉES, pas composées en JSX comme dans
      Transport, et c'est base-ui qui l'impose : son popup se démonte à la fermeture. Or la
      confirmation maison est asynchrone — elle retient le clic, pose la question, puis rejoue le clic
      APRÈS réponse. Rejoué sur un bouton démonté, il ne soumet rien, en silence.

    - Les formulaires sont donc rendus ici, dans la CELLULE, hors du popup, et l'entrée du menu ne
      fait que cliquer leur bouton — qui, lui, survit à la fermeture.

    - 'render' et non 'asChild' : le kit de ce projet est bâti sur base-ui, celui de Transport sur radix.
*/

/** Une action qui mène à une page. L'URL est composée par le contrôleur, jamais concaténée ici. */
export interface ActionLien {
    libelle: string
    href: string
}

/** Une action qui poste. 'confirmer' déclenche la question de 'modules/confirmation.js'. */
export interface ActionPost {
    libelle: string
    action: string
    confirmer: string
    detail?: string
    libelleAction?: string
    destructive?: boolean
}

export function MenuActions({ liens = [], actions = [], jeton }: { liens?: ActionLien[]; actions?: ActionPost[]; jeton?: string }) {
    const boutons = React.useRef<Record<string, HTMLButtonElement | null>>({})

    if(liens.length === 0 && actions.length === 0) {
        return null // Une ligne sans action n'affiche pas un menu vide
    }

    return (
        <>
            {actions.map(action => (
                <form key={action.action} method="post" action={action.action} className="hidden">
                    {/* '_csrf_token' et non '_token' : c'est le nom que lit 'GestionController::jetonValide', et l'identifiant du jeton est 'gestion' pour tous les formulaires de gestion. */}
                    {/* 'defaultValue' et non 'value' : le contrôleur CSRF sans état remplace le contenu du champ par un aléa au moment du 'submit'. Un champ contrôlé par React reverrouille la propriété derrière lui, et le champ repartirait avec l'ancienne valeur. */}
                    <input type="hidden" name="_csrf_token" data-controller="csrf-protection" defaultValue={jeton} />
                    <button
                        ref={n => { boutons.current[action.action] = n }}
                        type="submit"
                        data-confirmer={action.confirmer}
                        data-confirmer-detail={action.detail}
                        data-confirmer-action={action.libelleAction}
                    />
                </form>
            ))}

            <DropdownMenu>
                <DropdownMenuTrigger render={<Button variant="ghost" className="size-8 p-0" />}>
                    <span className="sr-only">Ouvrir le menu</span>
                    <MoreHorizontal className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {liens.map(lien => (
                        <DropdownMenuItem key={lien.href} render={<a href={lien.href} />}>{lien.libelle}</DropdownMenuItem>
                    ))}

                    {liens.length > 0 && actions.length > 0 && <DropdownMenuSeparator />}

                    {actions.map(action => (
                        <DropdownMenuItem
                            key={action.action}
                            variant={action.destructive ? 'destructive' : 'default'}
                            onClick={() => boutons.current[action.action]?.click()}
                        >
                            {action.libelle}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    )
}
