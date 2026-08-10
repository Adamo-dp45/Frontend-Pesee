import '@tanstack/react-table'

/*
    - TanStack laisse 'meta' vide par nature : c'est à l'application de dire ce qu'elle y range.
      Ici une seule chose, l'alignement à droite des colonnes de nombres.
*/
declare module '@tanstack/react-table' {
    interface ColumnMeta<TData extends unknown, TValue> {
        aDroite?: boolean
    }
}
