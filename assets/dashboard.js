(() => {
  const payload = window.dashboardChartData || {};
  const canvas = document.getElementById('destinationChart');

  if (!canvas || typeof Chart === 'undefined') return;

  Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
  Chart.defaults.color = '#70838f';

  const rawValues = (payload.values || []).map((value) => Number(value || 0));
  const hasValues = rawValues.some((value) => value > 0);
  const values = hasValues ? rawValues : [1, 0, 0, 0];

  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: payload.labels || [],
      datasets: [{
        data: values,
        backgroundColor: hasValues
          ? ['#1d8e76', '#4e7fc4', '#c49b45', '#8b63c5']
          : ['#dce4e8', 'transparent', 'transparent', 'transparent'],
        borderWidth: 0,
        hoverOffset: hasValues ? 4 : 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      rotation: -90,
      plugins: {
        legend: { display: false },
        tooltip: {
          enabled: hasValues,
          backgroundColor: '#12323a',
          titleColor: '#ffffff',
          bodyColor: '#eaf2f3',
          padding: 11,
          cornerRadius: 8,
          displayColors: true,
          callbacks: {
            label(context) {
              const value = Number(context.raw || 0);
              return context.label + ': ' + new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
              }).format(value);
            }
          }
        }
      }
    }
  });
})();