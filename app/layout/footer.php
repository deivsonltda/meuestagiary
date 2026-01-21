<?php
$js_ver = @filemtime(__DIR__ . '/../../public/assets/js/app.js') ?: time();
?>
  </div>

  <!-- ===== MODAL GLOBAL ===== -->
  <div id="modalBackdrop" class="modal-backdrop" hidden></div>

  <div id="appModal" class="modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
    <div class="modal-dialog" role="document">
      <div class="modal-head">
        <div id="modalTitle" class="modal-title">Título</div>
        <button id="modalClose" class="modal-x" type="button" aria-label="Fechar">×</button>
      </div>

      <div id="modalBody" class="modal-body"></div>

      <div id="modalFoot" class="modal-foot"></div>
    </div>
  </div>
  <!-- ===== /MODAL GLOBAL ===== -->

  <script src="<?= h(app_config('base_url')) ?>/assets/js/app.js?v=<?= $js_ver ?>"></script>
</body>
</html>