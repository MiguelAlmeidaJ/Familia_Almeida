(() => {
  const payload = window.dashboardChartData || {};
  if (typeof Chart === 'undefined') {
    document.querySelectorAll('.chart-shell').forEach((el) => el.classList.add('chart-unavailable'));
    return;
  }

  Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif';
  Chart.defaults.color = '#74889a';
  Chart.defaults.borderColor = 'rgba(126, 147, 164, .14)';

  const currency = (value) =>
    new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0
    }).format(Number(value || 0));

  const grid = {
    color: 'rgba(126, 147, 164, .12)',
    drawBorder: false
  };

  const ticks = {
    color: '#8a9aa8',
    font: { size: 10 },
    padding: 8
  };

  const tooltip = {
    backgroundColor: '#0b2033',
    titleColor: '#ffffff',
    bodyColor: '#dbe6ed',
    padding: 12,
    cornerRadius: 10,
    displayColors: true,
    callbacks: {
      label(context) {
        const label = context.dataset.label ? context.dataset.label + ': ' : '';
        return label + currency(context.parsed.y ?? context.parsed);
      }
    }
  };

  const dailyCanvas = document.getElementById('cashflowChart');
  if (dailyCanvas) {
    const daily = payload.daily || {};
    const dailyValues = [...(daily.income || []), ...(daily.outflow || []), ...(daily.investment || [])];
    const hasDailyValues = dailyValues.some((value) => Number(value) > 0);
    if (!hasDailyValues) {
      dailyCanvas.closest('.chart-shell')?.classList.add('chart-empty');
    } else new Chart(dailyCanvas, {
      type: 'line',
      data: {
        labels: daily.labels || [],
        datasets: [
          {
            label: 'Entradas',
            data: daily.income || [],
            borderColor: '#2f8d73',
            backgroundColor: 'rgba(47,141,115,.10)',
            fill: true,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: .35
          },
          {
            label: 'Saídas',
            data: daily.outflow || [],
            borderColor: '#c46a5c',
            backgroundColor: 'rgba(196,106,92,.06)',
            fill: false,
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: .35
          },
          {
            label: 'Investimentos',
            data: daily.investment || [],
            borderColor: '#c3a05b',
            backgroundColor: 'rgba(195,160,91,.06)',
            fill: false,
            borderWidth: 2,
            borderDash: [5, 5],
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: .35
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: { usePointStyle: true, boxWidth: 7, boxHeight: 7, padding: 18, font: { size: 10 } }
          },
          tooltip
        },
        scales: {
          x: { grid: { display: false }, ticks },
          y: {
            beginAtZero: true,
            grid,
            ticks: { ...ticks, callback: (value) => currency(value) }
          }
        }
      }
    });
  }

  const categoryCanvas = document.getElementById('categoryChart');
  if (categoryCanvas) {
    const categories = payload.categories || {};
    const values = categories.values || [];
    const hasValues = values.some((value) => Number(value) > 0);

    if (!hasValues) {
      categoryCanvas.closest('.chart-shell')?.classList.add('chart-empty');
    } else {
      new Chart(categoryCanvas, {
        type: 'doughnut',
        data: {
          labels: categories.labels || [],
          datasets: [{
            data: values,
            backgroundColor: ['#0e2a43', '#2f8d73', '#c3a05b', '#7691a6', '#a76b5e', '#5d6f82', '#b8c4cd'],
            borderWidth: 0,
            hoverOffset: 5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { usePointStyle: true, boxWidth: 7, boxHeight: 7, padding: 14, font: { size: 9 } }
            },
            tooltip: {
              ...tooltip,
              callbacks: {
                label(context) {
                  return context.label + ': ' + currency(context.parsed);
                }
              }
            }
          }
        }
      });
    }
  }

  const historyCanvas = document.getElementById('historyChart');
  if (historyCanvas) {
    const months = payload.months || {};
    new Chart(historyCanvas, {
      data: {
        labels: months.labels || [],
        datasets: [
          {
            type: 'bar',
            label: 'Entradas',
            data: months.income || [],
            backgroundColor: 'rgba(47,141,115,.72)',
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 28
          },
          {
            type: 'bar',
            label: 'Saídas',
            data: months.outflow || [],
            backgroundColor: 'rgba(196,106,92,.58)',
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 28
          },
          {
            type: 'line',
            label: 'Saldo',
            data: months.balance || [],
            borderColor: '#0e2a43',
            backgroundColor: '#0e2a43',
            borderWidth: 2,
            pointRadius: 3,
            pointBackgroundColor: '#ffffff',
            pointBorderWidth: 2,
            tension: .3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'top',
            align: 'end',
            labels: { usePointStyle: true, boxWidth: 7, boxHeight: 7, padding: 18, font: { size: 10 } }
          },
          tooltip
        },
        scales: {
          x: { stacked: false, grid: { display: false }, ticks },
          y: {
            beginAtZero: true,
            grid,
            ticks: { ...ticks, callback: (value) => currency(value) }
          }
        }
      }
    });
  }
})();