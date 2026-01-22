/* =========================================================
   app.js (global)
   - Burger/Sidebar (mobile)
   - Toast (sem alert)
   - Page modules:
     * Categories: add/edit + confirm delete (modal)
     * Cards: add/edit + delete (modal)
     * Agenda: calendário mensal + pontinho em dias com evento + lista do dia
     * Transactions
     * Account: change password + save profile + integrations (Google Calendar)
   ========================================================= */

(() => {
  // ---------- Helpers ----------
  const $ = (id) => document.getElementById(id);

  const escapeHtml = (s) =>
    String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const escapeAttr = (s) => escapeHtml(s).replaceAll("`", "&#096;");

  // Expor helpers p/ módulos que tentam usar window.*
  window.escapeHtml = escapeHtml;

  // ---------- Toast (no alerts) ----------
  function ensureToastHost() {
    let host = document.getElementById("toastHost");
    if (host) return host;

    host = document.createElement("div");
    host.id = "toastHost";
    host.style.position = "fixed";
    host.style.right = "14px";
    host.style.bottom = "14px";
    host.style.zIndex = "9999";
    host.style.display = "flex";
    host.style.flexDirection = "column";
    host.style.gap = "10px";
    document.body.appendChild(host);
    return host;
  }

  function toast(message, type = "info") {
    try {
      const host = ensureToastHost();
      const el = document.createElement("div");
      el.style.width = "min(420px, calc(100vw - 28px))";
      el.style.padding = "12px 14px";
      el.style.borderRadius = "14px";
      el.style.boxShadow = "0 20px 60px rgba(0,0,0,.35)";
      el.style.border = "1px solid rgba(255,255,255,.12)";
      el.style.background = "#0b1220";
      el.style.color = "rgba(255,255,255,.92)";
      el.style.fontSize = "13px";
      el.style.lineHeight = "1.35";
      el.style.display = "flex";
      el.style.alignItems = "flex-start";
      el.style.gap = "10px";

      const dot = document.createElement("span");
      dot.style.width = "10px";
      dot.style.height = "10px";
      dot.style.borderRadius = "999px";
      dot.style.marginTop = "4px";
      dot.style.flex = "0 0 auto";
      dot.style.background =
        type === "error" ? "#ef4444" : type === "success" ? "#22c55e" : "#3b82f6";

      const text = document.createElement("div");
      text.innerHTML = escapeHtml(message);

      const close = document.createElement("button");
      close.type = "button";
      close.textContent = "✕";
      close.style.marginLeft = "auto";
      close.style.background = "transparent";
      close.style.border = "0";
      close.style.color = "rgba(255,255,255,.65)";
      close.style.cursor = "pointer";
      close.style.fontSize = "14px";
      close.onclick = () => el.remove();

      el.appendChild(dot);
      el.appendChild(text);
      el.appendChild(close);
      host.appendChild(el);

      setTimeout(() => {
        el.style.transition = "opacity .2s ease, transform .2s ease";
        el.style.opacity = "0";
        el.style.transform = "translateY(6px)";
        setTimeout(() => el.remove(), 220);
      }, 4200);
    } catch (e) {
      console.log("[toast]", message);
    }
  }

  // Expor toast p/ módulos
  window.toast = toast;

  // ---------- API helpers ----------
  // ✅ CSRF token vindo do <meta name="csrf-token" ...> no header.php
  function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? (m.getAttribute("content") || "") : "";
  }

  async function apiGet(url) {
    const r = await fetch(url, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "fetch",
      },
    });

    const text = await r.text();

    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Resposta não-JSON. HTTP ${r.status}. Conteúdo: ${text.slice(0, 200)}`
      );
    }

    if (!r.ok || json.ok === false) {
      throw new Error(json.error?.message || json.error || `HTTP ${r.status}`);
    }
    return json;
  }

  async function apiPost(url, body) {
    const r = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Requested-With": "fetch",
        "X-CSRF-Token": getCsrf(),
      },
      body: JSON.stringify(body ?? {}),
    });

    const text = await r.text();

    let json;
    try {
      json = JSON.parse(text);
    } catch (e) {
      throw new Error(
        `Resposta não-JSON. HTTP ${r.status}. Conteúdo: ${text.slice(0, 200)}`
      );
    }

    if (!r.ok || json.ok === false) {
      throw new Error(json.error?.message || json.error || `HTTP ${r.status}`);
    }
    return json;
  }

  // Expor apiGet/apiPost p/ módulos
  window.apiGet = apiGet;
  window.apiPost = apiPost;

  // ---------------------------
  // Cache leve (sessionStorage)
  // ---------------------------
  // Objetivo: evitar o "flash" de estado vazio ("Nenhum encontrado")
  // enquanto a página ainda está carregando dados do Supabase.
  // Mantém a funcionalidade: sempre buscamos do servidor e depois atualizamos.
  const cacheGet = (key, ttlMs) => {
    try {
      const raw = sessionStorage.getItem(key);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object") return null;

      const ts = Number(parsed.ts || 0);
      if (!Number.isFinite(ts)) return null;

      if (ttlMs && Date.now() - ts > ttlMs) return null;
      return parsed.value ?? null;
    } catch (e) {
      return null;
    }
  };

  const cacheSet = (key, value) => {
    try {
      sessionStorage.setItem(key, JSON.stringify({ ts: Date.now(), value }));
    } catch (e) { }
  };


  // =========================================================
  // 1) Burger / Sidebar (mobile)
  // =========================================================
  (function initBurger() {
    const btnBurger = $("btnBurger");
    const sidebar = $("sidebar");
    const backdrop = $("backdrop");
    const btnClose = $("btnCloseSidebar");

    function open() {
      if (!sidebar) return;
      sidebar.classList.add("open");
      backdrop && backdrop.classList.add("show");
    }

    function close() {
      if (!sidebar) return;
      sidebar.classList.remove("open");
      backdrop && backdrop.classList.remove("show");
    }

    btnBurger && btnBurger.addEventListener("click", open);
    btnClose && btnClose.addEventListener("click", close);
    backdrop && backdrop.addEventListener("click", close);

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") close();
    });
  })();

  // =========================================================
  // 2) Page: Categories (Add/Edit modal + Confirm delete)
  // =========================================================
  (function initCategoriesPage() {
    if (window.__PAGE__ !== "categories") return;

    const listEl = $("categoriesList");
    const btnAdd = $("btnAddCategory");

    const backdrop = $("catBackdrop");

    // Drawer (Add/Edit)
    const modal = $("catModal");
    const modalTitle = $("catModalTitle");
    const btnClose = $("catClose");
    const btnCancel = $("catCancel");
    const btnSave = $("catSave");
    const inpName = $("catName");
    const inpColor = $("catColor");

    // Confirm (Delete)
    const confirmModal = $("confirmModal");
    const confirmText = $("confirmText");
    const confirmOk = $("confirmOk");
    const confirmCancel = $("confirmCancel");

    if (!listEl) return;

    const state = {
      items: [],
      loading: false,
      editingId: null,
      deletingId: null,
    };

    function showBackdrop() {
      backdrop && backdrop.classList.add("show");
    }
    function hideBackdrop() {
      backdrop && backdrop.classList.remove("show");
    }

    function openDrawer(title) {
      if (!modal) return;
      modalTitle && (modalTitle.textContent = title || "Categoria");
      showBackdrop();
      modal.classList.add("open");
      setTimeout(() => inpName && inpName.focus(), 50);
    }

    function closeDrawer() {
      if (!modal) return;
      modal.classList.remove("open");
      hideBackdrop();

      state.editingId = null;
      if (inpName) inpName.value = "";
      if (inpColor) inpColor.value = "#3b82f6";
    }

    function openConfirm(text, onOk) {
      if (!confirmModal) return;

      showBackdrop();
      confirmText && (confirmText.textContent = text);

      confirmModal.style.opacity = "1";
      confirmModal.style.pointerEvents = "auto";

      const handler = async () => {
        confirmOk && confirmOk.removeEventListener("click", handler);
        try {
          await onOk();
        } finally {
          closeConfirm();
        }
      };

      confirmOk && confirmOk.addEventListener("click", handler);
    }

    function closeConfirm() {
      if (!confirmModal) return;
      confirmModal.style.opacity = "0";
      confirmModal.style.pointerEvents = "none";
      hideBackdrop();
      state.deletingId = null;
    }

    function closeAllModals() {
      closeDrawer();
      closeConfirm();
    }

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAllModals();
    });

    backdrop && backdrop.addEventListener("click", closeAllModals);
    btnClose && btnClose.addEventListener("click", closeDrawer);
    btnCancel && btnCancel.addEventListener("click", closeDrawer);
    confirmCancel && confirmCancel.addEventListener("click", closeConfirm);

    function render() {
      if (!state.items.length) {
        const msg = state.loading
          ? "Carregando categorias..."
          : "Nenhuma categoria encontrada.";

        listEl.innerHTML = `<div style="padding:16px; color: rgba(15,23,42,.70);">
    ${msg}
  </div>`;
        return;
      }

      listEl.innerHTML = state.items
        .map(
          (c) => `
    <div class="list-row">
      <div class="list-left">
        <span class="color-dot" style="background:${c.color || "#3b82f6"}"></span>
        <span class="list-name">${escapeHtml(c.name || "")}</span>
      </div>

      <div class="list-actions">
        <button class="icon-action edit"
                data-act="edit"
                data-id="${c.id}"
                title="Editar" type="button">
          <i class="fa-regular fa-pen-to-square"></i>
        </button>

        <button class="icon-action delete"
                data-act="del"
                data-id="${c.id}"
                data-name="${escapeAttr(c.name || "")}"
                title="Excluir" type="button">
          <i class="fa-regular fa-trash-can"></i>
        </button>
      </div>
    </div>
  `
        )
        .join("");
    }

    async function load() {
      // 1) Mostra loading (evita "flash" de lista vazia)
      state.loading = true;
      render();

      // 2) Hidrata do cache (instantâneo), se existir
      const cached = cacheGet("meuestagiario:categories:list", 60_000);
      if (Array.isArray(cached) && cached.length) {
        state.items = cached;
        state.loading = false;
        render();
      }

      // 3) Busca do servidor (fonte da verdade) e atualiza
      try {
        const data = await apiGet("/api/categories.php?action=list");
        state.items = data.items || [];
        cacheSet("meuestagiario:categories:list", state.items);
        state.loading = false;
        render();
      } catch (err) {
        state.loading = false;
        listEl.innerHTML = `
      <div class="alert">
        Erro ao carregar categorias: ${escapeHtml(err.message)}
      </div>`;
      }
    }

    function openAdd() {
      state.editingId = null;
      if (modalTitle) modalTitle.textContent = "Adicionar Categoria";
      if (inpName) inpName.value = "";
      if (inpColor) inpColor.value = "#3b82f6";
      openDrawer();
      setTimeout(() => inpName && inpName.focus(), 60);
    }

    function openEdit(id) {
      const cat = state.items.find((x) => x.id === id);
      if (!cat) return;

      state.editingId = id;
      if (modalTitle) modalTitle.textContent = "Editar Categoria";
      if (inpName) inpName.value = cat.name || "";
      if (inpColor) inpColor.value = cat.color || "#3b82f6";
      openDrawer();
      setTimeout(() => inpName && inpName.focus(), 60);
    }

    async function save() {
      const name = (inpName?.value || "").trim();
      let color = (inpColor?.value || "#3b82f6").trim();

      if (!name) {
        toast("Nome obrigatório.", "error");
        inpName && inpName.focus();
        return;
      }

      if (!/^#[0-9a-fA-F]{6}$/.test(color)) color = "#3b82f6";

      btnSave && (btnSave.disabled = true);
      try {
        await apiPost("/api/categories.php?action=upsert", {
          id: state.editingId || "",
          name,
          color,
        });
        toast("Categoria salva.", "success");
        closeDrawer();
        await load();
      } catch (err) {
        toast(err.message, "error");
      } finally {
        btnSave && (btnSave.disabled = false);
      }
    }

    function askDelete(id, name) {
      openConfirm(`Excluir a categoria "${name}"?`, async () => {
        try {
          await apiPost("/api/categories.php?action=delete", { id });
          toast("Categoria excluída.", "success");
          await load();
        } catch (err) {
          toast(err.message, "error");
        }
      });
    }

    // Events
    btnAdd && btnAdd.addEventListener("click", openAdd);
    btnSave && btnSave.addEventListener("click", save);

    listEl.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-act]");
      if (!btn) return;

      const act = btn.getAttribute("data-act");
      const id = btn.getAttribute("data-id");
      if (!id) return;

      if (act === "edit") openEdit(id);
      if (act === "del") askDelete(id, btn.getAttribute("data-name") || "");
    });

    // boot
    load();
  })();

  // =========================================================
  // 3) Page: Cards (Meus Cartões)
  // =========================================================
  (function initCardsPage() {
    if (window.__PAGE__ !== "cards") return;

    const listEl = $("cardsList");
    const btnAdd = $("btnAddCard");

    // IDs do cards.php:
    const backdrop = $("cardBackdrop"); // modal-backdrop
    const modal = $("cardModal"); // modal
    const modalTitle = $("cardModalTitle");
    const btnClose = $("cardClose") || $("cardModalClose"); // suporta os dois
    const btnCancel = $("cardCancel");
    const btnSave = $("cardSave");

    // campos (cards.php usa estes IDs)
    const fName = $("cardName");
    const fLimit = $("cardLimit");
    const fCloseDay = $("cardCloseDay");
    const fDefault = $("cardDefault");

    if (!listEl || !modal || !backdrop) return;

    // garante hidden input de id (caso não tenha no HTML)
    let hiddenId = $("cardId");
    if (!hiddenId) {
      hiddenId = document.createElement("input");
      hiddenId.type = "hidden";
      hiddenId.id = "cardId";
      modal.querySelector(".modal-body")?.prepend(hiddenId);
    }

    const state = { items: [], editing: null };

    function moneyBR(v) {
      const n = Number(v || 0);
      return n.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    function parseMoney(s) {
      const str = String(s ?? "").replace(/[^\d,.-]/g, "");
      let x = str;
      if (x.includes(",") && x.includes(".")) x = x.replace(/\./g, "").replace(",", ".");
      else if (x.includes(",") && !x.includes(".")) x = x.replace(",", ".");
      const n = Number(x);
      return Number.isFinite(n) ? n : 0;
    }

    // ---------- Modal open/close ----------
    function openModal(editing) {
      backdrop.hidden = false;
      modal.hidden = false;
      modal.setAttribute("aria-hidden", "false");

      state.editing = editing || null;

      if (!editing) {
        modalTitle && (modalTitle.textContent = "Adicionar Cartão de Crédito");
        hiddenId.value = "";
        fName && (fName.value = "");
        fLimit && (fLimit.value = "");
        fCloseDay && (fCloseDay.value = "");
        fDefault && (fDefault.checked = false);
      } else {
        modalTitle && (modalTitle.textContent = "Editar Cartão de Crédito");
        hiddenId.value = editing.id || "";
        fName && (fName.value = editing.name || "");
        fLimit && (fLimit.value = editing.limit ?? "");
        fCloseDay && (fCloseDay.value = editing.closing_day ?? "");
        fDefault && (fDefault.checked = !!editing.is_default);
      }

      setTimeout(() => fName && fName.focus(), 0);
    }

    function closeModal() {
      modal.hidden = true;
      backdrop.hidden = true;
      modal.setAttribute("aria-hidden", "true");
      state.editing = null;
      // se existir confirm aberto, fecha também
      closeConfirm();
      resetBackdropLayer();
    }

    btnAdd && btnAdd.addEventListener("click", () => openModal(null));
    btnClose && btnClose.addEventListener("click", closeModal);
    btnCancel && btnCancel.addEventListener("click", closeModal);

    document.addEventListener("keydown", (e) => {
      if (window.__PAGE__ !== "cards") return;
      if (e.key === "Escape") {
        closeConfirm();
        closeModal();
      }
    });

    // clique fora: se confirm aberto, fecha confirm; senão fecha modal
    backdrop && backdrop.addEventListener("click", () => {
      if (isConfirmOpen()) {
        closeConfirm();
        return;
      }
      closeModal();
    });

    // ---------- Confirm modal (igual ao de Categorias) ----------
    let confirmWrap = null;
    let confirmOpened = false;

    function resetBackdropLayer() {
      // volta qualquer mexida de z-index/pointer-events
      backdrop.style.zIndex = "";
      backdrop.style.pointerEvents = "";
    }

    function ensureConfirmModal() {
      if (confirmWrap) return confirmWrap;

      confirmWrap = document.createElement("div");
      confirmWrap.id = "cardConfirmModal";
      confirmWrap.style.position = "fixed";
      confirmWrap.style.inset = "0";
      confirmWrap.style.display = "flex";
      confirmWrap.style.alignItems = "center";
      confirmWrap.style.justifyContent = "center";
      confirmWrap.style.opacity = "0";
      confirmWrap.style.pointerEvents = "none";
      confirmWrap.style.transition = "opacity .15s ease";
      confirmWrap.style.padding = "16px";

      // ✅ IMPORTANTE: z-index alto (sempre acima do backdrop)
      confirmWrap.style.zIndex = "100000";

      confirmWrap.innerHTML = `
        <div style="width:min(520px, 100%); background:#fff; border:1px solid rgba(15,23,42,.10);
                    border-radius:18px; box-shadow:0 25px 70px rgba(0,0,0,.22); overflow:hidden;">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;
                      padding:14px 16px; border-bottom:1px solid rgba(15,23,42,.08);">
            <div style="font-weight:800; font-size:14px;">Confirmar exclusão</div>
            <button type="button" id="cardConfirmClose"
              style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(15,23,42,.12);
                     background:#fff;cursor:pointer;font-size:20px;line-height:0;">✕</button>
          </div>

          <div id="cardConfirmText" style="padding:16px; color:rgba(15,23,42,.80); font-size:14px;">
            Tem certeza?
          </div>

          <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 16px;
                      border-top:1px solid rgba(15,23,42,.08); background:#fff;">
            <button class="btn-ghost" type="button" id="cardConfirmCancel">Cancelar</button>
            <button class="btn-danger" type="button" id="cardConfirmOk">Excluir</button>
          </div>
        </div>
      `;

      document.body.appendChild(confirmWrap);

      confirmWrap.querySelector("#cardConfirmClose")?.addEventListener("click", closeConfirm);
      confirmWrap.querySelector("#cardConfirmCancel")?.addEventListener("click", closeConfirm);

      return confirmWrap;
    }

    function isConfirmOpen() {
      return !!confirmOpened;
    }

    function openConfirm(text, onOk) {
      ensureConfirmModal();

      // mostra backdrop + modal confirm
      backdrop.hidden = false;

      // ✅ garante backdrop abaixo do confirm SEM bloquear clique no confirm
      backdrop.style.zIndex = "99990";
      backdrop.style.pointerEvents = "auto"; // continua fechando ao clicar fora

      confirmWrap.style.opacity = "1";
      confirmWrap.style.pointerEvents = "auto";
      confirmOpened = true;

      const t = confirmWrap.querySelector("#cardConfirmText");
      if (t) t.textContent = text;

      const okBtn = confirmWrap.querySelector("#cardConfirmOk");

      // remove listener antigo (se houver)
      okBtn?.replaceWith(okBtn.cloneNode(true));
      const newOkBtn = confirmWrap.querySelector("#cardConfirmOk");

      const handler = async () => {
        newOkBtn?.removeEventListener("click", handler);
        try {
          await onOk();
        } finally {
          closeConfirm();
        }
      };

      newOkBtn?.addEventListener("click", handler);
    }

    function closeConfirm() {
      if (!confirmWrap) return;
      confirmWrap.style.opacity = "0";
      confirmWrap.style.pointerEvents = "none";
      confirmOpened = false;

      // se modal principal estiver fechado, esconde backdrop também
      const modalIsOpen = modal && modal.hidden === false;
      if (!modalIsOpen) backdrop.hidden = true;

      resetBackdropLayer();
    }

    // ---------- Render ----------
    function render() {
      if (!state.items.length) {
        listEl.innerHTML = `<div class="muted small">Nenhum cartão cadastrado ainda.</div>`;
        return;
      }

      listEl.innerHTML = state.items
        .map((c) => {
          const used = Number(c.used || 0);
          const limit = Number(c.limit || 0);
          const avail = Math.max(0, limit - used);
          const pct = limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) : 0;

          return `
            <div class="card-item" data-id="${escapeAttr(c.id)}">
              <div class="card-item-row">
                <div class="card-item-left">
                  <div class="card-item-name">
                    <span class="card-ico">💳</span>
                    ${escapeHtml(c.name || "")}
                    ${c.is_default ? `<span class="pill" style="margin-left:8px;">Padrão</span>` : ``}
                  </div>
                  <div class="muted small">Dia de fechamento: ${escapeHtml(c.closing_day ?? "-")}</div>
                  <div class="muted small" style="margin-top:8px;">Limite utilizado na fatura atual:</div>
                </div>

                <div class="card-item-actions">
                  <button class="icon-action edit" data-act="edit" title="Editar" type="button">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>

                  <button class="icon-action delete" data-act="del" title="Excluir" type="button">
                    <i class="fa-regular fa-trash-can"></i>
                  </button>
                </div>
              </div>

              <div class="card-progress">
                <div class="card-progress-bar" style="width:${pct}%"></div>
              </div>

              <div class="card-item-bottom">
                <div class="small"><strong>${moneyBR(used)}</strong> de ${moneyBR(limit)}</div>
                <div class="small muted">Disponível: <strong>${moneyBR(avail)}</strong></div>
              </div>
            </div>
          `;
        })
        .join("");
    }

    async function load() {
      // 1) cola na tela com cache (até 60s)
      const cached = cacheGet("meuestagiario:cards:list", 60_000);
      if (Array.isArray(cached) && cached.length) {
        state.items = cached;
        render();
      }

      // 2) atualiza por baixo dos panos (LIST é GET no backend)
      try {
        const res = await fetch((window.__BASE__ || "") + "/api/cards.php", {
          method: "GET",
          credentials: "include",
          headers: { Accept: "application/json" },
        });

        const data = await res.json();
        if (!res.ok || !data || !data.ok) throw new Error((data && data.error) || `HTTP ${res.status}`);

        state.items = data.items || [];
        cacheSet("meuestagiario:cards:list", state.items);
      } catch (e) {
        console.warn("Falha ao carregar cartões", e);
      } finally {
        render();
      }
    }

    // ---------- Save ----------
    async function save() {
      const name = (fName?.value || "").trim();
      const closing_day = Number(fCloseDay?.value || 0);

      if (!name) {
        toast("Nome do cartão é obrigatório.", "error");
        fName && fName.focus();
        return;
      }

      // backend do seu cards.php valida 1..28 (mantive aqui alinhado)
      if (!(closing_day >= 1 && closing_day <= 28)) {
        toast("Dia de fechamento deve ser entre 1 e 28.", "error");
        fCloseDay && fCloseDay.focus();
        return;
      }

      const payload = {
        action: "upsert",
        id: hiddenId.value || "",
        name,
        limit: parseMoney(fLimit?.value || ""),
        closing_day,
        is_default: !!fDefault?.checked,
      };

      btnSave && (btnSave.disabled = true);
      try {
        await apiPost("/api/cards.php?action=upsert", payload);
        toast("Cartão salvo.", "success");
        closeModal();
        await load();
      } catch (err) {
        toast(err.message, "error");
      } finally {
        btnSave && (btnSave.disabled = false);
      }
    }

    btnSave && btnSave.addEventListener("click", save);

    // ---------- Actions (edit / delete) ----------
    listEl.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-act]");
      if (!btn) return;

      const cardEl = e.target.closest(".card-item");
      if (!cardEl) return;

      const id = cardEl.getAttribute("data-id");
      const item = state.items.find((x) => String(x.id) === String(id));
      if (!item) return;

      const act = btn.getAttribute("data-act");

      if (act === "edit") {
        openModal(item);
        return;
      }

      if (act === "del") {
        openConfirm(`Excluir o cartão "${item.name || ""}"?`, async () => {
          await apiPost("/api/cards.php?action=delete", { id: item.id });
          toast("Cartão excluído.", "success");
          await load();
        });
      }
    });

    // boot
    load();
  })();

  // =========================================================
  // 4) Page: Agenda
  // =========================================================
  (function initAgendaPage() {
    if (window.__PAGE__ !== "agenda") return;

    const monthLabel = $("calMonthLabel");
    const btnPrev = $("btnPrevMonth");
    const btnNext = $("btnNextMonth");
    const btnToday = $("btnToday");
    const grid = $("calGrid");
    const dayTitle = $("dayTitle");
    const dayList = $("dayList");

    // Modal (editar)
    const agBackdrop = $("agBackdrop");
    const agModal = $("agModal");
    const agModalTitle = $("agModalTitle");
    const agClose = $("agClose");
    const agCancel = $("agCancel");
    const agSave = $("agSave");

    const agId = $("agId");
    const agTitle = $("agTitle");
    const agDate = $("agDate");
    const agReminder = $("agReminder");
    const agStart = $("agStart");
    const agEnd = $("agEnd");
    const agType = $("agType");

    if (!grid || !monthLabel || !dayTitle || !dayList) return;

    const fmtMonth = new Intl.DateTimeFormat("pt-BR", { month: "long", year: "numeric" });
    const fmtDayTitle = new Intl.DateTimeFormat("pt-BR", { day: "numeric", month: "long" });
    const fmtTime = new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit" });

    const state = {
      view: new Date(),
      selected: new Date(),
      items: [],
      byDate: new Map(),
    };

    function ymd(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      const da = String(d.getDate()).padStart(2, "0");
      return `${y}-${m}-${da}`;
    }
    function ym(d) {
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, "0");
      return `${y}-${m}`;
    }
    function startOfMonth(d) {
      return new Date(d.getFullYear(), d.getMonth(), 1);
    }
    function sameDay(a, b) {
      return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function groupEvents() {
      state.byDate = new Map();
      for (const e of state.items) {
        const s = new Date(e.start_at);
        const key = ymd(s);
        if (!state.byDate.has(key)) state.byDate.set(key, []);
        state.byDate.get(key).push(e);
      }
    }

    async function loadMonth() {
      const key = ym(state.view);
      const cacheKey = `meuestagiario:agenda:${key}`;

      // 1) cola na tela com cache (até 60s)
      const cached = cacheGet(cacheKey, 60_000);
      if (Array.isArray(cached)) {
        state.items = cached;
        groupEvents();
        renderCalendar();
        renderDayPanel();
      }

      // 2) atualiza por baixo dos panos
      const r = await fetch(`/api/agenda.php?month=${encodeURIComponent(key)}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });

      const data = await r.json();
      if (!r.ok || !data.ok) throw new Error(data.error?.message || data.error || `HTTP ${r.status}`);

      state.items = data.items || [];
      cacheSet(cacheKey, state.items);
      groupEvents();
    }

    function renderCalendar() {
      const m0 = startOfMonth(state.view);
      monthLabel.textContent = fmtMonth.format(m0);

      const firstDow = m0.getDay();
      const firstCell = new Date(m0);
      firstCell.setDate(m0.getDate() - firstDow);

      const html = [];
      for (let i = 0; i < 42; i++) {
        const d = new Date(firstCell);
        d.setDate(firstCell.getDate() + i);

        const inMonth = d.getMonth() === state.view.getMonth();
        const isSelected = sameDay(d, state.selected);
        const hasEvent = state.byDate.has(ymd(d));

        html.push(`
        <button class="agenda-cell ${inMonth ? "" : "muted"} ${isSelected ? "selected" : ""}"
                data-date="${ymd(d)}" type="button">
          <span class="agenda-daynum">${d.getDate()}</span>
          ${hasEvent ? `<span class="agenda-dot"></span>` : ``}
        </button>
      `);
      }

      grid.innerHTML = html.join("");
    }

    function renderDayPanel() {
      dayTitle.textContent = fmtDayTitle.format(state.selected);

      const key = ymd(state.selected);
      const items = state.byDate.get(key) || [];

      if (!items.length) {
        dayList.innerHTML = `
        <div class="muted" style="margin-top:12px;">Nenhum evento neste dia</div>
        <div class="agenda-hint">
          <span class="agenda-wa"></span>
          <strong>Converse com seu assessor para criar, excluir e editar eventos</strong>
        </div>
      `;
        return;
      }

      dayList.innerHTML = items
        .map((e) => {
          const s = new Date(e.start_at);
          const en = new Date(e.end_at);
          const t = `${fmtTime.format(s)} - ${fmtTime.format(en)}`;

          const rem = Number(e.reminder_minutes ?? 30);
          const remTxt = rem === 60 ? "1 hora antes" : `${rem} minutos antes`;

          return `
          <div class="agenda-evt" data-id="${escapeAttr(e.id)}">
            <div class="agenda-evt-bar"></div>
            <div class="agenda-evt-main">
              <div class="agenda-evt-title">⛰️ ${escapeHtml(e.title || "Compromisso")}</div>
              <div class="agenda-evt-time">${escapeHtml(t)}</div>
            </div>
            <div class="agenda-evt-right">
              <div class="agenda-evt-rem">🔔 ${escapeHtml(remTxt)}</div>
            </div>
            <button class="agenda-evt-more" data-act="more" type="button" title="Mais" aria-label="Mais">⋮</button>
          </div>
        `;
        })
        .join("");
    }

    // ---------------------------
    // Modal (Editar) - mesmo padrão do drawer
    // ---------------------------
    function showAgBackdrop() {
      if (!agBackdrop) return;
      agBackdrop.hidden = false;
      agBackdrop.classList.add("show");
    }
    function hideAgBackdrop() {
      if (!agBackdrop) return;
      agBackdrop.classList.remove("show");
      agBackdrop.hidden = true;
    }

    function openAgModal(title) {
      if (!agModal) return;
      if (agModalTitle) agModalTitle.textContent = title || "Editar Compromisso";

      showAgBackdrop();

      agModal.hidden = false;
      agModal.setAttribute("aria-hidden", "false");

      // garante aparecer (mesmo estilo do tx drawer)
      agModal.classList.add("open");
      agModal.style.transform = "translateX(0)";

      setTimeout(() => agTitle && agTitle.focus(), 50);
    }

    function closeAgModal() {
      if (!agModal) return;

      agModal.style.transform = "translateX(110%)";
      agModal.classList.remove("open");

      setTimeout(() => {
        agModal.hidden = true;
        agModal.setAttribute("aria-hidden", "true");
        hideAgBackdrop();
      }, 160);
    }

    agClose && agClose.addEventListener("click", closeAgModal);
    agCancel && agCancel.addEventListener("click", closeAgModal);

    // ---------------------------
    // Confirm modal (Excluir) - SEM alert()
    // ---------------------------
    let agConfirmWrap = null;
    let agConfirmOpened = false;

    function ensureAgConfirm() {
      if (agConfirmWrap) return agConfirmWrap;

      agConfirmWrap = document.createElement("div");
      agConfirmWrap.id = "agConfirmModal";
      agConfirmWrap.style.position = "fixed";
      agConfirmWrap.style.inset = "0";
      agConfirmWrap.style.display = "flex";
      agConfirmWrap.style.alignItems = "center";
      agConfirmWrap.style.justifyContent = "center";
      agConfirmWrap.style.opacity = "0";
      agConfirmWrap.style.pointerEvents = "none";
      agConfirmWrap.style.transition = "opacity .15s ease";
      agConfirmWrap.style.padding = "16px";
      agConfirmWrap.style.zIndex = "100000";

      agConfirmWrap.innerHTML = `
      <div style="width:min(520px, 100%); background:#fff; border:1px solid rgba(15,23,42,.10);
                  border-radius:18px; box-shadow:0 25px 70px rgba(0,0,0,.22); overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;
                    padding:14px 16px; border-bottom:1px solid rgba(15,23,42,.08);">
          <div style="font-weight:800; font-size:14px;">Confirmar exclusão</div>
          <button type="button" id="agConfirmClose"
            style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(15,23,42,.12);
                   background:#fff;cursor:pointer;font-size:20px;line-height:0;">✕</button>
        </div>

        <div id="agConfirmText" style="padding:16px; color:rgba(15,23,42,.80); font-size:14px;">
          Tem certeza?
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 16px;
                    border-top:1px solid rgba(15,23,42,.08); background:#fff;">
          <button class="btn-ghost" type="button" id="agConfirmCancel">Cancelar</button>
          <button class="btn-danger" type="button" id="agConfirmOk">Excluir</button>
        </div>
      </div>
    `;

      document.body.appendChild(agConfirmWrap);

      agConfirmWrap.querySelector("#agConfirmClose")?.addEventListener("click", closeAgConfirm);
      agConfirmWrap.querySelector("#agConfirmCancel")?.addEventListener("click", closeAgConfirm);

      return agConfirmWrap;
    }

    function isAgConfirmOpen() {
      return !!agConfirmOpened;
    }

    function openAgConfirm(text, onOk) {
      ensureAgConfirm();
      showAgBackdrop();

      agConfirmWrap.style.opacity = "1";
      agConfirmWrap.style.pointerEvents = "auto";
      agConfirmOpened = true;

      const t = agConfirmWrap.querySelector("#agConfirmText");
      if (t) t.textContent = text;

      const okBtn = agConfirmWrap.querySelector("#agConfirmOk");
      okBtn?.replaceWith(okBtn.cloneNode(true));
      const okBtn2 = agConfirmWrap.querySelector("#agConfirmOk");

      const handler = async () => {
        okBtn2?.removeEventListener("click", handler);
        try {
          await onOk();
        } finally {
          closeAgConfirm();
        }
      };

      okBtn2?.addEventListener("click", handler);
    }

    function closeAgConfirm() {
      if (!agConfirmWrap) return;
      agConfirmWrap.style.opacity = "0";
      agConfirmWrap.style.pointerEvents = "none";
      agConfirmOpened = false;

      // se modal de edição estiver fechado, fecha backdrop
      const modalOpen = agModal && agModal.hidden === false;
      if (!modalOpen) hideAgBackdrop();
    }

    // ---------------------------
    // Menu ⋮ (portal no body) - evita overflow hidden
    // ---------------------------
    let agMenuEl = null;
    let agMenuForId = null;

    function closeAgMenu() {
      if (agMenuEl) agMenuEl.remove();
      agMenuEl = null;
      agMenuForId = null;
      document.removeEventListener("click", onDocClickCloseMenu, true);
      window.removeEventListener("scroll", closeAgMenu, true);
      window.removeEventListener("resize", closeAgMenu, true);
    }

    function onDocClickCloseMenu(e) {
      if (!agMenuEl) return;
      if (agMenuEl.contains(e.target)) return;
      closeAgMenu();
    }

    function openAgMenu(anchorBtn, evt) {
      const id = String(evt?.id || "").trim();
      if (!id) return;

      if (agMenuForId === id && agMenuEl) {
        closeAgMenu();
        return;
      }

      closeAgMenu();

      const r = anchorBtn.getBoundingClientRect();

      agMenuEl = document.createElement("div");
      agMenuEl.style.position = "fixed";
      agMenuEl.style.top = "0";
      agMenuEl.style.left = "0";
      agMenuEl.style.zIndex = "100000";
      agMenuEl.style.minWidth = "180px";
      agMenuEl.style.background = "#fff";
      agMenuEl.style.border = "1px solid rgba(15,23,42,.10)";
      agMenuEl.style.borderRadius = "12px";
      agMenuEl.style.boxShadow = "0 18px 55px rgba(0,0,0,.18)";
      agMenuEl.style.padding = "6px";
      agMenuEl.style.userSelect = "none";

      agMenuEl.innerHTML = `
      <button type="button" data-act="edit"
        style="width:100%;text-align:left;border:0;background:transparent;padding:10px 10px;border-radius:10px;cursor:pointer;font-weight:600;">
        Editar
      </button>
      <button type="button" data-act="del"
        style="width:100%;text-align:left;border:0;background:transparent;padding:10px 10px;border-radius:10px;cursor:pointer;color:#dc2626;font-weight:700;">
        Excluir
      </button>
    `;

      document.body.appendChild(agMenuEl);
      agMenuForId = id;

      // posiciona: tenta abrir pra baixo, se não couber, sobe
      const menuRect = agMenuEl.getBoundingClientRect();
      let top = r.bottom + 8;
      let left = Math.min(r.right - menuRect.width, window.innerWidth - menuRect.width - 10);
      left = Math.max(10, left);

      if (top + menuRect.height > window.innerHeight - 10) {
        top = r.top - menuRect.height - 8;
      }
      top = Math.max(10, top);

      agMenuEl.style.transform = `translate(${left}px, ${top}px)`;

      // eventos
      agMenuEl.addEventListener("click", (e) => {
        const b = e.target.closest("button[data-act]");
        if (!b) return;

        const act = b.getAttribute("data-act");
        closeAgMenu();

        if (act === "edit") return openEdit(evt);
        if (act === "del") return askDelete(evt);
      });

      setTimeout(() => {
        document.addEventListener("click", onDocClickCloseMenu, true);
        window.addEventListener("scroll", closeAgMenu, true);
        window.addEventListener("resize", closeAgMenu, true);
      }, 0);
    }

    // ---------------------------
    // Edit / Save / Delete (API)
    // ---------------------------
    function toLocalDateParts(d) {
      const Y = d.getFullYear();
      const M = String(d.getMonth() + 1).padStart(2, "0");
      const D = String(d.getDate()).padStart(2, "0");
      return `${Y}-${M}-${D}`;
    }
    function toHHMM(d) {
      const hh = String(d.getHours()).padStart(2, "0");
      const mm = String(d.getMinutes()).padStart(2, "0");
      return `${hh}:${mm}`;
    }

    function openEdit(evt) {
      // preencher campos
      const s = new Date(evt.start_at);
      const e = new Date(evt.end_at);

      if (agId) agId.value = evt.id || "";
      if (agTitle) agTitle.value = evt.title || "";
      if (agDate) agDate.value = toLocalDateParts(s);
      if (agStart) agStart.value = toHHMM(s);
      if (agEnd) agEnd.value = toHHMM(e);
      if (agReminder) agReminder.value = (evt.reminder_minutes ?? 30);
      if (agType) agType.value = (evt.type === "task" ? "task" : "meeting");

      openAgModal("Editar Compromisso");
    }

    function buildRFC3339FromLocal(dateYYYYMMDD, hhmm) {
      // monta "YYYY-MM-DDTHH:mm:00-03:00" (usa offset do browser)
      const [Y, M, D] = dateYYYYMMDD.split("-").map(Number);
      const [hh, mm] = hhmm.split(":").map(Number);
      const dt = new Date(Y, (M - 1), D, hh || 0, mm || 0, 0);

      // offset local do browser
      const offMin = -dt.getTimezoneOffset();
      const sign = offMin >= 0 ? "+" : "-";
      const abs = Math.abs(offMin);
      const oh = String(Math.floor(abs / 60)).padStart(2, "0");
      const om = String(abs % 60).padStart(2, "0");

      const y = dt.getFullYear();
      const m = String(dt.getMonth() + 1).padStart(2, "0");
      const da = String(dt.getDate()).padStart(2, "0");
      const H = String(dt.getHours()).padStart(2, "0");
      const Mi = String(dt.getMinutes()).padStart(2, "0");
      const S = "00";

      return `${y}-${m}-${da}T${H}:${Mi}:${S}${sign}${oh}:${om}`;
    }

    async function saveEdit() {
      const id = String(agId?.value || "").trim();
      const title = String(agTitle?.value || "").trim();
      const date = String(agDate?.value || "").trim();
      const start = String(agStart?.value || "").trim();
      const end = String(agEnd?.value || "").trim();
      const type = String(agType?.value || "meeting").trim();
      const remRaw = String(agReminder?.value || "").trim();

      if (!id) return toast("BUG: ID do evento não encontrado.", "error");
      if (!title) return toast("Título é obrigatório.", "error");
      if (!date) return toast("Data é obrigatória.", "error");
      if (!start) return toast("Início é obrigatório.", "error");
      if (!end) return toast("Fim é obrigatório.", "error");

      const start_at = buildRFC3339FromLocal(date, start);
      const end_at = buildRFC3339FromLocal(date, end);

      let reminder_minutes = null;
      if (remRaw !== "") {
        const n = Number(remRaw);
        reminder_minutes = Number.isFinite(n) ? Math.max(0, Math.trunc(n)) : 30;
      }

      agSave && (agSave.disabled = true);
      try {
        // ✅ action no body (corrige seu "Ação inválida")
        await apiPost("/api/agenda.php", {
          action: "update",
          id,
          title,
          type,
          start: start_at,
          end: end_at,
          reminder_minutes,
          timezone: "America/Sao_Paulo",
        });

        toast("Compromisso atualizado.", "success");
        closeAgModal();
        await bootMonth(); // recarrega mês/itens e renderiza
      } catch (err) {
        toast(err.message, "error");
      } finally {
        agSave && (agSave.disabled = false);
      }
    }

    agSave && agSave.addEventListener("click", saveEdit);

    function askDelete(evt) {
      openAgConfirm(`Excluir o compromisso "${evt.title || "Compromisso"}"?`, async () => {
        // ✅ action no body
        await apiPost("/api/agenda.php", { action: "delete", id: evt.id });
        toast("Compromisso excluído.", "success");
        await bootMonth();
      });
    }

    // ---------------------------
    // Clicks (calendário + lista)
    // ---------------------------
    grid.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-date]");
      if (!btn) return;
      const [Y, M, D] = btn.getAttribute("data-date").split("-").map(Number);
      state.selected = new Date(Y, M - 1, D);
      closeAgMenu();
      renderCalendar();
      renderDayPanel();
    });

    dayList.addEventListener("click", (e) => {
      const more = e.target.closest("button[data-act='more']");
      if (!more) return;

      const row = e.target.closest(".agenda-evt[data-id]");
      if (!row) return;

      const id = row.getAttribute("data-id");
      const key = ymd(state.selected);
      const items = state.byDate.get(key) || [];
      const evt = items.find((x) => String(x.id) === String(id));
      if (!evt) return;

      openAgMenu(more, evt);
    });

    btnPrev && btnPrev.addEventListener("click", async () => {
      closeAgMenu();
      state.view = new Date(state.view.getFullYear(), state.view.getMonth() - 1, 1);
      await bootMonth();
    });

    btnNext && btnNext.addEventListener("click", async () => {
      closeAgMenu();
      state.view = new Date(state.view.getFullYear(), state.view.getMonth() + 1, 1);
      await bootMonth();
    });

    btnToday && btnToday.addEventListener("click", async () => {
      closeAgMenu();
      const t = new Date();
      state.view = new Date(t.getFullYear(), t.getMonth(), 1);
      state.selected = t;
      await bootMonth();
    });

    // ESC e clique no backdrop
    document.addEventListener("keydown", (e) => {
      if (window.__PAGE__ !== "agenda") return;
      if (e.key === "Escape") {
        closeAgMenu();
        if (isAgConfirmOpen()) closeAgConfirm();
        closeAgModal();
      }
    });

    agBackdrop && agBackdrop.addEventListener("click", () => {
      closeAgMenu();
      if (isAgConfirmOpen()) {
        closeAgConfirm();
        return;
      }
      closeAgModal();
    });

    async function bootMonth() {
      try {
        await loadMonth();
        renderCalendar();
        renderDayPanel();
      } catch (err) {
        toast(err.message, "error");
      }
    }

    (async () => {
      state.view = new Date(state.view.getFullYear(), state.view.getMonth(), 1);
      await bootMonth();
    })();
  })();

  // =========================================================
  // 4.5) Page: Dashboard (sumir filtros fora do mês vigente)
  // =========================================================
  (function initDashboardPage() {
    if (window.__PAGE__ !== "dashboard") return;

    // container dos filtros (Semana / Mês / Hoje)
    const filters = document.getElementById("periodFilters");

    // botões de navegar mês (no topo do dashboard)
    const btnPrev = document.getElementById("btnPrevMonth");
    const btnNext = document.getElementById("btnNextMonth");

    if (!filters) return;

    function isSameMonth(a, b) {
      return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth()
      );
    }

    // ✅ você precisa ter alguma "data do mês em tela"
    // aqui começamos no mês atual, e atualizamos quando clicar prev/next
    let viewDate = new Date();

    function apply() {
      const now = new Date();
      const isCurrent = isSameMonth(viewDate, now);
      filters.style.display = isCurrent ? "" : "none";
    }

    function goPrev() {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
      apply();
    }

    function goNext() {
      viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
      apply();
    }

    // liga nos botões (se existirem)
    btnPrev && btnPrev.addEventListener("click", goPrev);
    btnNext && btnNext.addEventListener("click", goNext);

    // estado inicial (Janeiro = mostra; Dez/Fev = some)
    apply();
  })();

  /* =========================================================
   5) Page: Transactions (Transações)
   ========================================================= */

  (function initTransactionsPage() {
    if (window.__PAGE__ !== "transactions") return;

    // ---- helpers ----
    const $ = (id) => document.getElementById(id);
    const escapeHtml = window.escapeHtml;
    const escapeAttr = (s) => escapeHtml(s).replaceAll("`", "&#096;");
    const toast = window.toast;

    async function apiGet(url) {
      return window.apiGet(url);
    }

    async function apiPost(url, body) {
      return window.apiPost(url, body);
    }

    // ---- DOM ----
    const body = $("transactionsBody");
    const tabs = $("txTabs");
    const search = $("txSearch");
    const btnPrev = $("txPrev");
    const btnNext = $("txNext");
    const pagesEl = $("txPages");

    // drawer/modal
    const backdrop = $("txBackdrop");
    const modal = $("txModal");
    const modalTitle = $("txModalTitle");
    const btnClose = $("txClose");
    const btnCancel = $("txCancel");
    const btnSave = $("txSave");

    // fields
    const fId = $("txId");
    const fItem = $("txItem");
    const fAmount = $("txAmount");
    const fDate = $("txDate");
    const fType = $("txType");
    const fStatus = $("txStatus");
    const fCategory = $("txCategory");

    if (!body || !tabs || !search || !btnPrev || !btnNext || !pagesEl) return;

    // ---- state ----
    const state = {
      items: [],
      categories: [],
      catById: new Map(),
      loading: false,
      page: 1,
      perPage: 10,
      filter: "all",
    };

    function moneyBR(n) {
      const v = Number(n || 0);
      return v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
    }

    function parseMoney(v) {
      const s = String(v ?? "").trim();
      const n = Number(s);
      return Number.isFinite(n) ? n : 0;
    }

    function dateBR(yyyy_mm_dd) {
      const s = String(yyyy_mm_dd || "");
      const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
      if (!m) return s;
      return `${m[3]}/${m[2]}/${m[1]}`;
    }

    function typePill(type) {
      if (type === "income") return `<span class="pill pill-green">Receita</span>`;
      if (type === "expense") return `<span class="pill pill-red">Despesa</span>`;
      return `<span class="pill">—</span>`;
    }

    function statusPill(status) {
      if (status === "paid") return `<span class="pill pill-green">Pago</span>`;
      if (status === "received") return `<span class="pill" style="background:rgba(59,130,246,.14);border-color:rgba(59,130,246,.22);color:#1d4ed8;">Recebido</span>`;
      if (status === "due") return `<span class="pill pill-yellow">A Pagar</span>`;
      if (status === "receivable") return `<span class="pill" style="background:rgba(99,102,241,.14);border-color:rgba(99,102,241,.22);color:#4338ca;">A Receber</span>`;
      return `<span class="pill">${escapeHtml(status || "—")}</span>`;
    }

    function valueColored(amount, type) {
      const v = moneyBR(amount);
      if (type === "expense") return `<span class="red">${escapeHtml(v)}</span>`;
      return `<span class="green">${escapeHtml(v)}</span>`;
    }

    function matchesFilter(item) {
      if (state.filter === "all") return true;
      if (state.filter === "paid") return item.status === "paid";
      if (state.filter === "received") return item.status === "received";
      if (state.filter === "payable") return item.status === "due";
      if (state.filter === "receivable") return item.status === "receivable";
      return true;
    }

    function matchesSearch(item) {
      const q = String(search.value || "").trim().toLowerCase();
      if (!q) return true;

      const desc = String(item.item || "").toLowerCase();
      const cat = String(item.category_name || "").toLowerCase();
      return desc.includes(q) || cat.includes(q);
    }

    function computeView() {
      const filtered = state.items
        .map((t) => ({
          ...t,
          category_name: state.catById.get(t.category_id) || t.category_name || "",
        }))
        .filter(matchesFilter)
        .filter(matchesSearch);

      const totalPages = Math.max(1, Math.ceil(filtered.length / state.perPage));
      state.page = Math.min(state.page, totalPages);

      const start = (state.page - 1) * state.perPage;
      const pageItems = filtered.slice(start, start + state.perPage);

      return { filtered, pageItems, totalPages };
    }

    function renderPager(totalPages) {
      pagesEl.innerHTML = "";

      const max = 7;
      let from = Math.max(1, state.page - 3);
      let to = Math.min(totalPages, from + (max - 1));
      from = Math.max(1, to - (max - 1));

      for (let p = from; p <= to; p++) {
        const el = document.createElement("div");
        el.className = "pager-pill" + (p === state.page ? " active" : "");
        el.textContent = String(p);
        el.style.cursor = "pointer";
        el.addEventListener("click", () => {
          state.page = p;
          render();
        });
        pagesEl.appendChild(el);
      }

      const hasPages = totalPages > 1;

      btnPrev.disabled = !hasPages || state.page <= 1;
      btnNext.disabled = !hasPages || state.page >= totalPages;

      btnPrev.classList.toggle("is-disabled", btnPrev.disabled);
      btnNext.classList.toggle("is-disabled", btnNext.disabled);

    }

    function render() {
      const { pageItems, totalPages } = computeView();

      if (!pageItems.length) {
        const isLoadingEmpty = state.loading && (!state.items || state.items.length === 0);
        const msg = isLoadingEmpty ? "Carregando transações..." : "Nenhuma transação encontrada.";

        body.innerHTML = `
    <tr>
      <td colspan="7" class="muted" style="padding:18px 14px;">
        ${msg}
      </td>
    </tr>
  `;
        return;
      }

      body.innerHTML = pageItems
        .map((t) => {
          const catName = t.category_name || "-";
          return `
          <tr data-id="${escapeAttr(t.id)}">
            <td style="font-weight:600;">${escapeHtml(t.item || "")}</td>
            <td class="right">${valueColored(t.amount, t.type)}</td>
            <td class="muted">${escapeHtml(catName)}</td>
            <td>${typePill(t.type)}</td>
            <td>${statusPill(t.status)}</td>
            <td class="muted">${escapeHtml(dateBR(t.date))}</td>
            <td class="right">
              <button class="icon-action edit" data-act="edit" title="Editar" type="button">
                <i class="fa-regular fa-pen-to-square"></i>
              </button>
              <button class="icon-action delete" data-act="del" title="Excluir" type="button">
                <i class="fa-regular fa-trash-can"></i>
              </button>
            </td>
          </tr>
        `;
        })
        .join("");

      renderPager(totalPages);
    }

    // ---- Tabs behavior ----
    function setActiveTab(filter) {
      state.filter = filter;
      state.page = 1;

      tabs.querySelectorAll("a[data-filter]").forEach((a) => {
        a.classList.toggle("active", a.getAttribute("data-filter") === filter);
      });

      render();
    }

    tabs.addEventListener("click", (e) => {
      const a = e.target.closest("a[data-filter]");
      if (!a) return;
      e.preventDefault();
      setActiveTab(a.getAttribute("data-filter") || "all");
    });

    // ---- Search ----
    search.addEventListener("input", () => {
      state.page = 1;
      render();
    });

    // ---- Pager ----
    btnPrev.addEventListener("click", () => {
      state.page = Math.max(1, state.page - 1);
      render();
    });

    btnNext.addEventListener("click", () => {
      const { totalPages } = computeView();
      state.page = Math.min(totalPages, state.page + 1);
      render();
    });

    // ---- Drawer (Editar) ----
    function showBackdrop() {
      if (!backdrop) return;
      backdrop.hidden = false;
      backdrop.classList.add("show");
    }
    function hideBackdrop() {
      if (!backdrop) return;
      backdrop.classList.remove("show");
      backdrop.hidden = true;
    }

    function openDrawer(title) {
      if (!modal) return;
      modalTitle && (modalTitle.textContent = title || "Editar Transação");

      showBackdrop();
      modal.hidden = false;
      modal.classList.add("open");
      modal.style.transform = "translateX(0)";

      setTimeout(() => fItem && fItem.focus(), 50);
    }

    function closeDrawer() {
      if (!modal) return;

      modal.style.transform = "translateX(110%)";
      modal.classList.remove("open");

      setTimeout(() => {
        modal.hidden = true;
        hideBackdrop();
      }, 160);

      if (fId) fId.value = "";
    }

    btnClose && btnClose.addEventListener("click", closeDrawer);
    btnCancel && btnCancel.addEventListener("click", closeDrawer);

    document.addEventListener("keydown", (e) => {
      if (window.__PAGE__ !== "transactions") return;
      if (e.key === "Escape") {
        closeConfirm();
        closeDrawer();
      }
    });

    backdrop &&
      backdrop.addEventListener("click", () => {
        if (isConfirmOpen()) {
          closeConfirm();
          return;
        }
        closeDrawer();
      });

    function fillCategorySelect() {
      if (!fCategory) return;
      fCategory.innerHTML = `<option value="">Sem categoria</option>`;
      state.categories.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.id;
        opt.textContent = c.name;
        fCategory.appendChild(opt);
      });
    }

    function openEdit(item) {
      if (!item) return;
      if (fId) fId.value = item.id || "";
      if (fItem) fItem.value = item.item || "";
      if (fAmount) fAmount.value = item.amount ?? "";
      if (fDate) fDate.value = item.date || "";
      if (fType) fType.value = item.type || "expense";
      if (fStatus) fStatus.value = item.status || "paid";
      if (fCategory) fCategory.value = item.category_id || "";
      openDrawer("Editar Transação");
    }

    async function save() {
      const payload = {
        id: (fId?.value || "").trim(),
        item: (fItem?.value || "").trim(),
        amount: parseMoney(fAmount?.value || 0),
        type: (fType?.value || "expense").trim(),
        status: (fStatus?.value || "paid").trim(),
        date: (fDate?.value || "").trim(),
        category_id: (fCategory?.value || "").trim() || null,
      };

      if (!payload.item) {
        toast("Descrição é obrigatória.", "error");
        fItem && fItem.focus();
        return;
      }
      if (!payload.date) {
        toast("Data é obrigatória.", "error");
        fDate && fDate.focus();
        return;
      }

      btnSave && (btnSave.disabled = true);
      try {
        await apiPost("/api/transactions.php?action=upsert", payload);
        toast("Transação salva.", "success");
        closeDrawer();
        await loadAll();
      } catch (err) {
        toast(err.message, "error");
      } finally {
        btnSave && (btnSave.disabled = false);
      }
    }

    btnSave && btnSave.addEventListener("click", save);

    // ---- Confirm modal (Excluir) ----
    let confirmWrap = null;
    let confirmOpened = false;

    function ensureConfirmModal() {
      if (confirmWrap) return confirmWrap;

      confirmWrap = document.createElement("div");
      confirmWrap.id = "txConfirmModal";
      confirmWrap.style.position = "fixed";
      confirmWrap.style.inset = "0";
      confirmWrap.style.display = "flex";
      confirmWrap.style.alignItems = "center";
      confirmWrap.style.justifyContent = "center";
      confirmWrap.style.opacity = "0";
      confirmWrap.style.pointerEvents = "none";
      confirmWrap.style.transition = "opacity .15s ease";
      confirmWrap.style.padding = "16px";
      confirmWrap.style.zIndex = "100000";

      confirmWrap.innerHTML = `
      <div style="width:min(520px, 100%); background:#fff; border:1px solid rgba(15,23,42,.10);
                  border-radius:18px; box-shadow:0 25px 70px rgba(0,0,0,.22); overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;
                    padding:14px 16px; border-bottom:1px solid rgba(15,23,42,.08);">
          <div style="font-weight:800; font-size:14px;">Confirmar exclusão</div>
          <button type="button" id="txConfirmClose"
            style="width:36px;height:36px;border-radius:999px;border:1px solid rgba(15,23,42,.12);
                   background:#fff;cursor:pointer;font-size:20px;line-height:0;">✕</button>
        </div>

        <div id="txConfirmText" style="padding:16px; color:rgba(15,23,42,.80); font-size:14px;">
          Tem certeza?
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; padding:14px 16px;
                    border-top:1px solid rgba(15,23,42,.08); background:#fff;">
          <button class="btn-ghost" type="button" id="txConfirmCancel">Cancelar</button>
          <button class="btn-danger" type="button" id="txConfirmOk">Excluir</button>
        </div>
      </div>
    `;

      document.body.appendChild(confirmWrap);

      confirmWrap.querySelector("#txConfirmClose")?.addEventListener("click", closeConfirm);
      confirmWrap.querySelector("#txConfirmCancel")?.addEventListener("click", closeConfirm);

      return confirmWrap;
    }

    function isConfirmOpen() {
      return !!confirmOpened;
    }

    function openConfirm(text, onOk) {
      ensureConfirmModal();
      showBackdrop();

      confirmWrap.style.opacity = "1";
      confirmWrap.style.pointerEvents = "auto";
      confirmOpened = true;

      const t = confirmWrap.querySelector("#txConfirmText");
      if (t) t.textContent = text;

      const okBtn = confirmWrap.querySelector("#txConfirmOk");
      okBtn?.replaceWith(okBtn.cloneNode(true));
      const okBtn2 = confirmWrap.querySelector("#txConfirmOk");

      const handler = async () => {
        okBtn2?.removeEventListener("click", handler);
        try {
          await onOk();
        } finally {
          closeConfirm();
        }
      };

      okBtn2?.addEventListener("click", handler);
    }

    function closeConfirm() {
      if (!confirmWrap) return;
      confirmWrap.style.opacity = "0";
      confirmWrap.style.pointerEvents = "none";
      confirmOpened = false;

      const drawerOpen = modal && modal.hidden === false;
      if (!drawerOpen) hideBackdrop();
    }

    async function doDelete(item) {
      openConfirm(`Excluir a transação "${item.item || ""}"?`, async () => {
        await apiPost("/api/transactions.php?action=delete", { id: item.id });
        toast("Transação excluída.", "success");
        await loadAll();
      });
    }

    body.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-act]");
      if (!btn) return;

      const tr = e.target.closest("tr[data-id]");
      if (!tr) return;

      const id = tr.getAttribute("data-id");
      const item = state.items.find((x) => String(x.id) === String(id));
      if (!item) return;

      const act = btn.getAttribute("data-act");
      if (act === "edit") return openEdit(item);
      if (act === "del") return doDelete(item);
    });

    async function loadCategories() {
      try {
        const data = await apiGet("/api/categories.php?action=list");
        state.categories = data.items || [];
        state.catById = new Map(state.categories.map((c) => [String(c.id), c.name || ""]));
        fillCategorySelect();
        cacheSet("meuestagiario:categories:list", state.categories);
      } catch (e) {
        console.warn("Falha ao carregar categorias", e);
      }
    }

    async function loadTransactions() {
      const data = await apiGet("/api/transactions.php?action=list");
      state.items = (data.items || []).map((t) => ({
        ...t,
        category_id: t.category_id ? String(t.category_id) : "",
      }));
      cacheSet("meuestagiario:transactions:list", state.items);
    }

    async function loadAll() {
      // se tiver cache recente, renderiza instantâneo e atualiza em background
      const cachedCats = cacheGet("meuestagiario:categories:list", 60_000);
      if (Array.isArray(cachedCats) && cachedCats.length) {
        state.categories = cachedCats;
        state.catById = new Map(state.categories.map((c) => [String(c.id), c.name || ""]));
        fillCategorySelect();
      }

      const cachedTx = cacheGet("meuestagiario:transactions:list", 60_000);
      if (Array.isArray(cachedTx) && cachedTx.length) {
        state.items = cachedTx;
        state.loading = false;
        render();
      }

      // se não tem cache, mantém loading até chegar
      if (!state.items.length) state.loading = true;
      render();

      try {
        await Promise.all([loadCategories(), loadTransactions()]);
      } finally {
        state.loading = false;
        render();
      }
    }

    // Importante: marca loading ANTES do primeiro render do setActiveTab
    // para não mostrar "Nenhuma transação" por 1-2s.
    state.loading = true;
    setActiveTab("all");
    loadAll();

  })();

  /* =========================================================
   PAGE: ACCOUNT — ALTERAR SENHA (MODAL)
   ========================================================= */
  (function () {
    if (window.__PAGE__ !== "account") return;

    const btn = document.getElementById("btnChangePassword");
    if (!btn) return;

    const backdrop = document.getElementById("modalBackdrop");
    const modal = document.getElementById("appModal");
    const titleEl = document.getElementById("modalTitle");
    const bodyEl = document.getElementById("modalBody");
    const footEl = document.getElementById("modalFoot");
    const closeBtn = document.getElementById("modalClose");

    function openModal() {
      titleEl.textContent = "Alterar senha";

      bodyEl.innerHTML = `
      <div class="form-row">
        <label>Nova senha</label>
        <input id="newPassword" type="password" placeholder="Mínimo 8 caracteres" />
      </div>

      <div class="form-row" style="margin-top:12px;">
        <label>Confirmar nova senha</label>
        <input id="confirmPassword" type="password" placeholder="Repita a nova senha" />
      </div>

      <div id="passError" class="muted small" style="display:none;color:#dc2626;margin-top:10px;"></div>
    `;

      footEl.innerHTML = `
      <button class="btn-ghost" id="cancelPass" type="button">Cancelar</button>
      <button class="cat-btn primary" id="savePass" type="button">Salvar</button>
    `;

      backdrop.hidden = false;
      modal.hidden = false;

      document.getElementById("cancelPass").onclick = closeModal;
      document.getElementById("savePass").onclick = savePassword;
    }

    async function savePassword() {
      const p1 = document.getElementById("newPassword").value.trim();
      const p2 = document.getElementById("confirmPassword").value.trim();
      const err = document.getElementById("passError");

      err.style.display = "none";

      if (p1.length < 8) {
        err.textContent = "A senha deve ter no mínimo 8 caracteres.";
        err.style.display = "block";
        return;
      }

      if (p1 !== p2) {
        err.textContent = "As senhas não conferem.";
        err.style.display = "block";
        return;
      }

      try {
        await apiPost("/api/account.php", {
          action: "change_password",
          password: p1,
          password_confirm: p2,
        });

        toast("Senha alterada com sucesso!", "success");
        closeModal();
      } catch (e) {
        err.textContent = e.message || "Erro ao alterar senha.";
        err.style.display = "block";
      }
    }

    function closeModal() {
      modal.hidden = true;
      backdrop.hidden = true;
      bodyEl.innerHTML = "";
      footEl.innerHTML = "";
    }

    btn.addEventListener("click", openModal);
    closeBtn && closeBtn.addEventListener("click", closeModal);
    backdrop && backdrop.addEventListener("click", closeModal);
  })();

  /* =========================================================
   PAGE: ACCOUNT — SALVAR PERFIL (NOME / TELEFONE)
   ========================================================= */
  (function () {
    if (window.__PAGE__ !== "account") return;

    const btnSave = document.getElementById("btnSaveProfile");
    const inputName = document.getElementById("accFullName");
    const inputPhone = document.getElementById("accPhone");

    if (!btnSave) return;

    btnSave.addEventListener("click", async () => {
      if (!inputName) {
        toast("BUG: campo NOME não encontrado (accFullName).", "error");
        return;
      }
      if (!inputPhone) {
        toast("BUG: campo TELEFONE não encontrado (accPhone).", "error");
        return;
      }

      const full_name = String(inputName.value || "").trim();
      const phone_raw = String(inputPhone.value || "").trim();

      if (!full_name) {
        toast("Preencha seu nome completo.", "error");
        inputName.focus();
        return;
      }

      let phone = phone_raw.replace(/\D+/g, "");
      if (phone.length >= 12 && phone.startsWith("55")) {
        phone = phone.slice(2);
      }

      if (phone && !(phone.length === 10 || phone.length === 11)) {
        toast("Telefone inválido. Use DDD + número.", "error");
        inputPhone.focus();
        return;
      }

      btnSave.disabled = true;

      try {
        await apiPost("/api/profile.php", {
          action: "update_profile",
          full_name,
          phone,
        });

        inputPhone.value = phone;
        toast("Perfil atualizado com sucesso!", "success");
      } catch (e) {
        toast(e.message || "Erro ao salvar perfil.", "error");
      } finally {
        btnSave.disabled = false;
      }
    });
  })();

  /* =========================================================
   PAGE: ACCOUNT — INTEGRAÇÕES (TOGGLE GOOGLE CALENDAR)
   - SILENCIOSO (sem toast)
   ========================================================= */
  (function () {
    if (window.__PAGE__ !== "account") return;

    const toggle = document.getElementById("gcToggle");
    const area = document.getElementById("gcArea");
    if (!toggle || !area) return;

    function setVisible(on) {
      area.classList.toggle("is-hidden", !on);
    }

    // estado inicial
    setVisible(toggle.checked);

    toggle.addEventListener("change", async () => {
      const on = toggle.checked;
      setVisible(on);

      try {
        await apiPost("/api/integrations.php", {
          action: "set_google_calendar_enabled",
          enabled: on,
        });
        // silêncio total
      } catch (e) {
        // reverte visualmente
        toggle.checked = !on;
        setVisible(!on);
      }
    });
  })();

  /* =========================================================
   PAGE: ACCOUNT — GOOGLE CALENDAR UI (carregar conta real)
   - GET /api/google/accounts.php  -> { ok:true, account:{id, google_email,...} } ou { ok:true, account:null }
   ========================================================= */
  async function gcalLoadUI() {
    const connected = document.getElementById("gcConnected");
    const empty = document.getElementById("gcEmpty");
    const rmBtn = document.getElementById("gcRemoveBtn");

    if (!connected || !empty || !rmBtn) return;

    // aplica UI a partir de um objeto account (ou null)
    function applyGcalUI(account) {
      const has = !!account;

      if (has) {
        connected.classList.remove("is-hidden");
        empty.classList.add("is-hidden");

        const emailEl = connected.querySelector("[data-gcal-email]");
        if (emailEl) emailEl.textContent = account.google_email || "Conta Google";

        rmBtn.dataset.id = account.id || "";
      } else {
        connected.classList.add("is-hidden");
        empty.classList.remove("is-hidden");
        rmBtn.dataset.id = "";
      }
    }

    // 1) cola na tela com cache (até 60s)
    const cached = cacheGet("meuestagiario:gcal:account", 60_000);
    if (cached && typeof cached === "object") {
      applyGcalUI(cached.account || null);
    }

    // 2) atualiza por baixo dos panos
    try {
      const res = await fetch((window.__BASE__ || "") + "/api/google/accounts.php", {
        credentials: "include",
        headers: { Accept: "application/json" },
      });

      const data = await res.json();
      const account = (data && data.ok && data.account) ? data.account : null;

      // salva cache novo
      cacheSet("meuestagiario:gcal:account", { account });

      applyGcalUI(account);
    } catch (e) {
      // silêncio: mantém o que já estava colado na tela
    }
  }


  /* =========================================================
   PAGE: ACCOUNT — REMOVER INTEGRAÇÃO GOOGLE CALENDAR (DE VERDADE)
   - DELETE /api/google/accounts.php { id }
   - toggle permanece ON
   ========================================================= */
  (function () {
    if (window.__PAGE__ !== "account") return;

    document.addEventListener("click", async (e) => {
      const btn = e.target.closest("#gcRemoveBtn");
      if (!btn) return;

      const id = String(btn.dataset.id || "").trim();
      if (!id) {
        // se não tem id, só re-render
        await gcalLoadUI();
        return;
      }

      btn.disabled = true;
      try {
        const url =
          (window.__BASE__ || "") +
          "/api/google/accounts.php?id=" +
          encodeURIComponent(id);

        const res = await fetch(url, {
          method: "DELETE",
          credentials: "include",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
          },
          // manda o id também no body (mais compatível)
          body: JSON.stringify({ id }),
        });

        // lê como texto primeiro (pra capturar erro real mesmo se vier HTML)
        const txt = await res.text();

        let data = {};
        try {
          data = JSON.parse(txt);
        } catch (_) {
          // se não for JSON, data fica {}
        }

        if (!res.ok || !data.ok) {
          throw new Error(data.error || txt.slice(0, 200) || "Falha ao remover");
        }

        // sucesso -> recarrega UI
        await gcalLoadUI();

      } catch (err) {
        toast(err.message || "Erro ao remover", "error");
        // mantém UI como estava
      } finally {
        btn.disabled = false;
      }
    });

    (function () {
      if (window.__PAGE__ !== "account") return;

      const run = () => gcalLoadUI();
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", run);
      } else {
        run();
      }
    })();

  })();

  /* =========================================================
   PAGE: ACCOUNT — PLANOS (billing_cycle)
   - toggle mensal/anual
   - persiste no banco via /api/profile.php (update_plan)
   ========================================================= */
  (function () {
    if (window.__PAGE__ !== "account") return;

    const toggle = document.getElementById("planBillingToggle");
    const elPrice = document.getElementById("planPrice");
    const elPeriod = document.getElementById("planPeriod");
    if (!toggle || !elPrice || !elPeriod) return;

    function applyUI(isAnnual) {
      elPrice.textContent = isAnnual ? "19,90" : "29,90";
      elPeriod.textContent = isAnnual ? "/ano" : "/mês";
    }

    // estado inicial (vem do PHP)
    applyUI(toggle.checked);

    let busy = false;

    toggle.addEventListener("change", async () => {
      if (busy) return;
      busy = true;

      const newIsAnnual = toggle.checked;
      const cycle = newIsAnnual ? "annual" : "monthly";

      // Atualiza UI imediatamente (fica responsivo)
      applyUI(newIsAnnual);

      try {
        await apiPost("/api/profile.php", {
          action: "update_plan",
          billing_cycle: cycle,
        });

        toast("Plano atualizado!", "success");
      } catch (e) {
        // rollback UI + toggle
        toggle.checked = !newIsAnnual;
        applyUI(toggle.checked);

        toast(e.message || "Erro ao salvar plano.", "error");
      } finally {
        busy = false;
      }
    });

  })();

})();