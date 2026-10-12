/* FEI site behaviour — WordPress port of the Astro site.js.
 * Keeps: storage keys (fei_utms, fei_landing, fei_doc_referrer, fei_prereg), payload
 * contract, atomic last-touch attribution, dataLayer `form_conversion` event, modal
 * ids/prefixes (am/af/lp/pr). Changes: POSTs to the WP REST intake (FEI.endpoint),
 * IP is captured server-side (no api.ipify.org), honeypot + timing token added.
 */
(function () {
  "use strict";
  var CFG = window.FEI || {};
  var LEAD_URL = CFG.endpoint || "/wp-json/fei/v1/lead";
  var ERROR_URL = CFG.errorEndpoint || "/wp-json/fei/v1/lead-error";
  var LANG = CFG.lang || (document.documentElement.lang || "en").slice(0, 2);
  var I18N = CFG.i18n || {};

  /* ---------- Attribution (identical semantics to site.js) ---------- */
  var UTM_KEYS = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "utm_id", "utm_platform"];
  var CLICK_ID_KEYS = ["gclid", "gbraid", "wbraid", "fbclid", "msclkid", "ttclid", "li_fat_id", "twclid", "sccid", "epik", "irclickid"];
  var TOUCH_KEYS = UTM_KEYS.concat(CLICK_ID_KEYS);
  var lsGet = function (k) { try { return localStorage.getItem(k) || ""; } catch (e) { return ""; } };
  var lsSet = function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} };

  var feiUtms = {};
  try { feiUtms = JSON.parse(lsGet("fei_utms") || "{}") || {}; } catch (e) { feiUtms = {}; }

  var externalReferrer = function () {
    try {
      var r = document.referrer || "";
      if (!r) return "";
      return new URL(r).origin === window.location.origin ? "" : r;
    } catch (e) { return ""; }
  };
  var landingUrl = function () {
    try {
      var u = new URL(window.location.href);
      ["q", "s", "email", "phone", "name"].forEach(function (k) { u.searchParams.delete(k); });
      var qs = u.searchParams.toString();
      return u.origin + u.pathname + (qs ? "?" + qs : "");
    } catch (e) { return String(window.location.href).split("#")[0]; }
  };

  var feiLanding = lsGet("fei_landing");
  var feiDocReferrer = lsGet("fei_doc_referrer");
  try {
    var qp = new URLSearchParams(window.location.search);
    var hasTouch = TOUCH_KEYS.some(function (k) { return qp.get(k); });
    var hasStoredTouch = TOUCH_KEYS.some(function (k) { return feiUtms[k]; });
    if (hasTouch) {
      feiUtms = {};
      TOUCH_KEYS.forEach(function (k) { feiUtms[k] = qp.get(k) || ""; });
      feiLanding = landingUrl();
      feiDocReferrer = externalReferrer();
      lsSet("fei_utms", JSON.stringify(feiUtms));
      lsSet("fei_landing", feiLanding);
      lsSet("fei_doc_referrer", feiDocReferrer);
    } else if (!feiLanding && !hasStoredTouch) {
      feiLanding = landingUrl();
      feiDocReferrer = externalReferrer();
      lsSet("fei_landing", feiLanding);
      lsSet("fei_doc_referrer", feiDocReferrer);
    }
  } catch (e) {}

  var readCookie = function (name) {
    try {
      var m = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
      return m ? decodeURIComponent(m[1]) : "";
    } catch (e) { return ""; }
  };

  var reportSubmitError = function (info) {
    try {
      fetch(ERROR_URL, {
        method: "POST", headers: { "Content-Type": "application/json" }, keepalive: true,
        body: JSON.stringify({
          kind: "lead_submit_error", at: new Date().toISOString(),
          reason: info.reason || "", httpStatus: info.httpStatus || 0, message: info.message || "",
          responseBody: String(info.responseBody || "").slice(0, 2000), formId: info.formId || "",
          pageUrl: window.location.href, userAgent: navigator.userAgent, payload: info.payload || {}
        })
      }).catch(function () {});
    } catch (e) {}
  };

  var baseAttribution = function () {
    var out = {
      referrer: feiLanding || landingUrl(),
      documentReferrer: feiDocReferrer,
      fbc: readCookie("_fbc"),
      fbp: readCookie("_fbp"),
      lang: LANG,
      pageUrl: window.location.href
    };
    TOUCH_KEYS.forEach(function (k) { out[k] = feiUtms[k] || ""; });
    return out;
  };

  var postLead = function (payload, formId) {
    var hp = document.querySelector("#" + formId + " [name=website]");
    var t0 = document.querySelector("#" + formId + " .fei-t0");
    var ts = document.querySelector("#" + formId + " [name=cf-turnstile-response]");
    payload.website = hp ? hp.value : "";
    payload._t0 = t0 ? t0.value : "";
    if (ts && ts.value) payload["cf-turnstile-response"] = ts.value;
    payload.formId = formId;
    return fetch(LEAD_URL, {
      method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify(payload), credentials: "same-origin"
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = {};
        try { data = JSON.parse(text || "{}"); } catch (e) {}
        if (res.ok && data.ok !== false) return data;
        var err = new Error("HTTP " + res.status);
        err.httpStatus = res.status; err.responseBody = text; err.fieldErrors = data.errors || null;
        throw err;
      });
    });
  };

  // Stamp the timing token on every form (anti-bot: too-fast submissions are dropped).
  document.querySelectorAll(".fei-t0").forEach(function (el) { el.value = String(Date.now()); });

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  var selectedProgramType = function (sel) {
    if (!sel || sel.selectedIndex < 0) return "";
    var opt = sel.options[sel.selectedIndex];
    return (opt && opt.getAttribute("data-program-type")) || "";
  };

  var initPhone = function (input) {
    if (!input || !window.intlTelInput) return null;
    try {
      return window.intlTelInput(input, {
        initialCountry: "us", onlyCountries: ["us"], allowDropdown: false,
        separateDialCode: true, strictMode: true, formatAsYouType: true,
        loadUtils: function () { return Promise.resolve(); }
      });
    } catch (e) { return null; }
  };

  /* ---------- Apply form (am / af / lp) ---------- */
  function initApplyForm(cfg) {
    var form = document.getElementById(cfg.formId);
    if (!form) return null;
    var byId = function (s) { return document.getElementById(cfg.prefix + "-" + s); };
    var progSelect = byId("program"), progField = byId("program-field"), progTypeHidden = byId("program-type");
    var titleEl = cfg.titleId ? document.getElementById(cfg.titleId) : null;
    var progMeta = document.getElementById("feiProgramMeta");
    if (progMeta && progField && progSelect) {
      var pName = progMeta.getAttribute("data-program") || "";
      var pType = progMeta.getAttribute("data-program-type") || "";
      if (pName) {
        progSelect.value = pName;
        if (progSelect.value !== pName) {
          var inj = document.createElement("option"); inj.value = pName; inj.textContent = pName;
          progSelect.appendChild(inj); progSelect.value = pName;
        }
        progField.hidden = true;
        if (titleEl) titleEl.textContent = (titleEl.getAttribute("data-apply-to") || I18N.applyTo || "Apply to") + " " + (progMeta.getAttribute("data-program-label") || pName);
      }
      if (pType && progTypeHidden) progTypeHidden.value = pType;
    }
    TOUCH_KEYS.forEach(function (k) {
      var input = byId(k.replace(/_/g, "-"));
      if (input && feiUtms[k]) input.value = feiUtms[k];
    });
    var phoneInput = byId("phone");
    var iti = initPhone(phoneInput);

    var lpStage = form.getAttribute("data-stage");
    var preReg = null;
    try { preReg = JSON.parse(sessionStorage.getItem("fei_prereg") || "null"); } catch (e) { preReg = null; }
    if (preReg) {
      var fill = function (s, v) { var el = byId(s); if (el && !el.value && v) el.value = v; };
      fill("first", preReg.firstName); fill("last", preReg.lastName); fill("email", preReg.email);
      if (preReg.phone && iti) { try { iti.setNumber(preReg.phone); } catch (e) {} }
      else if (preReg.phone && phoneInput && !phoneInput.value) phoneInput.value = preReg.phone;
      // Step-2 UX: jump straight to the first empty required field (zip).
      if (byId("zip") && !byId("zip").value) setTimeout(function () { try { byId("zip").focus({ preventScroll: false }); } catch (e) {} }, 300);
    }

    var summaryEl = byId("summary");
    var msgFor = function (key) { return form.getAttribute("data-msg-" + key) || ""; };
    var mark = function (field, isBad, msgKey, invalids, customMsg) {
      if (!field) return;
      field.classList.toggle("is-invalid", isBad);
      field.setAttribute("aria-invalid", String(isBad));
      var errEl = document.getElementById(field.id + "-error");
      if (errEl) { errEl.textContent = isBad ? (customMsg || msgFor(msgKey)) : ""; errEl.hidden = !isBad; }
      if (isBad) invalids.push(field);
    };

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var first = byId("first"), last = byId("last"), email = byId("email"), zip = byId("zip");
      var smsMarketing = byId("sms-marketing"), smsTransactional = byId("sms-transactional");
      var invalids = [];
      mark(first, !first.value.trim(), "required", invalids);
      mark(last, !last.value.trim(), "required", invalids);
      mark(email, !EMAIL_RE.test(email.value.trim()), "invalid-email", invalids);
      mark(zip, !/^\d{5}(-\d{4})?$/.test(zip.value.trim()), "invalid-zip", invalids);
      var phoneOk = iti ? iti.isValidNumber() : /\d{10}/.test(phoneInput.value.replace(/\D/g, ""));
      mark(phoneInput, !phoneOk, "invalid-phone", invalids);
      mark(progSelect, !progSelect.value, "select-program", invalids);
      if (invalids.length) {
        if (summaryEl) { summaryEl.textContent = msgFor("error-summary"); summaryEl.hidden = false; summaryEl.focus(); }
        else invalids[0].focus();
        return;
      }
      if (summaryEl) summaryEl.hidden = true;

      var payload = Object.assign({
        firstName: first.value.trim(), lastName: last.value.trim(), email: email.value.trim(),
        phone: iti ? iti.getNumber() : phoneInput.value.trim(), zip: zip.value.trim(),
        program: progSelect ? progSelect.value : "",
        programType: (progTypeHidden && progTypeHidden.value) || selectedProgramType(progSelect),
        smsMarketingConsent: smsMarketing ? smsMarketing.checked : false,
        smsTransactionalConsent: smsTransactional ? smsTransactional.checked : false,
        stage: lpStage || "full"
      }, baseAttribution());
      if (preReg && preReg.id) payload.preRegId = preReg.id;

      var submitBtn = form.querySelector('[type="submit"]');
      var errEl = byId("error");
      if (errEl) errEl.hidden = true;
      var btnHTML = submitBtn ? submitBtn.innerHTML : "";
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = I18N.sending || "Sending…"; }

      postLead(payload, cfg.formId).then(function (data) {
        try {
          window.dataLayer = window.dataLayer || [];
          window.dataLayer.push({
            event: "form_conversion", form_id: cfg.formId, program: payload.program,
            program_type: payload.programType, lead_id: payload.preRegId || (data && data.id) || "",
            lead_stage: payload.stage, lead_source: "wordpress"
          });
        } catch (gaErr) {}
        try { sessionStorage.removeItem("fei_prereg"); } catch (e) {}
        try {
          var nameEl = cfg.successNameId ? document.getElementById(cfg.successNameId) : null;
          if (nameEl) nameEl.textContent = first.value.trim().split(" ")[0] || (I18N.there || "there");
          if (typeof cfg.onSuccess === "function") cfg.onSuccess();
        } catch (uiErr) {
          reportSubmitError({ reason: "ui", message: String(uiErr && uiErr.message || uiErr), formId: cfg.formId, payload: payload });
        }
      }).catch(function (err) {
        if (err && err.fieldErrors) {
          var inv = [];
          var fieldMap = { firstName: first, lastName: last, email: email, phone: phoneInput, zip: zip, program: progSelect };
          Object.keys(err.fieldErrors).forEach(function (k) { if (fieldMap[k]) mark(fieldMap[k], true, "", inv, err.fieldErrors[k]); });
          if (inv.length) { if (summaryEl) { summaryEl.textContent = msgFor("error-summary"); summaryEl.hidden = false; summaryEl.focus(); } else inv[0].focus(); }
          if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = btnHTML; }
          return;
        }
        if (errEl) errEl.hidden = false;
        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = btnHTML; }
        reportSubmitError({
          reason: (err && err.httpStatus) ? "http" : "network", httpStatus: (err && err.httpStatus) || 0,
          responseBody: (err && err.responseBody) || "", message: (err && err.message) || String(err),
          formId: cfg.formId, payload: payload
        });
      });
    });
    return { focusFirst: function () { var f = byId("first"); if (f) f.focus(); } };
  }

  /* ---------- Focus trap / inert helpers ---------- */
  var setBackgroundInert = function (on, except) {
    Array.prototype.forEach.call(document.body.children, function (el) {
      if (el === except || el.tagName === "SCRIPT") return;
      if (on) { el.setAttribute("inert", ""); el.setAttribute("aria-hidden", "true"); }
      else { el.removeAttribute("inert"); el.removeAttribute("aria-hidden"); }
    });
  };
  var trapTab = function (container, e) {
    if (e.key !== "Tab" || !container) return;
    var f = container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select,textarea,[tabindex]:not([tabindex="-1"])');
    f = Array.prototype.filter.call(f, function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  };

  /* ---------- Modal ---------- */
  var overlay = document.getElementById("applyModal");
  if (overlay) {
    var formWrap = document.getElementById("modalFormWrap");
    var successWrap = document.getElementById("modalSuccessWrap");
    var modalForm = initApplyForm({
      prefix: "am", formId: "applyModalForm", titleId: "applyModalTitle", successNameId: "modalSuccessWrapName",
      onSuccess: function () { if (formWrap) formWrap.hidden = true; if (successWrap) { successWrap.hidden = false; successWrap.focus(); } }
    });
    var openModal = function () {
      overlay._lastFocused = document.activeElement;
      overlay.classList.add("is-open"); overlay.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden"; setBackgroundInert(true, overlay);
      try { window.dataLayer = window.dataLayer || []; window.dataLayer.push({ event: "apply_modal_open", page_path: location.pathname }); } catch (e) {}
      if (modalForm) setTimeout(modalForm.focusFirst, 60);
    };
    var closeModal = function () {
      overlay.classList.remove("is-open"); overlay.setAttribute("aria-hidden", "true");
      document.body.style.overflow = ""; setBackgroundInert(false);
      if (overlay._lastFocused && overlay._lastFocused.focus) overlay._lastFocused.focus();
    };
    overlay.addEventListener("click", function (e) { if (e.target === overlay) closeModal(); });
    var mClose = document.getElementById("applyModalClose");
    if (mClose) mClose.addEventListener("click", closeModal);
    document.addEventListener("keydown", function (e) {
      if (!overlay.classList.contains("is-open")) return;
      if (e.key === "Escape") { closeModal(); return; }
      trapTab(overlay.querySelector(".modal"), e);
    });
    // Any "Apply Now"/"Inscríbete" link, or [data-apply], opens the modal — except on /apply itself.
    var onApplyPage = !!document.getElementById("applyForm");
    document.querySelectorAll("a, button").forEach(function (el) {
      if (el.closest("#applyModal")) return;
      var txt = (el.textContent || "").trim();
      var isApply = el.hasAttribute("data-apply") || txt === "Apply Now" || txt === "Inscríbete" || txt === "Inscríbete ahora";
      if (!isApply) return;
      if (onApplyPage && el.getAttribute("href") === "#apply") return;
      el.addEventListener("click", function (e) {
        if (onApplyPage) { e.preventDefault(); var f = document.getElementById("applyForm"); if (f) f.scrollIntoView({ behavior: "smooth" }); return; }
        e.preventDefault(); openModal();
      });
    });
    if (location.hash === "#apply" && !onApplyPage) setTimeout(openModal, 200);
  }

  /* ---------- Standalone /apply form & LP step-2 ---------- */
  var applyPageForm = document.getElementById("applyForm");
  if (applyPageForm) {
    var applySuccess = document.getElementById("applySuccess");
    initApplyForm({
      prefix: "af", formId: "applyForm", successNameId: "applySuccessName",
      onSuccess: function () { applyPageForm.hidden = true; var t = document.getElementById("applyFormTitle"); if (t) t.hidden = true; if (applySuccess) { applySuccess.hidden = false; applySuccess.focus(); applySuccess.scrollIntoView({ behavior: "smooth", block: "center" }); } }
    });
  }
  var lpForm = document.getElementById("lpApplyForm");
  if (lpForm) {
    var lpSuccess = document.getElementById("lpSuccess");
    initApplyForm({
      prefix: "lp", formId: "lpApplyForm", titleId: "lpApplyFormTitle", successNameId: "lpSuccessName",
      onSuccess: function () { lpForm.hidden = true; var t = document.getElementById("lpApplyFormTitle"); if (t) t.hidden = true; if (lpSuccess) { lpSuccess.hidden = false; lpSuccess.focus(); } }
    });
  }

  /* ---------- LP step-1 pre-registration ---------- */
  var preRegForm = document.getElementById("preRegForm");
  if (preRegForm) {
    var prBy = function (s) { return document.getElementById("pr-" + s); };
    var prFirst = prBy("first"), prLast = prBy("last"), prEmail = prBy("email"), prPhoneInput = prBy("phone");
    var prIti = initPhone(prPhoneInput);
    var prSummary = prBy("summary");
    var prMsg = function (k) { return preRegForm.getAttribute("data-msg-" + k) || ""; };
    preRegForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var invalids = [];
      var mark2 = function (fld, bad, key) {
        if (!fld) return;
        fld.classList.toggle("is-invalid", bad); fld.setAttribute("aria-invalid", String(bad));
        var er = document.getElementById(fld.id + "-error");
        if (er) { er.textContent = bad ? prMsg(key) : ""; er.hidden = !bad; }
        if (bad) invalids.push(fld);
      };
      mark2(prFirst, !prFirst.value.trim(), "required");
      mark2(prLast, !prLast.value.trim(), "required");
      mark2(prEmail, !EMAIL_RE.test(prEmail.value.trim()), "invalid-email");
      mark2(prPhoneInput, !(prIti ? prIti.isValidNumber() : /\d{10}/.test(prPhoneInput.value.replace(/\D/g, ""))), "invalid-phone");
      if (invalids.length) { if (prSummary) { prSummary.textContent = prMsg("error-summary"); prSummary.hidden = false; prSummary.focus(); } else invalids[0].focus(); return; }
      if (prSummary) prSummary.hidden = true;

      var id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
        : "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) { var r = (Math.random() * 16) | 0; return (c === "x" ? r : ((r & 0x3) | 0x8)).toString(16); });
      var firstV = prFirst.value.trim(), lastV = prLast.value.trim(), emailV = prEmail.value.trim();
      var phoneV = prIti ? prIti.getNumber() : prPhoneInput.value.trim();
      try { sessionStorage.setItem("fei_prereg", JSON.stringify({ id: id, firstName: firstV, lastName: lastV, email: emailV, phone: phoneV })); } catch (e2) {}

      var payload = Object.assign({
        stage: "pre", preRegId: id, firstName: firstV, lastName: lastV, email: emailV, phone: phoneV,
        program: preRegForm.getAttribute("data-program") || "", programType: preRegForm.getAttribute("data-program-type") || ""
      }, baseAttribution());

      var next = preRegForm.getAttribute("data-next") || CFG.applyUrl || "/apply/";
      var submitBtn = preRegForm.querySelector('[type="submit"]');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = I18N.sending || "Sending…"; }
      try { window.dataLayer = window.dataLayer || []; window.dataLayer.push({ event: "form_prereg", form_id: "preRegForm", program: payload.program, lead_id: id }); } catch (e) {}
      var go = function () { window.location.href = next; };
      postLead(payload, "preRegForm").then(go).catch(function (err) {
        reportSubmitError({ reason: (err && err.httpStatus) ? "http" : "network", httpStatus: (err && err.httpStatus) || 0, responseBody: (err && err.responseBody) || "", message: (err && err.message) || String(err), formId: "preRegForm", payload: payload });
        go();
      });
    });
  }

  /* ---------- Accordion ---------- */
  document.querySelectorAll(".accordion").forEach(function (acc) {
    acc.addEventListener("click", function (e) {
      var btn = e.target.closest(".acc-trigger");
      if (!btn) return;
      var item = btn.closest(".acc-item");
      var open = item.classList.contains("is-open");
      acc.querySelectorAll(".acc-item.is-open").forEach(function (it) { it.classList.remove("is-open"); it.querySelector(".acc-trigger").setAttribute("aria-expanded", "false"); });
      if (!open) { item.classList.add("is-open"); btn.setAttribute("aria-expanded", "true"); }
    });
  });

  /* ---------- Program filter chips ---------- */
  var chips = document.querySelectorAll(".chip[data-filter]");
  if (chips.length) {
    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        chips.forEach(function (c) { c.classList.remove("is-active"); c.setAttribute("aria-pressed", "false"); });
        chip.classList.add("is-active"); chip.setAttribute("aria-pressed", "true");
        var f = chip.getAttribute("data-filter");
        document.querySelectorAll(".prog-list li").forEach(function (li) {
          var tile = li.querySelector(".prog-tile");
          li.hidden = !(f === "all" || (tile && tile.getAttribute("data-cat") === f));
        });
      });
    });
  }

  /* ---------- Reveal on scroll (respects reduced motion) ---------- */
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var reveals = document.querySelectorAll(".reveal");
  if (reveals.length && "IntersectionObserver" in window && !reduce) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add("is-in"); io.unobserve(en.target); } });
    }, { rootMargin: "0px 0px -8% 0px" });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* ---------- Net Price Calculator full-bleed sizing ---------- */
  var npc = document.querySelector(".npc-fullbleed");
  if (npc) {
    var hdr = document.querySelector("#masthead, header.nav, .site-header");
    var setH = function () { var h = hdr ? hdr.getBoundingClientRect().bottom : 0; document.documentElement.style.setProperty("--header-h", Math.max(h, 0) + "px"); };
    setH(); window.addEventListener("load", setH); window.addEventListener("resize", setH, { passive: true });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(setH);
  }

  /* ---------- Click-to-call tracking ---------- */
  document.addEventListener("click", function (e) {
    var a = e.target.closest('a[href^="tel:"]');
    if (!a) return;
    try { window.dataLayer = window.dataLayer || []; window.dataLayer.push({ event: "click_to_call", phone: a.getAttribute("href").replace("tel:", ""), page_path: location.pathname }); } catch (err) {}
  });
})();
