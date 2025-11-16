// Script minimal pour corriger les graphiques agents
console.log('🔧 Script de correction des graphiques agents chargé');

// Fonction pour créer les graphiques manuellement si les fonctions d'origine ne marchent pas
window.forceInitAgentCharts = function() {
    console.log('🔄 Force initialisation des graphiques agent...');
    
    // Vérifier que Chart.js est disponible
    if (typeof Chart === 'undefined') {
        console.error('❌ Chart.js non disponible');
        return false;
    }
    
    // Chercher les éléments canvas
    const canvas1 = document.getElementById('agentFormationsChart');
    const canvas2 = document.getElementById('agentTypesChart');
    const tableBody = document.getElementById('agentRealizationTable');
    
    console.log('🔍 Éléments trouvés:', {
        canvas1: !!canvas1,
        canvas2: !!canvas2,
        table: !!tableBody
    });
    
    if (!canvas1 || !canvas2) {
        console.error('❌ Canvas non trouvés');
        return false;
    }
    
    // Essayer d'appeler les fonctions d'initialisation existantes d'abord
    if (typeof initAgentCharts === 'function') {
        console.log('📊 Appel de initAgentCharts');
        initAgentCharts();
        return true;
    } else if (typeof initChartsWhenReady === 'function') {
        console.log('📊 Appel de initChartsWhenReady');
        initChartsWhenReady();
        return true;
    }
    
    console.log('⚠️ Fonctions d\'initialisation non trouvées, création manuelle...');
    
    // Créer les graphiques manuellement en extrayant les données du DOM
    return createChartsFromDOM();
};

// Fonction pour extraire les données du DOM et créer les graphiques
function createChartsFromDOM() {
    console.log('🔧 Création manuelle des graphiques depuis le DOM...');
    
    try {
        // Chercher les données dans les scripts de la modal
        const modalBody = document.getElementById('agentModalBody');
        if (!modalBody) {
            console.error('❌ Modal body non trouvé');
            return false;
        }
        
        // Extraire les données depuis les variables JavaScript dans le contenu
        const scripts = modalBody.querySelectorAll('script');
        let agentData = null;
        let typesData = null;
        
        for (let script of scripts) {
            const content = script.textContent;
            
            // Chercher agentData
            const agentDataMatch = content.match(/const agentData = ({[^}]+});/);
            if (agentDataMatch) {
                try {
                    agentData = eval('(' + agentDataMatch[1] + ')');
                    console.log('✅ agentData trouvé:', agentData);
                } catch (e) {
                    console.error('❌ Erreur parsing agentData:', e);
                }
            }
            
            // Chercher typesData
            const typesDataMatch = content.match(/const typesData = ({[\s\S]*?});/);
            if (typesDataMatch) {
                try {
                    typesData = eval('(' + typesDataMatch[1] + ')');
                    console.log('✅ typesData trouvé:', typesData);
                } catch (e) {
                    console.error('❌ Erreur parsing typesData:', e);
                }
            }
        }
        
        if (!agentData || !typesData) {
            console.error('❌ Données non trouvées dans les scripts');
            return false;
        }
        
        // Créer les graphiques avec les données extraites
        return buildChartsWithData(agentData, typesData);
        
    } catch (error) {
        console.error('❌ Erreur création manuelle:', error);
        return false;
    }
}

// Fonction pour construire les graphiques avec les données fournies
function buildChartsWithData(agentData, typesData) {
    console.log('🏗️ Construction des graphiques avec les données...');
    
    const canvas1 = document.getElementById('agentFormationsChart');
    const canvas2 = document.getElementById('agentTypesChart');
    
    if (!canvas1 || !canvas2) {
        console.error('❌ Canvas non trouvés');
        return false;
    }
    
    try {
        // Détruire les graphiques existants
        const existingChart1 = Chart.getChart(canvas1);
        const existingChart2 = Chart.getChart(canvas2);
        if (existingChart1) existingChart1.destroy();
        if (existingChart2) existingChart2.destroy();
        
        // Graphique 1: Répartition globale
        const chart1 = new Chart(canvas1, {
            type: 'doughnut',
            data: {
                labels: ['Effectuées', 'Non Effectuées', 'À Renouveler', 'Planifiées'],
                datasets: [{
                    data: [
                        agentData.effectuees || 0,
                        agentData.non_effectuees || 0,
                        agentData.a_renouveler || 0,
                        agentData.planifiees || 0
                    ],
                    backgroundColor: ['#198754', '#dc3545', '#ffc107', '#0dcaf0'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom',
                        labels: {
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return {
                                            text: `${label}: ${value} (${percentage}%)`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor,
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${value} formations (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Graphique 2: Par type de formation
        const chart2 = new Chart(canvas2, {
            type: 'bar',
            data: {
                labels: ['Familiarisation', 'Formation Initiale', 'Cours d\'Emploi', 'Technique'],
                datasets: [
                    {
                        label: 'Effectuées',
                        data: [
                            typesData.effectuees?.familiarisation || 0,
                            typesData.effectuees?.initiale || 0,
                            typesData.effectuees?.cours_emploi || 0,
                            typesData.effectuees?.technique || 0
                        ],
                        backgroundColor: '#198754'
                    },
                    {
                        label: 'Non Effectuées',
                        data: [
                            typesData.non_effectuees?.familiarisation || 0,
                            typesData.non_effectuees?.initiale || 0,
                            typesData.non_effectuees?.cours_emploi || 0,
                            typesData.non_effectuees?.technique || 0
                        ],
                        backgroundColor: '#dc3545'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.parsed.y;
                                return `${label}: ${value} formations`;
                            },
                            afterLabel: function(context) {
                                // Calculer le pourcentage pour ce type de formation
                                const datasetIndex = context.datasetIndex;
                                const dataIndex = context.dataIndex;
                                const effectuees = chart2.data.datasets[0].data[dataIndex];
                                const nonEffectuees = chart2.data.datasets[1].data[dataIndex];
                                const total = effectuees + nonEffectuees;
                                
                                if (total > 0) {
                                    const percentage = ((context.parsed.y / total) * 100).toFixed(1);
                                    return `${percentage}% du total (${total})`;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1 }
                    }
                },
                // Afficher les valeurs sur les barres
                onHover: (event, activeElements) => {
                    event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                }
            },
            plugins: [{
                id: 'barValues',
                afterDatasetsDraw: function(chart) {
                    const ctx = chart.ctx;
                    ctx.font = '10px Arial';
                    ctx.fillStyle = '#000';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';

                    chart.data.datasets.forEach((dataset, datasetIndex) => {
                        const meta = chart.getDatasetMeta(datasetIndex);
                        meta.data.forEach((bar, index) => {
                            const value = dataset.data[index];
                            if (value > 0) {
                                ctx.fillText(value, bar.x, bar.y - 5);
                            }
                        });
                    });
                }
            }]
        });
        
        // Créer aussi le tableau détaillé par type
        createRealizationTable(typesData);
        
        console.log('✅ Graphiques créés manuellement avec succès !');
        console.log('📊 Graphique 1 données:', chart1.data);
        console.log('📊 Graphique 2 données:', chart2.data);
        
        return true;
        
    } catch (error) {
        console.error('❌ Erreur construction graphiques:', error);
        return false;
    }
}

// Fonction pour créer le tableau de réalisation par type
function createRealizationTable(typesData) {
    console.log('📋 Création du tableau de réalisation par type...');
    
    const tableBody = document.getElementById('agentRealizationTable');
    if (!tableBody) {
        console.error('❌ Tableau agentRealizationTable non trouvé');
        return false;
    }
    
    try {
        // Définir les types de formation avec leurs labels
        const formationTypes = [
            { key: 'familiarisation', label: 'Familiarisation', shortLabel: 'Familiarisation' },
            { key: 'initiale', label: 'Formation Initiale', shortLabel: 'F. Initiale' },
            { key: 'cours_emploi', label: 'Formation Cours d\'Emploi', shortLabel: 'C. Emploi' },
            { key: 'technique', label: 'Formation Technique', shortLabel: 'F. Technique' }
        ];
        
        let tableHTML = '';
        
        formationTypes.forEach(type => {
            const effectuees = typesData.effectuees?.[type.key] || 0;
            const nonEffectuees = typesData.non_effectuees?.[type.key] || 0;
            const total = effectuees + nonEffectuees;
            const taux = total > 0 ? ((effectuees / total) * 100) : 0;
            
            // Déterminer la couleur de la barre de progression
            let progressClass = 'bg-danger';
            if (taux >= 80) progressClass = 'bg-success';
            else if (taux >= 50) progressClass = 'bg-warning';
            
            tableHTML += `
                <tr>
                    <td style="font-size: 10px;"><strong>${type.shortLabel}</strong></td>
                    <td class="text-center">
                        <span class="badge bg-success" style="font-size: 9px;">${effectuees}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-danger" style="font-size: 9px;">${nonEffectuees}</span>
                    </td>
                    <td style="width: 100px;">
                        <div class="progress" style="height: 15px; font-size: 9px;">
                            <div class="progress-bar ${progressClass}" 
                                 style="width: ${taux}%" 
                                 title="${type.label}: ${taux.toFixed(1)}%">
                                ${taux.toFixed(1)}%
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tableBody.innerHTML = tableHTML;
        console.log('✅ Tableau de réalisation créé avec succès');
        
        return true;
        
    } catch (error) {
        console.error('❌ Erreur création tableau:', error);
        return false;
    }
}

console.log('✅ Script de correction des graphiques agents prêt');
