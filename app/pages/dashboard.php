<?php
// app/pages/dashboard.php

$period = $_GET['period'] ?? 'month'; // week|month|today
$month  = $_GET['m'] ?? date('n');
$year   = $_GET['y'] ?? date('Y');

$month = max(1, min(12, (int)$month));
$year  = (int)$year;

// ✅ garante period válido
$period = in_array($period, ['week','month','today'], true) ? $period : 'month';

// ✅ mês vigente (do servidor)
$curM = (int)date('n');
$curY = (int)date('Y');
$isCurrentMonth = ($month === $curM && $year === $curY);

// ✅ REGRA: fora do mês vigente, SEMPRE força month (evita bug de semana/hoje “vazar”)
if (!$isCurrentMonth) {
  $period = 'month';
}

$months = [
  1 => 'Janeiro',
  2 => 'Fevereiro',
  3 => 'Março',
  4 => 'Abril',
  5 => 'Maio',
  6 => 'Junho',
  7 => 'Julho',
  8 => 'Agosto',
  9 => 'Setembro',
  10 => 'Outubro',
  11 => 'Novembro',
  12 => 'Dezembro'
];

$base = app_config('base_url', '');

$prevM = $month - 1;
$prevY = $year;
$nextM = $month + 1;
$nextY = $year;
if ($prevM < 1) {
  $prevM = 12;
  $prevY--;
}
if ($nextM > 12) {
  $nextM = 1;
  $nextY++;
}

// ✅ links Prev/Next: se o alvo NÃO for mês vigente, manda period=month (reseta semana/hoje)
$prevIsCurrent = ($prevM === $curM && $prevY === $curY);
$nextIsCurrent = ($nextM === $curM && $nextY === $curY);

// Se quiser, ao voltar pro mês vigente você pode manter o period atual.
// Mas pra evitar qualquer “memória chata”, a regra abaixo mantém o que o usuário escolheu
// SOMENTE se o alvo for mês vigente; caso contrário, força month.
$prevPeriod = $prevIsCurrent ? $period : 'month';
$nextPeriod = $nextIsCurrent ? $period : 'month';
?>
<div class="page">
  <div class="page-head">
    <div class="period-nav">
      <a class="icon-btn"
        aria-label="Mês anterior"
        href="<?= h($base) ?>/app.php?page=dashboard&m=<?= $prevM ?>&y=<?= $prevY ?>&period=<?= h($prevPeriod) ?>">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      </a>

      <div class="period-title"><?= h($months[$month]) ?></div>

      <a class="icon-btn"
        aria-label="Próximo mês"
        href="<?= h($base) ?>/app.php?page=dashboard&m=<?= $nextM ?>&y=<?= $nextY ?>&period=<?= h($nextPeriod) ?>">
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
      </a>
    </div>

    <div id="periodFilters" class="segmented">
      <a class="<?= $period === 'week' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=dashboard&m=<?= $month ?>&y=<?= $year ?>&period=week">Semana</a>
      <a class="<?= $period === 'month' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=dashboard&m=<?= $month ?>&y=<?= $year ?>&period=month">Mês</a>
      <a class="<?= $period === 'today' ? 'active' : '' ?>" href="<?= h($base) ?>/app.php?page=dashboard&m=<?= $month ?>&y=<?= $year ?>&period=today">Hoje</a>
    </div>
  </div>

  <div class="grid-2">
    <section class="card card-pad">
      <div class="card-kpi-top">
        <div>
          <div class="muted">Resultado do Período</div>
          <div class="kpi">R$ <span id="kpiResultado">0,00</span></div>
          <div class="small muted" id="kpiRange">--</div>
        </div>
        <div id="kpiDelta" class="kpi-delta" style="display:none;"></div>
      </div>

      <div class="mini-line">
        <canvas id="miniLine"></canvas>
      </div>
    </section>

    <section class="card card-pad">
      <div class="card-title-row">
        <div class="card-title">Evolução do Saldo no Período</div>
        <div class="small muted"><?= h($period === 'today' ? 'Hoje' : ($period === 'week' ? 'Semana' : 'Mês')) ?></div>
      </div>
      <div class="chart-line">
        <canvas id="saldoLine"></canvas>
      </div>
    </section>
  </div>

  <div class="grid-3">
    <section class="card card-pad">
      <div class="card-title-row">
        <div class="card-title">Entradas</div>
        <span class="pill pill-green">Receitas</span>
      </div>
      <div class="kpi green">R$ <span id="kpiEntradas">0,00</span></div>
      <div class="split">
        <div class="small muted">Realizado</div>
        <div class="small green">R$ <span id="kpiEntradasReal">0,00</span></div>
      </div>
      <div class="split">
        <div class="small muted">Previsto (a receber)</div>
        <div class="small muted">R$ <span id="kpiEntradasPrev">0,00</span></div>
      </div>
    </section>

    <section class="card card-pad">
      <div class="card-title-row">
        <div class="card-title">Saídas</div>
        <span class="pill pill-red">Despesas</span>
      </div>
      <div class="kpi red">R$ <span id="kpiSaidas">0,00</span></div>
      <div class="split">
        <div class="small muted">Realizado</div>
        <div class="small red">R$ <span id="kpiSaidasReal">0,00</span></div>
      </div>
      <div class="split">
        <div class="small muted">Previsto (a pagar)</div>
        <div class="small muted">R$ <span id="kpiSaidasPrev">0,00</span></div>
      </div>
    </section>

    <section class="card card-pad">
      <div class="card-title-row">
        <div class="card-title">Despesas</div>
        <div class="tabs">
          <button class="tab active" id="tabPago" type="button">Pagos</button>
          <button class="tab" id="tabApagar" type="button">A Pagar</button>
        </div>
      </div>

      <div class="donut-wrap">
        <div class="donut-canvas">
          <canvas id="donut"></canvas>
        </div>
        <div class="donut-legend" id="donutLegend"></div>
      </div>
    </section>
  </div>
</div>

<script>
  (() => {
    const BASE = window.location.origin;
    const PERIOD = <?= json_encode($period) ?>;
    const M = <?= (int)$month ?>;
    const Y = <?= (int)$year ?>;

    // =========================================================
    //  VISIBILIDADE DOS FILTROS (desktop mantém espaço; mobile remove)
    //  Regra: filtros só aparecem no mês vigente.
    // =========================================================
    (function applyFiltersVisibility() {
      const now = new Date();
      const isCurrentMonth = (Y === now.getFullYear() && M === (now.getMonth() + 1));

      // ajuste o breakpoint se quiser (768/900/1024 etc.)
      const isMobile = window.matchMedia("(max-width: 768px)").matches;

      const filterEls = document.querySelectorAll(".segmented, .tabs");

      filterEls.forEach((el) => {
        if (isCurrentMonth) {
          // volta ao normal
          el.style.display = "";
          el.style.visibility = "";
          el.style.pointerEvents = "";
          el.style.height = "";
          el.style.margin = "";
          el.style.opacity = "";
        } else {
          if (isMobile) {
            // mobile: remove do layout (como estava)
            el.style.display = "none";
          } else {
            // desktop: some sem mexer no layout (mantém o espaço)
            el.style.display = ""; // garante que ocupa espaço
            el.style.visibility = "hidden"; // some visualmente
            el.style.pointerEvents = "none"; // não clica
            el.style.opacity = "0"; // reforço (evita “fantasma”)
          }
        }
      });
    })();

    const cacheKey = `dash:${PERIOD}:${M}:${Y}`;

    // ===== Helpers =====
    function brMoney(n) {
      const v = Number(n || 0);
      return v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function setText(id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    }

    function fmtRange(start, end) {
      const s = String(start || '').split('-');
      const e = String(end || '').split('-');
      if (s.length !== 3 || e.length !== 3) return '--';
      return `${s[2]}/${s[1]}/${s[0]} até ${e[2]}/${e[1]}/${e[0]}`;
    }

    // ===== Donut / Legend =====
    const donutEl = document.getElementById('donut');
    const legendEl = document.getElementById('donutLegend');
    const tabPago = document.getElementById('tabPago');
    const tabApagar = document.getElementById('tabApagar');

    let dataPago = []; // SEM fake
    let dataApagar = []; // SEM fake

    function renderLegend(items) {
      const arr = items || [];
      legendEl.innerHTML = arr.map(i => `
    <div class="legend-row">
      <span class="dot" style="background:${i.color || '#6b7280'}"></span>
      <span class="legend-name">${i.label}</span>
      <span class="legend-val">R$ ${brMoney(i.value)}</span>
    </div>
  `).join('');
    }

    function setActive(a, b) {
      a.classList.add('active');
      b.classList.remove('active');
    }

    // Donut chart inicia vazio (sem flash fake)
    const donutChart = new Chart(donutEl, {
      type: 'doughnut',
      data: {
        labels: [],
        datasets: [{
          data: [],
          borderWidth: 0,
          cutout: '70%'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });

    function applyDonut(items) {
      const arr = items || [];
      donutChart.data.labels = arr.map(x => x.label);
      donutChart.data.datasets[0].data = arr.map(x => Number(x.value || 0));
      donutChart.data.datasets[0].backgroundColor = arr.map(x => x.color || '#6b7280');
      donutChart.update();
      renderLegend(arr);
    }

    tabPago.addEventListener('click', () => {
      setActive(tabPago, tabApagar);
      applyDonut(dataPago);
    });

    tabApagar.addEventListener('click', () => {
      setActive(tabApagar, tabPago);
      applyDonut(dataApagar);
    });

    // ===== Charts (inicia vazio, sem fake) =====
    const miniChart = new Chart(document.getElementById('miniLine'), {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          data: [],
          tension: .4,
          borderWidth: 2,
          pointRadius: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            enabled: false
          }
        },
        scales: {
          x: {
            display: false
          },
          y: {
            display: false
          }
        }
      }
    });

    const saldoChart = new Chart(document.getElementById('saldoLine'), {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          label: 'Saldo',
          data: [],
          tension: .35,
          borderWidth: 2,
          pointRadius: 0,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true
          }
        },
        interaction: {
          mode: 'index',
          intersect: false
        },
        scales: {
          x: {
            grid: {
              display: false
            }
          },
          y: {
            grid: {
              color: 'rgba(0,0,0,.06)'
            }
          }
        }
      }
    });

    // ===== Aplica payload na tela =====
    function applyPayload(payload) {
      if (!payload || !payload.ok) return;

      setText("kpiResultado", brMoney(payload.kpis?.resultado));
      setText("kpiEntradas", brMoney(payload.kpis?.entradas?.realizado));
      setText("kpiEntradasReal", brMoney(payload.kpis?.entradas?.realizado));
      setText("kpiEntradasPrev", brMoney(payload.kpis?.entradas?.previsto));

      setText("kpiSaidas", brMoney(payload.kpis?.saidas?.realizado));
      setText("kpiSaidasReal", brMoney(payload.kpis?.saidas?.realizado));
      setText("kpiSaidasPrev", brMoney(payload.kpis?.saidas?.previsto));

      setText("kpiRange", fmtRange(payload.range?.start, payload.range?.end));

      const mini = payload.charts?.mini;
      if (mini && Array.isArray(mini.labels) && Array.isArray(mini.data)) {
        miniChart.data.labels = mini.labels;
        miniChart.data.datasets[0].data = mini.data;
        miniChart.update();
      }

      const saldo = payload.charts?.saldo;
      if (saldo && Array.isArray(saldo.labels) && Array.isArray(saldo.data)) {
        saldoChart.data.labels = saldo.labels;
        saldoChart.data.datasets[0].data = saldo.data;
        saldoChart.update();
      }

      const donut = payload.charts?.donut;
      dataPago = Array.isArray(donut?.pago) ? donut.pago : [];
      dataApagar = Array.isArray(donut?.apagar) ? donut.apagar : [];

      const showingPago = tabPago.classList.contains("active");
      applyDonut(showingPago ? dataPago : dataApagar);

      // ===== Delta real (comparando com período anterior) =====
      const elDelta = document.getElementById("kpiDelta");
      const v = payload.kpis?.delta_pct;

      if (elDelta) {
        if (v === null || v === undefined || Number.isNaN(Number(v))) {
          elDelta.classList.remove("up", "down");
          elDelta.textContent = "—";
          elDelta.style.display = "";
        } else {
          const num = Number(v);
          const down = num < 0;
          const abs = Math.abs(num).toFixed(1).replace(".", ",");

          elDelta.classList.toggle("down", down);
          elDelta.classList.toggle("up", !down);
          elDelta.textContent = `${down ? "↓" : "↑"} ${abs}%`;
          elDelta.style.display = "";
        }
      }
    }

    // 1) Mostra IMEDIATO o último real (cache), igual transactions/categories
    try {
      const cached = sessionStorage.getItem(cacheKey);
      if (cached) {
        const payload = JSON.parse(cached);
        applyPayload(payload);
      }
    } catch (e) {}

    // 2) Atualiza por debaixo dos panos
    async function refresh() {
      const url = `${BASE}/api/dashboard.php?period=${encodeURIComponent(PERIOD)}&m=${encodeURIComponent(M)}&y=${encodeURIComponent(Y)}`;
      const res = await fetch(url, {
        credentials: "include",
        headers: {
          "Accept": "application/json"
        }
      });

      let payload = null;
      try {
        payload = await res.json();
      } catch (e) {
        payload = null;
      }

      // Se falhou: NÃO zera nada. Mantém o que já estava (comportamento que você quer)
      if (!res.ok || !payload || !payload.ok) {
        console.warn("Dashboard API error:", payload || {
          status: res.status
        });
        return;
      }

      // aplica e salva cache
      applyPayload(payload);
      try {
        sessionStorage.setItem(cacheKey, JSON.stringify(payload));
      } catch (e) {}
    }

    refresh().catch(err => console.warn("dashboard refresh:", err));
  })();
</script>