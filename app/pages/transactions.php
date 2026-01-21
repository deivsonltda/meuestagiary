<?php
// app/pages/transactions.php
$base = app_config('base_url', '');
?>

<div class="page">

  <!-- Cabeçalho (igual ao print: título + subtítulo) -->
  <div class="page-head">
    <div>
      <h1 class="page-title">Transações</h1>
      <div class="muted small" style="margin-top:4px;">
        Verifique suas transações completas.
      </div>
    </div>
  </div>

  <!-- Barra de abas + busca (igual ao print) -->
  <section class="card">
    <div class="card-pad" style="padding-bottom:12px;">
      <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <!-- Tabs -->
        <div class="segmented" id="txTabs" style="margin:0;">
          <a class="active" href="javascript:void(0)" data-filter="all">Todas</a>
          <a href="javascript:void(0)" data-filter="paid">Pagos</a>
          <a href="javascript:void(0)" data-filter="received">Recebidos</a>
          <a href="javascript:void(0)" data-filter="payable">A pagar</a>
          <a href="javascript:void(0)" data-filter="receivable">A receber</a>
        </div>

        <!-- Busca -->
        <div style="flex:1; min-width:260px;">
          <input
            id="txSearch"
            class="cat-input"
            type="text"
            placeholder="Pesquisar por descrição, categoria..."
            style="width:100%;"
            autocomplete="off" />
        </div>
      </div>
    </div>

    <!-- Tabela (colunas iguais ao print) -->
    <div class="table-wrap">
      <table class="table" id="txTable">
        <thead>
          <tr>
            <th>Descrição</th>
            <th class="right">Valor</th>
            <th>Categoria</th>
            <th>Tipo</th>
            <th>Status</th>
            <th>Data</th>
            <th class="right">Ações</th>
          </tr>
        </thead>
        <tbody id="transactionsBody">
          <!-- JS injeta as linhas -->
        </tbody>
      </table>
    </div>

    <!-- Paginação (10 por página — o JS controla) -->
    <div class="card-pad" style="padding-top:12px;">
      <div style="display:flex; align-items:center; justify-content:center; gap:10px;">
        <button class="btn" id="txPrev" type="button"
          style="background:#fff;border:1px solid rgba(15,23,42,10);">
          Anterior
        </button>

        <div id="txPages" style="display:flex; gap:8px; align-items:center;">
          <!-- JS injeta os botões/bolinhas de página -->
        </div>

        <button class="btn" id="txNext" type="button"
          style="background:#fff;border:1px solid rgba(15,23,42,10);">
          Próximo
        </button>
      </div>
    </div>
  </section>

</div>

<!-- Backdrop -->
<div class="backdrop" id="txBackdrop"></div>

<!-- Modal CENTRAL (mesmo padrão de Categorias) -->
<div id="txModal" class="cat-modal" role="dialog" aria-modal="true" aria-labelledby="txModalTitle">
  <div class="cat-dialog tx-dialog">
    <div class="cat-head">
      <div id="txModalTitle" class="cat-title">Editar Transação</div>
      <button id="txClose" class="cat-x" type="button" aria-label="Fechar">✕</button>
    </div>

    <div class="cat-body">
      <input type="hidden" id="txId" value="" />

      <label class="cat-label" for="txItem">Descrição</label>
      <input id="txItem" type="text" class="cat-input" placeholder="Ex: Mercado" />

      <div style="height:12px;"></div>

      <div class="grid-2">
        <div>
          <label class="cat-label" for="txAmount">Valor</label>
          <input id="txAmount" type="number" step="0.01" min="0" class="cat-input" placeholder="0,00" />
        </div>

        <div>
          <label class="cat-label" for="txDate">Data</label>
          <input id="txDate" type="date" class="cat-input" />
        </div>
      </div>

      <div style="height:12px;"></div>

      <div class="grid-2">
        <div>
          <label class="cat-label" for="txType">Tipo</label>
          <select id="txType" class="cat-input">
            <option value="expense">Despesa</option>
            <option value="income">Receita</option>
          </select>
        </div>

        <div>
          <label class="cat-label" for="txStatus">Status</label>
          <select id="txStatus" class="cat-input">
            <option value="paid">Pago</option>
            <option value="due">A Pagar</option>
            <option value="received">Recebido</option>
            <option value="receivable">A Receber</option>
          </select>
        </div>
      </div>

      <div style="height:12px;"></div>

      <label class="cat-label" for="txCategory">Categoria</label>
      <select id="txCategory" class="cat-input">
        <option value="">Sem categoria</option>
      </select>
    </div>

    <div class="cat-foot">
      <button id="txCancel" type="button" class="cat-btn ghost">Cancelar</button>
      <button id="txSave" type="button" class="cat-btn primary">Salvar</button>
    </div>
  </div>
</div>

<script>
  window.__PAGE__ = 'transactions';
</script>