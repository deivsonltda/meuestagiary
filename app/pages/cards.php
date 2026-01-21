<script>
  // Usado pelo app.js para inicializar somente a página atual
  window.__PAGE__ = 'cards';
</script>

<div class="page">
  <div class="page-head simple">
    <div>
      <h1 class="page-title">Meus Cartões (Beta)</h1>
      <div class="muted small">
        Gerencie seus cartões de crédito, lance despesas agrupadas por cartão através do WhatsApp.
        Você pode filtrar os gastos por cartão no menu de transações.
      </div>
    </div>
  </div>

  <div style="display:flex; gap:10px; align-items:center;">
    <button id="btnAddCard" type="button" class="btn btn-black">+ Adicionar novo cartão</button>
  </div>

  <!-- Lista sem “caixa branca” atrás (sem wrapper .card) -->
  <div class="cards-list" id="cardsList"></div>
</div>

<!-- Modal: adicionar/editar cartão -->
<div id="cardBackdrop" class="modal-backdrop" hidden></div>

<div id="cardModal" class="modal" hidden>
  <div class="modal-dialog">
    <div class="modal-head">
      <div id="cardModalTitle" class="modal-title">Adicionar Cartão de Crédito</div>
      <button id="cardClose" class="modal-x" type="button">✕</button>
    </div>

    <div class="modal-body">
      <div class="form-row">
        <label for="cardName">Nome do Cartão</label>
        <input id="cardName" type="text" placeholder="Ex: Black Itaú" />
      </div>

      <div class="form-row">
        <label for="cardLimit">Limite do Cartão (R$)</label>
        <input id="cardLimit" type="number" step="0.01" min="0" placeholder="Ex: 45000" />
      </div>

      <div class="form-row">
        <label for="cardCloseDay">Dia de Fechamento da Fatura</label>
        <input id="cardCloseDay" type="number" min="1" max="31" placeholder="Ex: 15" />
      </div>

      <div class="form-row" style="display:flex; gap:10px; align-items:center;">
        <input id="cardDefault" type="checkbox" style="width:16px; height:16px;" />
        <label for="cardDefault" style="margin:0; color:rgba(15,23,42,.75);">Definir como cartão padrão</label>
      </div>
    </div>

    <div class="modal-foot">
      <button id="cardCancel" type="button" class="btn-ghost">Cancelar</button>
      <button id="cardSave" type="button" class="cat-btn primary">Salvar Cartão</button>
    </div>
  </div>
</div>