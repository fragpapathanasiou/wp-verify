(function () {
  function selectElement(selector) { try { return document.querySelector(selector); } catch (e) { return null; } }
  function createElement(tag, attributes, children) { var element = document.createElement(tag); if (attributes) Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); }); if (children) children.forEach(function (child) { if (typeof child === "string") element.appendChild(document.createTextNode(child)); else element.appendChild(child); }); return element; }
  function apiEndpoint(path) { var base = (window.WPVTS && WPVTS.rest) ? WPVTS.rest.replace(/\/+$/, "") : "/wp-json/wpvts/v1"; return base + path; }

  function buildVerificationWidget(kind) { var wrapper = createElement("div", { "class": "wpvts-wrap", "data-kind": kind }, []); var sendButton = createElement("button", { "type": "button", "class": "wpvts-send" }, ["Send code"]); var statusSpan = createElement("span", { "class": "wpvts-status" }, []); var hiddenVerificationInput = createElement("input", { "type": "hidden", "class": "wpvts-vesid" }, []); var outcomeContainer = createElement("div", { "class": "wpvts-outcome" }, []); wrapper.appendChild(sendButton); wrapper.appendChild(statusSpan); wrapper.appendChild(hiddenVerificationInput); wrapper.appendChild(outcomeContainer); return wrapper; }
  function renderOutcome(widget, ok, message) { var c = widget.querySelector(".wpvts-outcome"); if (!c) return; c.innerHTML = ""; var t = message || (ok ? ((window.WPVTS && WPVTS.messages && WPVTS.messages.verified) ? WPVTS.messages.verified : "Verified") : ((window.WPVTS && WPVTS.messages && WPVTS.messages.invalid) ? WPVTS.messages.invalid : "Invalid code")); var b = createElement("span", { "class": ok ? "wpvts-badge wpvts-badge--ok" : "wpvts-badge wpvts-badge--err", "style": "display:inline-block;margin-top:8px;padding:4px 8px;border-radius:999px;font-size:12px;" + (ok ? "background:#e6f4ea;color:#1e4620;" : "background:#fdecea;color:#611a15;") }, [t]); c.appendChild(b); }

  function postRequest(url, payload, onResult) { var formData = new FormData(); var p = Object.assign({}, payload, { user_id: WPVTS && WPVTS.currentUserId ? WPVTS.currentUserId : undefined }); Object.keys(p).forEach(function (k) { if (p[k] !== undefined && p[k] !== null) formData.append(k, p[k]); }); fetch(url, { method: "POST", body: formData, credentials: "same-origin", cache: "no-store", headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "X-WP-Nonce": WPVTS && WPVTS.nonce ? WPVTS.nonce : "", "X-WPVTS-User": WPVTS && WPVTS.currentUserId ? String(WPVTS.currentUserId) : "" } }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); }).then(function (j) { onResult(j); }).catch(function () { onResult({ ok: false }); }); }
  function getRequest(url, onResult) { fetch(url, { method: "GET", credentials: "same-origin", headers: { "Accept": "application/json", "X-WP-Nonce": WPVTS && WPVTS.nonce ? WPVTS.nonce : "" } }).then(function (r) { return r.json().catch(function(){return { ok:false };}); }).then(function (j) { onResult(j); }).catch(function(){ onResult({ ok:false }); }); }

  function setVerificationSid(widget, inputElement, verificationSid) { var v = verificationSid || ""; widget.dataset.verificationSid = v; var h = widget.querySelector(".wpvts-vesid"); if (h) h.value = v; try { var key = "wpvts.ve." + (widget.getAttribute("data-kind") || "unknown") + "." + (inputElement.name || inputElement.id || "unknown"); sessionStorage.setItem(key, v); localStorage.setItem(key, v); } catch (e) {} }
  function getVerificationSid(widget, inputElement) { var v = widget.dataset.verificationSid || ""; if (v) return v; var h = widget.querySelector(".wpvts-vesid"); if (h && h.value) return h.value; try { var key = "wpvts.ve." + (widget.getAttribute("data-kind") || "unknown") + "." + (inputElement.name || inputElement.id || "unknown"); var s = sessionStorage.getItem(key) || localStorage.getItem(key) || ""; if (s) return s; } catch (e) {} return ""; }

  function preventFormSubmit(root) { var f = root.closest("form"); if (!f || f.dataset.wpvtsBound === "1") return; f.dataset.wpvtsBound = "1"; f.addEventListener("submit", function (e) { var pending = !!document.querySelector(".wpvts-modal[data-pending='1']"); if (pending) { e.preventDefault(); e.stopPropagation(); } }, true); }
  function wireClick(btn, fn) { btn.addEventListener("click", function (e) { e.preventDefault(); e.stopPropagation(); fn(); }); }

  function openModal(options) { var overlay = createElement("div", { "class": "wpvts-modal-overlay", "style": "position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:99999;" }, []); var modal = createElement("div", { "class": "wpvts-modal", "data-pending": "1", "style": "background:#fff;max-width:420px;width:90%;padding:16px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);font-family:inherit;" }, []); var title = createElement("div", { "class": "wpvts-modal-title", "style": "font-size:18px;font-weight:600;margin-bottom:8px;" }, [options.titleText]); var hint = createElement("div", { "class": "wpvts-modal-hint", "style": "font-size:13px;opacity:.8;margin-bottom:12px;" }, [options.hintText]); var codeInput = createElement("input", { "type": "text", "class": "wpvts-modal-code", "placeholder": options.placeholderText, "style": "width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;margin-bottom:12px;outline:none;" }, []); var actions = createElement("div", { "class": "wpvts-modal-actions", "style": "display:flex;gap:8px;justify-content:flex-end;" }, []); var cancelButton = createElement("button", { "type": "button", "class": "wpvts-modal-cancel", "style": "padding:10px 14px;border-radius:8px;border:1px solid #ddd;background:#f6f6f6;cursor:pointer;" }, [options.cancelText]); var verifyButton = createElement("button", { "type": "button", "class": "wpvts-modal-verify", "style": "padding:10px 14px;border-radius:8px;border:0;background:#2e7d32;color:#fff;cursor:pointer;" }, [options.verifyText]); var status = createElement("div", { "class": "wpvts-modal-status", "style": "font-size:13px;margin-top:10px;min-height:16px;" }, []); actions.appendChild(cancelButton); actions.appendChild(verifyButton); modal.appendChild(title); modal.appendChild(hint); modal.appendChild(codeInput); modal.appendChild(actions); modal.appendChild(status); overlay.appendChild(modal); document.body.appendChild(overlay); codeInput.focus(); cancelButton.addEventListener("click", function () { modal.dataset.pending = "0"; if (overlay.parentNode) document.body.removeChild(overlay); }); verifyButton.addEventListener("click", function () { var enteredCode = (codeInput.value || "").trim(); if (!enteredCode) { status.textContent = (window.WPVTS && WPVTS.messages && WPVTS.messages.invalid) ? WPVTS.messages.invalid : "Invalid code"; return; } verifyButton.disabled = true; options.onVerify(enteredCode, function (result) { var isApproved = !!(result && (result.status === "approved" || result.ok === true || result.smsStatus === "approved")); status.textContent = isApproved ? ((window.WPVTS && WPVTS.messages && WPVTS.messages.verified) ? WPVTS.messages.verified : "Verified") : ((window.WPVTS && WPVTS.messages && WPVTS.messages.invalid) ? WPVTS.messages.invalid : "Invalid code"); verifyButton.disabled = false; if (isApproved) { modal.dataset.pending = "0"; setTimeout(function () { if (overlay.parentNode) document.body.removeChild(overlay); }, 400); } }); }); return { overlay: overlay, modal: modal, codeInput: codeInput, verifyButton: verifyButton, status: status }; }

  function toE164FromIntlInput(input) { var raw = (input.value || "").trim(); if (/^\+/.test(raw)) return raw.replace(/\s+/g, ""); var wrap = input.closest && input.closest(".iti"); var dial = (wrap ? (wrap.querySelector(".iti__selected-dial-code") || {}).textContent : "") || ""; var cc = (dial.trim().match(/\d+/) || ["30"])[0]; var nat = raw.replace(/\D+/g, "").replace(/^0+/, ""); if (!nat) return ""; return "+" + cc + nat; }
  function isE164(v) { return /^\+[1-9]\d{7,14}$/.test(v); }

  function globalApprovalKey(kind) { return "wpvts.global.approved." + kind; }
  function isGloballyApproved(kind) { try { return localStorage.getItem(globalApprovalKey(kind)) === "1" || sessionStorage.getItem(globalApprovalKey(kind)) === "1"; } catch (e) { return false; } }
  function setGloballyApproved(kind) { try { localStorage.setItem(globalApprovalKey(kind), "1"); sessionStorage.setItem(globalApprovalKey(kind), "1"); } catch (e) {} }
  function clearGloballyApproved(kind) { try { localStorage.removeItem(globalApprovalKey(kind)); sessionStorage.removeItem(globalApprovalKey(kind)); } catch (e) {} }

  function attachEmail() {
    var emailInput = selectElement('#verify_email_code input[name="user_email_field"]') || selectElement('#verify_email_code input[name="user_email"]') || selectElement('#verify_email_code input[type="email"]');
    if (!emailInput || emailInput.dataset.wpvts === "1" || isGloballyApproved("email")) return;
    emailInput.dataset.wpvts = "1";
    var widget = buildVerificationWidget("email");
    emailInput.parentNode.insertBefore(widget, emailInput.nextSibling);
    var sendButton = widget.querySelector(".wpvts-send");
    var statusSpan = widget.querySelector(".wpvts-status");
    preventFormSubmit(widget);
    wireClick(sendButton, function () {
      var toAddress = (emailInput.value || "").trim();
      if (!toAddress) { statusSpan.textContent = (window.WPVTS && WPVTS.messages && WPVTS.messages.failed) ? WPVTS.messages.failed : "Failed"; return; }
      sendButton.disabled = true;
      postRequest(apiEndpoint("/start"), { channel: "email", target: toAddress }, function (res) {
        var verificationSid = (res && res.verificationSid) ? res.verificationSid : "";
        setVerificationSid(widget, emailInput, verificationSid);
        statusSpan.textContent = res.ok ? ((window.WPVTS && WPVTS.messages && WPVTS.messages.sent) ? WPVTS.messages.sent : "Sent") : ((window.WPVTS && WPVTS.messages && WPVTS.messages.failed) ? WPVTS.messages.failed : "Failed");
        sendButton.disabled = false;
        if (res && res.ok) {
          openModal({
            titleText: (window.WPVTS && WPVTS.titles && WPVTS.titles.email) ? WPVTS.titles.email : "Verify email",
            hintText: (window.WPVTS && WPVTS.hints && WPVTS.hints.email) ? WPVTS.hints.email : "Enter the code we sent to your email.",
            placeholderText: "Code",
            cancelText: (window.WPVTS && WPVTS.buttons && WPVTS.buttons.cancel) ? WPVTS.buttons.cancel : "Cancel",
            verifyText: (window.WPVTS && WPVTS.buttons && WPVTS.buttons.verify) ? WPVTS.buttons.verify : "Verify",
            onVerify: function (enteredCode, done) {
              var sidNow = getVerificationSid(widget, emailInput);
              var payload = { channel: "email", otp: enteredCode, target: toAddress };
              if (sidNow) payload.token = sidNow;
              postRequest(apiEndpoint("/check"), payload, function (result) {
                var approved = !!(result && (result.status === "approved" || result.ok === true));
                var message = result && result.message ? String(result.message) : "";
                if (approved) { widget.dataset.verified = "1"; setGloballyApproved("email"); if (widget.parentNode) widget.parentNode.removeChild(widget); }
                renderOutcome(widget, approved, message);
                done(result || { ok: false });
              });
            }
          });
        } else {
          renderOutcome(widget, false, res && res.message ? String(res.message) : "");
        }
      });
    });
  }

  function attachPhone() {
    var phoneContainer = selectElement("#verify_phone_code .phon-num-fieldcon") || selectElement("#verify_phone_code");
    var phoneInput = selectElement('#verify_phone_code input[name="user_phone"]') || selectElement('#verify_phone_code input[type="tel"]') || (phoneContainer ? phoneContainer.querySelector("input") : null);
    if (!phoneInput || phoneInput.dataset.wpvts === "1" || isGloballyApproved("sms")) return;
    phoneInput.dataset.wpvts = "1";
    var widget = buildVerificationWidget("sms");
    (phoneContainer || phoneInput).parentNode.insertBefore(widget, (phoneContainer || phoneInput).nextSibling);
    var sendButton = widget.querySelector(".wpvts-send");
    var statusSpan = widget.querySelector(".wpvts-status");
    preventFormSubmit(widget);
    setTimeout(function () { var container = phoneInput.closest && phoneInput.closest(".iti"); var flagButton = container ? container.querySelector(".iti__selected-flag") : null; if (flagButton) flagButton.click(); }, 300);
    wireClick(sendButton, function () {
      var e164 = toE164FromIntlInput(phoneInput);
      if (!isE164(e164)) { statusSpan.textContent = "Invalid phone"; renderOutcome(widget, false, "Invalid phone"); return; }
      sendButton.disabled = true;
      postRequest(apiEndpoint("/start"), { channel: "sms", target: e164 }, function (res) {
        var verificationSid = (res && res.verificationSid) ? res.verificationSid : "";
        setVerificationSid(widget, phoneInput, verificationSid);
        statusSpan.textContent = res.ok ? ((window.WPVTS && WPVTS.messages && WPVTS.messages.sent) ? WPVTS.messages.sent : "Sent") : ((window.WPVTS && WPVTS.messages && WPVTS.messages.failed) ? WPVTS.messages.failed : "Failed");
        sendButton.disabled = false;
        if (res && res.ok) {
          openModal({
            titleText: (window.WPVTS && WPVTS.titles && WPVTS.titles.sms) ? WPVTS.titles.sms : "Verify phone",
            hintText: (window.WPVTS && WPVTS.hints && WPVTS.hints.sms) ? WPVTS.hints.sms : "Enter the code we sent via SMS.",
            placeholderText: "Code",
            cancelText: (window.WPVTS && WPVTS.buttons && WPVTS.buttons.cancel) ? WPVTS.buttons.cancel : "Cancel",
            verifyText: (window.WPVTS && WPVTS.buttons && WPVTS.buttons.verify) ? WPVTS.buttons.verify : "Verify",
            onVerify: function (enteredCode, done) {
              var sidNow = getVerificationSid(widget, phoneInput);
              var payload = { channel: "sms", otp: enteredCode, target: e164 };
              if (sidNow) payload.token = sidNow;
              postRequest(apiEndpoint("/check"), payload, function (result) {
                var approved = !!(result && (result.status === "approved" || result.smsStatus === "approved" || result.ok === true));
                var message = result && result.message ? String(result.message) : "";
                if (approved) { widget.dataset.verified = "1"; setGloballyApproved("sms"); if (widget.parentNode) widget.parentNode.removeChild(widget); }
                renderOutcome(widget, approved, message);
                done(result || { ok: false });
              });
            }
          });
        } else {
          renderOutcome(widget, false, res && res.message ? String(res.message) : "");
        }
      });
    });
  }

  function currentTargets() {
    var emailInput = selectElement('#verify_email_code input[name="user_email_field"]') || selectElement('#verify_email_code input[name="user_email"]') || selectElement('#verify_email_code input[type="email"]');
    var phoneInput = selectElement('#verify_phone_code input[name="user_phone"]') || selectElement('#verify_phone_code input[type="tel"]');
    var email = emailInput ? (emailInput.value || "").trim() : "";
    var phone = phoneInput ? toE164FromIntlInput(phoneInput) : "";
    return { email: email, phone: phone };
  }

  function toE164FromIntlInput(input) { var raw = (input.value || "").trim(); if (/^\+/.test(raw)) return raw.replace(/\s+/g, ""); var wrap = input.closest && input.closest(".iti"); var dial = (wrap ? (wrap.querySelector(".iti__selected-dial-code") || {}).textContent : "") || ""; var cc = (dial.trim().match(/\d+/) || ["30"])[0]; var nat = raw.replace(/\D+/g, "").replace(/^0+/, ""); if (!nat) return ""; return "+" + cc + nat; }

  function applyInitialStatus(statusJson) {
    var smsApproved = !!(statusJson && statusJson.ok && statusJson.smsStatus === "approved");
    var emailApproved = !!(statusJson && statusJson.ok && statusJson.emailStatus === "approved");
    if (smsApproved) setGloballyApproved("sms"); else clearGloballyApproved("sms");
    if (emailApproved) setGloballyApproved("email"); else clearGloballyApproved("email");
    attachEmail();
    attachPhone();
  }

  function start() {
    var t = currentTargets();
    var qs = [];
    if (t.phone) qs.push("sms_to=" + encodeURIComponent(t.phone));
    if (t.email) qs.push("email_to=" + encodeURIComponent(t.email));
    var url = apiEndpoint("/verification/status") + (qs.length ? ("?" + qs.join("&")) : "");
    if (WPVTS && WPVTS.nonce) getRequest(url, applyInitialStatus); else applyInitialStatus(null);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start); else start();
})();
