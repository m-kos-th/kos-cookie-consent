document.addEventListener("DOMContentLoaded", function () {
  var popup = document.getElementById("kosCookiePopup");
  if (!popup) {
    return;
  }

  var backdrop = popup.querySelector("[data-cookie-backdrop]");
  var banner = popup.querySelector("[data-cookie-banner]");
  var modal = popup.querySelector("[data-cookie-modal]");
  var error = popup.querySelector("[data-cookie-error]");
  var preferencesTrigger = document.querySelector("[data-cookie-preferences-trigger]");
  var openModalButtons = popup.querySelectorAll("[data-cookie-action='open-modal']");
  var closeModalButtons = popup.querySelectorAll("[data-cookie-action='close-modal']");
  var acceptAllButtons = popup.querySelectorAll("[data-cookie-action='accept-all']");
  var declineButtons = popup.querySelectorAll("[data-cookie-action='decline-all']");
  var saveButton = popup.querySelector("[data-cookie-action='save']");
  var itemToggleButtons = popup.querySelectorAll("[data-cookie-action='toggle-item']");
  var checkboxes = popup.querySelectorAll("input[data-cookie-id]");
  var stateUrl = popup.getAttribute("data-state-url") || "";
  var saveUrl = popup.getAttribute("data-save-url") || "";
  var fallbackToken = popup.getAttribute("data-token") || "";
  var siteId = popup.getAttribute("data-site-id") || "";
  var editMode = popup.getAttribute("data-edit-mode") === "1";
  var consentCookieName = "kos_cookie_consent_id";
  var defaultConsentCookieMaxAge = 30 * 24 * 60 * 60;
  var consentCookieMaxAge = parseInt(popup.getAttribute("data-consent-cookie-max-age"), 10);
  consentCookieMaxAge = consentCookieMaxAge > 0 ? consentCookieMaxAge : defaultConsentCookieMaxAge;
  var preferencesMode = false;
  var state = {
    token: fallbackToken,
    consentId: "",
    version: "",
    consent: [],
    saved: false,
    managedCookieCleanup: {},
    code: {
      header: [],
      body: [],
      footer: [],
    },
  };
  var injected = {
    header: {},
    body: {},
    footer: {},
  };

  function showPopup(showBanner) {
    preferencesMode = showBanner === false;
    popup.hidden = false;
    if (banner) {
      banner.hidden = showBanner === false;
    }
    hidePreferenceTrigger();
  }

  function hidePopup() {
    popup.hidden = true;
    setModalOpen(false);
  }

  function showPreferenceTrigger() {
    if (preferencesTrigger && !editMode) {
      preferencesTrigger.hidden = false;
    }
  }

  function hidePreferenceTrigger() {
    if (preferencesTrigger) {
      preferencesTrigger.hidden = true;
    }
  }

  function getCookie(name) {
    var pattern = new RegExp("(?:^|; )" + name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "=([^;]*)");
    var match = document.cookie.match(pattern);

    return match ? decodeURIComponent(match[1]) : "";
  }

  function normalizeCookieMaxAge(maxAge) {
    var seconds = parseInt(maxAge, 10);

    return seconds > 0 ? seconds : defaultConsentCookieMaxAge;
  }

  function setCookie(name, value, maxAge) {
    var normalizedMaxAge = normalizeCookieMaxAge(maxAge);
    var expires = new Date(Date.now() + normalizedMaxAge * 1000).toUTCString();
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie =
      name +
      "=" +
      encodeURIComponent(value) +
      "; expires=" +
      expires +
      "; max-age=" +
      normalizedMaxAge +
      "; path=/; SameSite=Lax" +
      secure;
  }

  function expireCookie(name, domain, path) {
    var secure = window.location.protocol === "https:" ? "; Secure" : "";
    var cookie =
      name +
      "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; max-age=0; path=" +
      path;

    if (domain) {
      cookie += "; domain=" + domain;
    }

    document.cookie = cookie + "; SameSite=Lax" + secure;
    document.cookie = cookie;
  }

  function generateConsentId() {
    if (window.crypto && window.crypto.getRandomValues) {
      var bytes = new Uint8Array(16);
      window.crypto.getRandomValues(bytes);
      return Array.prototype.map
        .call(bytes, function (byte) {
          return ("0" + byte.toString(16)).slice(-2);
        })
        .join("");
    }

    return (Date.now().toString(16) + Math.random().toString(16).slice(2) + Math.random().toString(16).slice(2)).slice(0, 32);
  }

  function syncConsentId(consentId) {
    if (!consentId) {
      return "";
    }

    state.consentId = consentId;
    setCookie(consentCookieName, consentId, consentCookieMaxAge);

    return consentId;
  }

  function ensureConsentId() {
    var consentId = getCookie(consentCookieName);
    if (!consentId) {
      consentId = generateConsentId();
    }

    return syncConsentId(consentId);
  }

  function setConsentCookie() {
    var version = state && state.version ? state.version : "";
    syncConsentId(state.consentId || ensureConsentId());
    setCookie("consent", "true", consentCookieMaxAge);
    if (version) {
      setCookie("consent_version", version, consentCookieMaxAge);
    }
  }

  function setBusy(isBusy) {
    popup.classList.toggle("is-submitting", isBusy);
    popup.querySelectorAll("button").forEach(function (button) {
      button.disabled = isBusy;
    });
  }

  function setError(isVisible) {
    if (error) {
      error.hidden = !isVisible;
    }
  }

  function setModalOpen(isOpen) {
    popup.classList.toggle("is-modal-open", isOpen);
    if (modal) {
      modal.hidden = !isOpen;
    }
    if (backdrop) {
      backdrop.hidden = !isOpen;
    }
  }

  function closeModal() {
    setModalOpen(false);
    if (preferencesMode && state.saved) {
      preferencesMode = false;
      hidePopup();
      showPreferenceTrigger();
    }
  }

  function toggleItem(button, forceOpen) {
    var item = button.closest("[data-cookie-item]");
    if (!item) {
      return;
    }

    var body = item.querySelector("[data-cookie-item-body]");
    var isExpanded = button.getAttribute("aria-expanded") === "true";
    var shouldOpen = typeof forceOpen === "boolean" ? forceOpen : !isExpanded;

    button.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    if (body) {
      body.hidden = !shouldOpen;
    }
  }

  function buildNode(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      return document.createTextNode(node.textContent);
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
      return document.createDocumentFragment();
    }

    if (node.tagName.toLowerCase() === "script") {
      var script = document.createElement("script");
      Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
        script.setAttribute(attribute.name, attribute.value);
      });
      script.text = node.text || node.textContent || "";
      return script;
    }

    var clone = document.createElement(node.tagName);
    Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
      clone.setAttribute(attribute.name, attribute.value);
    });
    Array.prototype.slice.call(node.childNodes).forEach(function (childNode) {
      clone.appendChild(buildNode(childNode));
    });

    return clone;
  }

  function injectCode(position, code) {
    if (!code || injected[position][code]) {
      return;
    }

    var template = document.createElement("template");
    template.innerHTML = code;
    var fragment = document.createDocumentFragment();

    Array.prototype.slice.call(template.content.childNodes).forEach(function (node) {
      fragment.appendChild(buildNode(node));
    });

    if (position === "header") {
      document.head.appendChild(fragment);
    } else if (position === "body") {
      document.body.insertBefore(fragment, document.body.firstChild);
    } else {
      document.body.appendChild(fragment);
    }

    injected[position][code] = true;
  }

  function injectCodes(codeGroups) {
    ["header", "body", "footer"].forEach(function (position) {
      var codes = codeGroups && codeGroups[position] ? codeGroups[position] : [];
      codes.forEach(function (code) {
        injectCode(position, code);
      });
    });
  }

  function applySavedConsent(consentIds) {
    var saved = {};
    (consentIds || []).forEach(function (id) {
      saved[String(id)] = true;
    });

    checkboxes.forEach(function (checkbox) {
      checkbox.checked = !!saved[String(checkbox.getAttribute("data-cookie-id"))];
    });
  }

  function captureSelections() {
    var selected = {};
    checkboxes.forEach(function (checkbox) {
      selected[String(checkbox.getAttribute("data-cookie-id"))] = checkbox.checked;
    });

    return selected;
  }

  function applySelections(selected) {
    checkboxes.forEach(function (checkbox) {
      var cookieId = String(checkbox.getAttribute("data-cookie-id"));
      if (Object.prototype.hasOwnProperty.call(selected, cookieId)) {
        checkbox.checked = !!selected[cookieId];
      }
    });
  }

  function setAllOptionalConsents(isChecked) {
    checkboxes.forEach(function (checkbox) {
      checkbox.checked = isChecked;
    });
  }

  function normalizeCleanupMap(cleanupMap) {
    return cleanupMap && typeof cleanupMap === "object" ? cleanupMap : {};
  }

  function buildConsentLookup(consentIds) {
    var lookup = {};

    (consentIds || []).forEach(function (id) {
      lookup[String(id)] = true;
    });

    return lookup;
  }

  function getRevokedConsentIds(previousConsentIds, nextConsentIds) {
    var nextLookup = buildConsentLookup(nextConsentIds);

    return (previousConsentIds || []).filter(function (id) {
      return !nextLookup[String(id)];
    });
  }

  function getAvailableCookieNames() {
    if (!document.cookie) {
      return [];
    }

    return document.cookie
      .split(/;\s*/)
      .map(function (cookie) {
        var index = cookie.indexOf("=");
        return index === -1 ? cookie : cookie.slice(0, index);
      })
      .filter(function (name) {
        return name !== "";
      });
  }

  function uniqueValues(values) {
    var result = [];

    values.forEach(function (value) {
      if (result.indexOf(value) === -1) {
        result.push(value);
      }
    });

    return result;
  }

  function buildDomainCandidates(domain) {
    var candidates = [""];
    var hostname = (window.location.hostname || "").toLowerCase();

    function pushDomain(value) {
      var normalized = String(value || "")
        .trim()
        .toLowerCase()
        .replace(/^\.+/, "");

      if (!normalized || normalized === "localhost") {
        return;
      }

      if (candidates.indexOf(normalized) === -1) {
        candidates.push(normalized);
      }

      if (candidates.indexOf("." + normalized) === -1) {
        candidates.push("." + normalized);
      }
    }

    if (domain) {
      pushDomain(domain);
    }

    if (hostname && hostname.indexOf(".") !== -1) {
      var parts = hostname.split(".");
      for (var i = 0; i < parts.length - 1; i += 1) {
        pushDomain(parts.slice(i).join("."));
      }
    } else if (hostname) {
      pushDomain(hostname);
    }

    return candidates;
  }

  function buildPathCandidates(path) {
    var candidates = [];
    var currentPath = window.location.pathname || "/";

    function pushPath(value) {
      var normalized = String(value || "").trim() || "/";
      if (normalized.charAt(0) !== "/") {
        normalized = "/" + normalized;
      }

      if (normalized.length > 1) {
        normalized = normalized.replace(/\/+$/, "");
      }

      if (!normalized) {
        normalized = "/";
      }

      if (candidates.indexOf(normalized) === -1) {
        candidates.push(normalized);
      }

      if (normalized !== "/" && candidates.indexOf(normalized + "/") === -1) {
        candidates.push(normalized + "/");
      }
    }

    if (path) {
      pushPath(path);
    }

    pushPath(currentPath);

    if (currentPath !== "/") {
      var segments = currentPath.split("/").filter(Boolean);
      var assembled = "";

      segments.forEach(function (segment) {
        assembled += "/" + segment;
        pushPath(assembled);
      });
    }

    pushPath("/");

    return candidates;
  }

  function resolveCleanupCookieNames(config, availableCookieNames) {
    var names = [];

    (config.cookies || []).forEach(function (rule) {
      if (!rule || !rule.pattern) {
        return;
      }

      if (rule.match === "prefix") {
        availableCookieNames.forEach(function (cookieName) {
          if (cookieName.indexOf(rule.pattern) === 0) {
            names.push(cookieName);
          }
        });
        return;
      }

      names.push(rule.pattern);
    });

    return uniqueValues(names);
  }

  function clearManagedCookies(cleanupMap, allowedConsentIds) {
    var consentLookup = buildConsentLookup(allowedConsentIds);
    var availableCookieNames = getAvailableCookieNames();

    Object.keys(cleanupMap || {}).forEach(function (cookieId) {
      var config = cleanupMap[cookieId];
      if (!config || config.always || consentLookup[String(cookieId)]) {
        return;
      }

      var cookieNames = resolveCleanupCookieNames(config, availableCookieNames);
      var domains = buildDomainCandidates(config.domain || "");
      var paths = buildPathCandidates(config.path || "/");

      cookieNames.forEach(function (cookieName) {
        domains.forEach(function (domain) {
          paths.forEach(function (path) {
            expireCookie(cookieName, domain, path);
          });
        });
      });
    });
  }

  function afterNextPaint(callback) {
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(callback);
      return;
    }

    window.setTimeout(callback, 0);
  }

  function applyState(data) {
    if (data && Object.prototype.hasOwnProperty.call(data, "cookie_max_age")) {
      consentCookieMaxAge = normalizeCookieMaxAge(data.cookie_max_age);
    }

    if (data && Object.prototype.hasOwnProperty.call(data, "managed_cookie_cleanup")) {
      data.managedCookieCleanup = normalizeCleanupMap(data.managed_cookie_cleanup);
    }

    state = data || state;
    state.managedCookieCleanup = normalizeCleanupMap(state.managedCookieCleanup);
    if (state.consent_id) {
      state.consentId = syncConsentId(state.consent_id);
    } else if (!state.consentId) {
      state.consentId = ensureConsentId();
    }
    if (state.saved) {
      applySavedConsent(state.consent || []);
      clearManagedCookies(state.managedCookieCleanup || {}, state.consent || []);
    }
    injectCodes(state.code || {});

    return state;
  }

  function fetchState() {
    if (!stateUrl || !siteId) {
      return Promise.reject(new Error("missing_state_url"));
    }

    var existingConsentId = getCookie(consentCookieName);
    var consentId = existingConsentId || ensureConsentId();
    var separator = stateUrl.indexOf("?") === -1 ? "?" : "&";
    var query =
      "site_id=" +
      encodeURIComponent(siteId) +
      "&consent_id=" +
      encodeURIComponent(consentId) +
      "&consent_id_fresh=" +
      (existingConsentId ? "0" : "1") +
      "&_=" +
      Date.now();
    return window
      .fetch(stateUrl + separator + query, {
        method: "GET",
        cache: "no-store",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            return {
              ok: response.ok,
              status: response.status,
              data: data,
            };
          });
      });
  }

  function refreshState() {
    return fetchState().then(function (result) {
      if (!result.ok || result.data.status !== 1) {
        throw new Error("state_failed");
      }

      return applyState(result.data);
    });
  }

  function submitConsent(hasRetried) {
    var token = state && state.token ? state.token : fallbackToken;
    var previousConsent = Array.isArray(state.consent) ? state.consent.slice() : [];
    var hadSavedConsent = !!state.saved;
    if (!saveUrl || !token || !siteId) {
      return Promise.reject(new Error("missing_save_data"));
    }

    var params = new URLSearchParams();
    params.append("_token", token);
    params.append("site_id", siteId);
    params.append("consent_id", state.consentId || ensureConsentId());

    checkboxes.forEach(function (checkbox) {
      if (checkbox.checked) {
        params.append("consent[" + checkbox.getAttribute("data-cookie-id") + "]", "1");
      }
    });

    return window
      .fetch(saveUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: params.toString(),
      })
      .then(function (response) {
        return response
          .json()
          .catch(function () {
            return {};
          })
          .then(function (data) {
            return {
              ok: response.ok,
              status: response.status,
              data: data,
            };
          });
      })
      .then(function (result) {
        if (!result.ok || result.data.status !== 1) {
          if (result.status === 400 && !hasRetried) {
            var selected = captureSelections();
            return refreshState().then(function () {
              applySelections(selected);
              return submitConsent(true);
            });
          }

          throw new Error("save_failed");
        }

        if (Object.prototype.hasOwnProperty.call(result.data, "saved") && !result.data.saved) {
          throw new Error("save_not_persisted");
        }

        injectCodes(result.data.code || {});
        if (Object.prototype.hasOwnProperty.call(result.data, "cookie_max_age")) {
          consentCookieMaxAge = normalizeCookieMaxAge(result.data.cookie_max_age);
        }
        if (Object.prototype.hasOwnProperty.call(result.data, "managed_cookie_cleanup")) {
          state.managedCookieCleanup = normalizeCleanupMap(result.data.managed_cookie_cleanup);
        }
        if (result.data.consent_id) {
          state.consentId = syncConsentId(result.data.consent_id);
        }
        if (result.data.version) {
          state.version = result.data.version;
        }
        if (result.data.consent) {
          state.consent = result.data.consent;
          applySavedConsent(state.consent);
        }
        state.saved = true;
        setConsentCookie();
        clearManagedCookies(state.managedCookieCleanup || {}, state.consent || []);
        hidePopup();
        showPreferenceTrigger();
        setBusy(false);

        if (hadSavedConsent && getRevokedConsentIds(previousConsent, state.consent || []).length > 0 && !editMode) {
          window.location.reload();
        }
      });
  }

  function saveConsent(selectAll, declineAll) {
    setError(false);
    state.consentId = ensureConsentId();

    if (selectAll) {
      setAllOptionalConsents(true);
    } else if (declineAll) {
      setAllOptionalConsents(false);
    }

    var selected = captureSelections();
    setBusy(true);

    afterNextPaint(function () {
      refreshState()
        .catch(function () {
          return state;
        })
        .then(function () {
          applySelections(selected);
          return submitConsent(false);
        })
        .catch(function () {
          setError(true);
          setModalOpen(true);
          setBusy(false);
        });
    });
  }

  openModalButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      setModalOpen(true);
    });
  });

  closeModalButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      closeModal();
    });
  });

  if (backdrop) {
    backdrop.addEventListener("click", function () {
      closeModal();
    });
  }

  itemToggleButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      toggleItem(button);
    });
  });

  acceptAllButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      saveConsent(true, false);
    });
  });

  declineButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      saveConsent(false, true);
    });
  });

  if (saveButton) {
    saveButton.addEventListener("click", function () {
      saveConsent(false, false);
    });
  }

  if (preferencesTrigger) {
    preferencesTrigger.addEventListener("click", function () {
      setError(false);
      hidePreferenceTrigger();

      refreshState()
        .catch(function () {
          return state;
        })
        .then(function () {
          if (state.saved) {
            showPopup(false);
            setModalOpen(true);
            return;
          }

          showPopup(true);
        });
    });
  }

  state.consentId = getCookie(consentCookieName) || "";

  refreshState()
    .then(function (result) {
      if (!state.saved || editMode) {
        showPopup(true);
        if (editMode) {
          setModalOpen(true);
        }
      } else {
        showPreferenceTrigger();
      }
    })
    .catch(function () {
      showPopup(true);
    });
});
