<?php
$base = app_config('base_url');
?>
<script>
  window.__PAGE__ = 'agenda';
</script>

<div class="page">
  <div class="page-head">
    <div>
      <h1 class="page-title">Minha Agenda</h1>
      <div class="muted small" style="margin-top:4px;">
        Verifique seus compromissos e afazeres. Você pode integrar sua agenda do Google na aba "minha conta".
      </div>
    </div>
  </div>

  <div class="agenda-wrap">
    <!-- CALENDÁRIO -->
    <section class="card agenda-cal">
      <div class="card-pad">
        <div class="agenda-cal-head">
          <div class="agenda-cal-nav">
            <button class="icon-btn" id="btnPrevMonth" title="Mês anterior" type="button" aria-label="Mês anterior">
              <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>

            <div class="agenda-cal-month" id="calMonthLabel">—</div>

            <button class="icon-btn" id="btnNextMonth" title="Próximo mês" type="button" aria-label="Próximo mês">
              <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
          </div>

          <button class="btn" id="btnToday" type="button">Hoje</button>
        </div>

        <div class="agenda-dow">
          <div>DOM</div>
          <div>SEG</div>
          <div>TER</div>
          <div>QUA</div>
          <div>QUI</div>
          <div>SEX</div>
          <div>SÁB</div>
        </div>

        <div class="agenda-grid" id="calGrid"></div>
      </div>
    </section>

    <!-- PAINEL DO DIA -->
    <section class="card agenda-day">
      <div class="card-pad">
        <div class="agenda-day-title" id="dayTitle">—</div>
        <div class="agenda-day-list" id="dayList"></div>
      </div>
    </section>
  </div>

  <!-- ===== MODAL EDITAR COMPROMISSO (mesmo estilo do modal de transação) ===== -->
  <div id="agBackdrop" class="modal-backdrop" hidden></div>

  <div id="agModal" class="cat-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="cat-dialog tx-dialog" role="document">
      <div class="cat-head">
        <div id="agModalTitle" class="cat-title">Editar Compromisso</div>
        <button id="agClose" class="cat-x" type="button" aria-label="Fechar">×</button>
      </div>

      <div class="cat-body">
        <input type="hidden" id="agId" value="" />

        <label class="cat-label" for="agTitle">Título</label>
        <input id="agTitle" type="text" class="cat-input" placeholder="Ex: Consulta" />

        <div style="height:12px;"></div>

        <div class="grid-2">
          <div>
            <label class="cat-label" for="agDate">Data</label>
            <input id="agDate" type="date" class="cat-input" />
          </div>

          <div>
            <label class="cat-label" for="agReminder">Lembrete (min)</label>
            <input id="agReminder" type="number" min="0" step="5" class="cat-input" placeholder="30" />
          </div>
        </div>

        <div style="height:12px;"></div>

        <div class="grid-2 form-row">
          <div>
            <label class="cat-label" for="agStart">Início</label>
            <input id="agStart" type="time" class="cat-input" />
          </div>
          <div>
            <label class="cat-label" for="agEnd">Fim</label>
            <input id="agEnd" type="time" class="cat-input" />
          </div>
        </div>

        <div style="height:12px;"></div>

        <label class="cat-label" for="agType">Tipo</label>
        <select id="agType" class="cat-input">
          <option value="meeting">Compromisso</option>
          <option value="task">Tarefa</option>
        </select>
      </div>

      <div class="cat-foot">
        <button id="agCancel" type="button" class="cat-btn ghost">Cancelar</button>
        <button id="agSave" type="button" class="cat-btn primary">Salvar</button>
      </div>
    </div>
  </div>
  <!-- ===== /MODAL EDITAR COMPROMISSO ===== -->

</div>