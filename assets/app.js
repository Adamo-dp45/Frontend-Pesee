import { registerReactControllerComponents } from '@symfony/ux-react';
import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';
import { initTheme } from './modules/theme.js';
import { initDropdowns } from './modules/dropdown.js';
import { initSidebar } from './modules/sidebar.js';
import { createChart } from './lib/dom.js';
import * as XLSX from 'xlsx-js-style' /*
    - On a remplacé 'import * as XLSX from 'xlsx' pour le style vu que 'xlsx-js-style' est un fork de 'xlsx' avec support du style
*/
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable'

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));

document.addEventListener('turbo:before-render', (event) => {
    if(!document.startViewTransition) { /*
            - L'api 'ViewTransition'
        */
        return
    }
    event.preventDefault()
    document.startViewTransition(() => event.detail.resume())
})

document.addEventListener('turbo:load', () => {
    initTheme()
    initDropdowns()
    initSidebar()
    /* Bilan
     */
    initFiltersToggle()
    initSiteSelect()
    initExportExcel()
    initExportPdf()

    /* Statistiques
     */
    const chartParJourEl = document.getElementById('chartParJour')
    if(chartParJourEl) {
        const data = JSON.parse(chartParJourEl.dataset.values)
        createChart({
            element: chartParJourEl,
            type: 'bar',
            labels: data.map(d => {
                const date = new Date(d.jour)
                return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
            }),
            datasets: [
                {
                    label: 'Poids net (kg)',
                    data: data.map(d => d.total_poidsnet),
                    borderRadius: 4,
                }
            ],
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: v => v.toLocaleString('fr-FR') + ' kg' }
                    },
                    x: { grid: { display: false } }
                }
            }
        })
    }
    /* ----- */
    const chartParProduitEl = document.getElementById('chartParProduit')
    if(chartParProduitEl) {
        const data = JSON.parse(chartParProduitEl.dataset.values)
        createChart({
            element: chartParProduitEl,
            type: 'doughnut',
            labels: data.map(d => d.libelle),
            datasets: [
                {
                    data: data.map(d => d.total_poidsnet),
                    borderWidth: 0,
                    hoverOffset: 6,
                }
            ],
            options: {
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.formattedValue} kg`
                        }
                    }
                }
            }
        })
    }
    /* ----- */
    const chartParSiteEl = document.getElementById('chartParSite')
    if(chartParSiteEl) {
        const data = JSON.parse(chartParSiteEl.dataset.values)
        createChart({
            element: chartParSiteEl,
            type: 'bar',
            labels: data.map(d => d.libellesite ?? d.codesite),
            datasets: [
                {
                    label: 'Poids net (kg)',
                    data: data.map(d => d.total_poidsnet),
                    borderRadius: 4,
                }
            ],
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { callback: v => v.toLocaleString('fr-FR') + ' kg' },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: { grid: { display: false } }
                }
            }
        })
    }
})

/* Bilan
 */
// ═══════════════════════════════════════════════════
// PANNEAU FILTRES
// ═══════════════════════════════════════════════════

function initFiltersToggle() {
    const trigger = document.getElementById('filters-trigger');
    const body    = document.getElementById('filters-body');
    const chevron = document.getElementById('filters-chevron');
    if (!trigger || !body || !chevron) return;

    trigger.addEventListener('click', () => {
        const isOpen = body.classList.toggle('is-open');
        chevron.classList.toggle('is-open', isOpen);
    });
}

// ═══════════════════════════════════════════════════
// SELECT SITE → RÉFÉRENTIELS SÉQUENTIELS
// ═══════════════════════════════════════════════════

function initSiteSelect() {
    const selectSite = document.getElementById('select-site');
    if (!selectSite) return;

    selectSite.addEventListener('change', async () => {
        const code = selectSite.value;
        resetRefSelects();
        if (!code) return;
        await loadAllReferentiels(code);
    });
}

function resetRefSelects() {
    document.querySelectorAll('.filter-field__select--ref').forEach(select => {
        select.innerHTML      = '<option value="">Tous</option>';
        select.disabled       = true;
        select.dataset.loaded = 'false';
    });
}

async function loadAllReferentiels(code) {
    const types = [
        { type: 'mouvement',    key: 'mouvement',       selectId: 'select-mouvement'    },
        { type: 'produit',      key: 'produit',          selectId: 'select-produit'      },
        { type: 'client',       key: 'client',           selectId: 'select-client'       },
        { type: 'fournisseur',  key: 'fournisseur',      selectId: 'select-fournisseur'  },
        { type: 'transporteur', key: 'transporteur',     selectId: 'select-transporteur' },
        { type: 'destination',  key: 'destination',      selectId: 'select-destination'  },
        { type: 'provenance',   key: 'provenance',       selectId: 'select-provenance'   },
        { type: 'vehicule',     key: 'immatriculation',  selectId: 'select-vehicule'     },
    ];

    for (const { type, key, selectId } of types) {
        const select = document.getElementById(selectId);
        if (!select) continue;

        select.innerHTML = '<option value="">Chargement…</option>';
        select.disabled  = true;

        try {
            const res = await fetch(
                `/referentiels/${encodeURIComponent(code)}/${type}`,
                { credentials: 'include' }
            );

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const data = await res.json();

            select.innerHTML = '<option value="">Tous</option>';
            data.forEach(item => {
                const val = item[key] ?? '';
                if (!val) return;
                const opt       = document.createElement('option');
                opt.value       = val;
                opt.textContent = val;
                select.appendChild(opt);
            });

            select.disabled       = false;
            select.dataset.loaded = code;

        } catch (err) {
            console.error(`Erreur chargement ${type}:`, err);
            select.innerHTML = '<option value="">Erreur</option>';
            select.disabled  = false;
        }
    }
}

// ═══════════════════════════════════════════════════
// COLLECTE DONNÉES TABLEAU
// ═══════════════════════════════════════════════════

function collectTableData() {
    const table = document.getElementById('operations-table');
    if (!table) return { headers: [], rows: [] };

    const headers = [...table.querySelectorAll('thead th')]
        .map(th => th.textContent.trim());

    const rows = [...table.querySelectorAll('tbody tr')]
        .filter(tr => !tr.querySelector('.col-empty'))
        .map(tr =>
            [...tr.querySelectorAll('td')].map(td => {
                const clone = td.cloneNode(true);
                clone.querySelectorAll('.cell-sub').forEach(el => el.remove());
                return clone.textContent.trim().replace(/\s+/g, ' ');
            })
        );

    return { headers, rows };
}

// ═══════════════════════════════════════════════════
// EXPORT EXCEL
// ═══════════════════════════════════════════════════

function initExportExcel() {
    const btn = document.getElementById('btn-export-excel');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const { headers, rows } = collectTableData();
        if (!rows.length) {
            alert('Aucune donnée à exporter.');
            return;
        }

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

        headers.forEach((_, colIndex) => { /*
                - On applique un style de gras aux entêtes
            */
            const cellRef = XLSX.utils.encode_cell({ r: 0, c: colIndex });
            if (!ws[cellRef]) return;
            ws[cellRef].s = {
                font: { bold: true },
            }
        })

        ws['!cols'] = headers.map((h, i) => ({
            wch: Math.max(h.length, ...rows.map(r => (r[i] ?? '').length)) + 2,
        }));

        XLSX.utils.book_append_sheet(wb, ws, 'Opérations');
        XLSX.writeFile(wb, `operations_${formatDateFilename()}.xlsx`);
    });
}

// ═══════════════════════════════════════════════════
// EXPORT PDF
// ═══════════════════════════════════════════════════

function initExportPdf() {
    const btn = document.getElementById('btn-export-pdf');
    if (!btn) return;

    btn.addEventListener('click', () => {
        const { headers, rows } = collectTableData();
        if (!rows.length) {
            alert('Aucune donnée à exporter.');
            return;
        }

        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        doc.setFontSize(13);
        doc.setFont('helvetica', 'bold');
        doc.text('Rapport des opérations de pesée', 14, 16);

        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(120);
        doc.text(
            `Généré le ${new Date().toLocaleDateString('fr-FR', {
                day: '2-digit', month: 'long', year: 'numeric',
            })}`,
            14, 22
        );
        doc.setTextColor(0);

        autoTable(doc, {
            head: [headers],
            body: rows,
            startY: 28,
            styles: {
                fontSize: 7.5,
                cellPadding: 2,
            },
            headStyles: {
                fillColor: [69, 55, 41],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 7,
            },
            alternateRowStyles: {
                fillColor: [250, 249, 247],
            },
            columnStyles: {
                8:  { halign: 'right' },
                9:  { halign: 'right' },
                10: { halign: 'right' },
                11: { halign: 'right' },
            },
        });

        doc.output('dataurlnewwindow') /*
            - Pour que le navigateur ouvre le pdf dans un nouvel onglet sinon 'doc.save(`operations_${formatDateFilename()}.pdf`)' pour qu'il le télécharge directement
        */
    });
}

// ═══════════════════════════════════════════════════
// UTILITAIRE
// ═══════════════════════════════════════════════════

function formatDateFilename() {
    return new Date().toISOString().slice(0, 10);
}