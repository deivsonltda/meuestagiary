<?php $base = app_config('base_url'); ?>

<div class="page">
  <div class="page-head simple">
    <h1 class="page-title">Categorias</h1>

    <button class="btn" id="btnAddCategory" type="button"
      style="background:#fff;border:1px solid rgba(15,23,42,.10);">
      + Adicionar Categoria
    </button>
  </div>

  <!-- Lista sem “caixa branca” atrás (sem wrapper .card) -->
  <div class="list" id="categoriesList"></div>
</div>

<!-- Backdrop (usado pelos modais desta página) -->
<div class="backdrop" id="catBackdrop"></div>

<!-- Modal (Add/Edit) - CENTRAL -->
<div id="catModal" class="cat-modal" role="dialog" aria-modal="true" aria-labelledby="catModalTitle">
  <div class="cat-dialog">
    <div class="cat-head">
      <div id="catModalTitle" class="cat-title">Adicionar Categoria</div>
      <button id="catClose" class="cat-x" type="button" aria-label="Fechar">✕</button>
    </div>

    <div class="cat-body">
      <label class="cat-label" for="catName">Nome da Categoria</label>
      <input id="catName" type="text" class="cat-input" placeholder="Ex: Alimentação, Transporte..." />

      <div style="height:14px;"></div>

      <div class="cat-label">Cor da Categoria</div>

      <!-- Swatches (clicáveis) -->
      <div class="cat-colors" id="catSwatches">
        <button type="button" class="cat-swatch" data-color="#f97316" style="background:#f97316" aria-label="Laranja"></button>
        <button type="button" class="cat-swatch" data-color="#22c55e" style="background:#22c55e" aria-label="Verde"></button>
        <button type="button" class="cat-swatch" data-color="#3b82f6" style="background:#3b82f6" aria-label="Azul"></button>
        <button type="button" class="cat-swatch" data-color="#a855f7" style="background:#a855f7" aria-label="Roxo"></button>
        <button type="button" class="cat-swatch" data-color="#eab308" style="background:#eab308" aria-label="Amarelo"></button>
        <button type="button" class="cat-swatch" data-color="#facc15" style="background:#facc15" aria-label="Dourado"></button>
        <button type="button" class="cat-swatch" data-color="#ef4444" style="background:#ef4444" aria-label="Vermelho"></button>
        <button type="button" class="cat-swatch" data-color="#10b981" style="background:#10b981" aria-label="Verde água"></button>
        <button type="button" class="cat-swatch" data-color="#0f172a" style="background:#0f172a" aria-label="Preto"></button>
      </div>

      <!-- Picker -->
      <div class="cat-color-row">
        <input id="catColor" type="color" class="cat-colorpicker" value="#3b82f6" />
        <input id="catColorMirror" type="text" class="cat-input" value="#3b82f6" />
      </div>
    </div>

    <div class="cat-foot">
      <button id="catCancel" type="button" class="cat-btn ghost">Cancelar</button>
      <button id="catSave" type="button" class="cat-btn primary">Salvar Categoria</button>
    </div>
  </div>
</div>

<!-- Confirm Modal (Excluir) - CENTRAL -->
<div id="confirmModal"
  style="position:fixed; inset:0; display:flex; align-items:center; justify-content:center;
         opacity:0; pointer-events:none; transition:opacity .15s ease; z-index:70; padding:16px;">
  <div style="width:min(520px, 100%); background:#fff; border:1px solid rgba(15,23,42,.10);
              border-radius:18px; box-shadow:0 25px 70px rgba(0,0,0,.22); overflow:hidden;">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;
                padding:14px 16px; border-bottom:1px solid rgba(15,23,42,.08);">
      <div style="font-weight:800; font-size:14px;">Confirmar exclusão</div>
      <button id="confirmClose" type="button"
        style="width:36px;height:36px;border-radius:12px;border:1px solid rgba(15,23,42,.12);
               background:#fff;cursor:pointer;font-size:20px;line-height:0;">✕</button>
    </div>

    <div id="confirmText" style="padding:16px; color:rgba(15,23,42,.80); font-size:14px;">
      Tem certeza?
    </div>

    <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 16px;
                border-top:1px solid rgba(15,23,42,.08); background:#fff;">
      <button class="btn-ghost" type="button" id="confirmCancel">Cancelar</button>
      <button class="btn-danger" type="button" id="confirmOk">Excluir</button>
    </div>
  </div>
</div>

<script>
  window.__PAGE__ = 'categories';

  // Swatches + espelho do input (não mexe no seu app.js)
  (function(){
    const color = document.getElementById('catColor');
    const mirror = document.getElementById('catColorMirror');
    const wrap = document.getElementById('catSwatches');
    if (!color || !mirror || !wrap) return;

    function setColor(v){
      color.value = v;
      mirror.value = v;
    }

    color.addEventListener('input', () => setColor(color.value));
    mirror.addEventListener('input', () => {
      const v = (mirror.value || '').trim();
      if(/^#[0-9a-fA-F]{6}$/.test(v)) setColor(v);
    });

    wrap.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-color]');
      if(!btn) return;
      const v = btn.getAttribute('data-color');
      if(v) setColor(v);
    });
  })();
</script>

<script>
  // Fecha (X) do modal de confirmação reaproveita o mesmo handler do botão "Cancelar"
  // (evita ter que mexer no app.js).
  document.getElementById('confirmClose')?.addEventListener('click', () => {
    document.getElementById('confirmCancel')?.click();
  });
</script>