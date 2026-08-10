import * as React from 'react'
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react'
import { cn } from '@/assets/lib/utils'
import { SortMeta } from '../../hooks/useServerTable'

/*
    - L'en-tête d'une colonne triable. Un LIEN et non un bouton : le tri vit dans l'URL, donc il doit
      se partager, s'ouvrir dans un onglet et répondre au bouton retour.

    - Transport y ajoute un menu Asc / Desc / Masquer. Ici l'en-tête bascule simplement le sens : le
      kit shadcn de ce projet est bâti sur base-ui, dont les menus attendent 'render' et non
      'asChild'. Un lien suffit à ce qu'on en fait.

    - Sans 'sortUrl', la colonne n'est pas triable : c'est le contrôleur qui le décide par sa liste
      blanche, jamais ce composant.
*/

interface Props {
    title: string
    className?: string
    sortUrl?: string
    sortState?: 'asc' | 'desc' | false
}

export function ServerDataTableColumnHeader({ title, className, sortUrl, sortState }: Props) {
    if(!sortUrl) {
        return <div className={cn('font-bold', className)}>{title}</div>
    }

    return (
        <a
            href={sortUrl}
            className={cn('inline-flex items-center gap-1 font-bold hover:text-foreground', className)}
            title={`Trier par ${title}`}
        >
            {title}
            {sortState === 'desc'
                ? <ArrowDown className="size-3.5" />
                : sortState === 'asc'
                    ? <ArrowUp className="size-3.5" />
                    : <ChevronsUpDown className="size-3.5 opacity-40" />}
        </a>
    )
}

/*
    - Le raccourci que chaque table utilise pour ses en-têtes : 'const entete = entetesDepuis(sortMeta)'
      puis 'header: () => entete('prix', 'Prix au kg', true)'. Un champ absent de 'sortMeta' rend un
      en-tête simple, non cliquable — c'est la liste blanche du contrôleur qui décide.
*/
export function entetesDepuis(sortMeta: SortMeta) {
    return (champ: string, titre: string, aDroite = false) => {
        const meta = sortMeta[champ]

        return (
            <ServerDataTableColumnHeader
                title={titre}
                sortUrl={meta && '?' + new URLSearchParams(meta.params).toString()}
                sortState={meta?.active ? (meta.dir as 'asc' | 'desc') : false}
                className={aDroite ? 'justify-end' : undefined}
            />
        )
    }
}
