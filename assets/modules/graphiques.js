import { createChart } from '../lib/dom.js'

/*
    - Les graphiques du tableau de bord. Les données arrivent en attributs 'data-labels' et
      'data-valeurs' posés par le gabarit : aucun appel réseau côté navigateur, donc aucune
      authentification à rejouer en JS.
*/

/* Palette fixe : les couleurs aléatoires changeaient à chaque rechargement, un même produit
   n'avait jamais deux fois la même couleur. */
const PALETTE = [
    '#1e3a5f', '#2f6f4e', '#b8860b', '#8b4049', '#4a5568',
    '#3182ce', '#38a169', '#d69e2e', '#c05621', '#805ad5',
]

const kg = valeur => `${valeur.toLocaleString('fr-FR')} kg`

export function initGraphiques() {
    tonnageParJour(document.getElementById('chartParJour'))
    tonnageParProduit(document.getElementById('chartParProduit'))
}

function lire(element) {
    return {
        labels: JSON.parse(element.dataset.labels),
        valeurs: JSON.parse(element.dataset.valeurs),
    }
}

function tonnageParJour(element) {
    if (!element) return

    const { labels, valeurs } = lire(element)

    createChart({
        element,
        type: 'bar',
        labels: labels.map(jour => new Date(jour).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })),
        datasets: [{ label: 'Poids net', data: valeurs, color: PALETTE[0], borderRadius: 4 }],
        options: {
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${kg(ctx.parsed.y)}` } },
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: kg } },
                x: { grid: { display: false } },
            },
        },
    })
}

function tonnageParProduit(element) {
    if (!element) return

    const { labels, valeurs } = lire(element)

    createChart({
        element,
        type: 'doughnut',
        labels,
        datasets: [{
            data: valeurs,
            backgroundColor: labels.map((_, i) => PALETTE[i % PALETTE.length]),
            borderWidth: 0,
            hoverOffset: 6,
        }],
        options: {
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } },
                tooltip: { callbacks: { label: ctx => ` ${kg(ctx.parsed)}` } },
            },
        },
    })
}
