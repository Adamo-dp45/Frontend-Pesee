import { visit } from '@hotwired/turbo'

/*
    - Le socle de toutes les tables : tri, filtres et pagination se font SUR LE SERVEUR, jamais en
      mémoire. Une table de trois mille pesées ne se trie pas dans le navigateur, et surtout : trier
      une seule page donnerait un classement faux, calculé sur vingt-cinq lignes au lieu de tout.

    - L'état vit dans l'URL. Un tri ou un filtre appliqué se partage par copier-coller, se met en
      favori, et le bouton retour du navigateur fonctionne — ce qu'aucun état React local ne donne.
      C'est le contrôleur Symfony qui traduit ces paramètres pour l'API, après sa liste blanche.
*/

export interface ServerMeta {
    total: number
    page: number
    perPage: number
    totalPages: number
    from: number
    to: number
}

/*
    - Ce que 'TableQueryBuilder::sortUrl()' renvoie pour chaque champ triable, indexé par champ. Le
      composant ne devine aucune URL de tri : il les REÇOIT. C'est ce qui garantit qu'un tri refusé
      par la liste blanche du contrôleur n'apparaît jamais comme cliquable.
*/
export type SortMeta = Record<string, { params: Record<string, string>; active: boolean; dir: string | null }>

export interface ServerTableFilter {
    type: 'text' | 'select' | 'date_range'
    name: string
    label: string
    placeholder?: string
    options?: { label: string; value: string }[]
}

export function useServerTable(queryParams: Record<string, string>) {
    const buildUrl = (overrides: Record<string, string | null>) => {
        const url = new URL(window.location.href)

        Object.entries({ ...queryParams, ...overrides }).forEach(([cle, valeur]) => {
            valeur !== null && valeur !== '' ? url.searchParams.set(cle, valeur) : url.searchParams.delete(cle)
        })

        return url.toString()
    }

    // Naviguer = changer l'URL, pas l'état local : le serveur renvoie la page demandée
    const navigate = (overrides: Record<string, string | null>) => visit(buildUrl(overrides))

    const getSortState = (field: string): 'asc' | 'desc' | false =>
        queryParams.sort === field ? ((queryParams.dir ?? 'asc') as 'asc' | 'desc') : false

    /* Un nouveau tri renvoie au début, sinon on atterrit au milieu d'un classement changé. */
    const getSortToggleUrl = (field: string): string =>
        buildUrl({ sort: field, dir: getSortState(field) === 'asc' ? 'desc' : 'asc', page: '1' })

    return { navigate, buildUrl, getSortState, getSortToggleUrl }
}
