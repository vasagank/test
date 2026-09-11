async function enc(value) {
  if(!value) {
    return "";
  }
  const app_key = await get_app_key();
  vm_log("enc app_key", app_key);

  const salt = CryptoJS.lib.WordArray.random(16);
  const iv = CryptoJS.lib.WordArray.random(16);

  const key = CryptoJS.PBKDF2(app_key, salt, {keySize: 256 / 32, iterations: VaultMateConfig.default.enc_dec_iterations});
  const encrypted = CryptoJS.AES.encrypt(String(value), key, {iv, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7});
  const payload = JSON.stringify({ct:encrypted.toString(), iv:iv.toString(), s:salt.toString()});

  return CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(payload));
}

async function dec(cipherText) {
  if(!cipherText) {
    return "";
  }

  const app_key = await get_app_key();
  vm_log("dec app_key", app_key);

  let jsonStr;
  try {
    jsonStr = CryptoJS.enc.Utf8.stringify(CryptoJS.enc.Base64.parse(cipherText));
  } catch {
    throw new Error("Invalid encrypted payload!");
  }

  let data;
  try {
    data = JSON.parse(jsonStr);
  } catch {
    throw new Error("Invalid encrypted payload.");
  }

  const salt = CryptoJS.enc.Hex.parse(data.s);
  const iv = CryptoJS.enc.Hex.parse(data.iv);

  const key = CryptoJS.PBKDF2(app_key, salt, {keySize: 256 / 32, iterations: VaultMateConfig.default.enc_dec_iterations});
  const bytes = CryptoJS.AES.decrypt(data.ct, key, {iv, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7});
  const text = bytes.toString(CryptoJS.enc.Utf8);

  if(!text) { 
    throw new Error("Decryption failed");
  }
  return text;
}

const loaderSVG = `<svg class="copy-loader" viewBox="0 0 50 50">
  <circle cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
</svg>`;


function showCopyStatus(iconEl, success, originalClass) {
  iconEl.innerHTML = "";
  iconEl.className = success ? "fas fa-check fa-fw copy-icon" : "fas fa-times fa-fw copy-icon";

  iconEl.style.color = success ? "#28a745" : "#dc3545";
  setTimeout(() => {
    iconEl.style.transform = "scale(0.7)";
    iconEl.style.opacity = "0";
    setTimeout(() => {
      iconEl.innerHTML = "";
      iconEl.className = originalClass;
      iconEl.style.color = "";
      iconEl.style.transform = "scale(1)";
      iconEl.style.opacity = "1";
    }, 150);
  }, 1800);
}

async function handleCopyClick(iconEl, enc_str, id) {
  const originalClass = iconEl.className;
  iconEl.className = "copy-icon fa-fw";
  iconEl.innerHTML = loaderSVG;

  iconEl.style.transform = "scale(0.9)";
  iconEl.style.opacity = "0.7";

  requestAnimationFrame(() => {
    iconEl.style.transform = "scale(1)";
    iconEl.style.opacity = "1";
  });

  await new Promise(resolve => setTimeout(resolve, 0));
  try {
    const decrypted = await dec(enc_str);
    if(!decrypted) {
      showCopyStatus(iconEl, false, originalClass);
      return;
    }
    await copy_vault(decrypted, id);
    showCopyStatus(iconEl, true, originalClass);
  } catch (e) {
    console.error(e);
    showCopyStatus(iconEl, false, originalClass);
  }
}


//start deep analysis

function _zx_raw_predictability(zx) {
  let score = 0;
  for (const p of (zx.sequence || [])) {
    if (p.pattern === "dictionary" && p.dictionary_name === "passwords") score += 80;
    else if (p.pattern === "dictionary") score += 40;
    else if (p.pattern === "sequence")   score += 30;
    else if (p.pattern === "repeat")     score += 20;
    else if (p.pattern === "date")       score += 15;
  }
  score += (4 - zx.score) * 10;
  return Math.min(100, score);
}

function generateSimSignature(pwd) {
  if (!pwd || pwd.length < 3) return "";

  const grams = new Set();
  for (let i = 0; i <= pwd.length - 3; i++) {
    grams.add(pwd.substring(i, i + 3));
  }

  return Array.from(grams).sort().join("|");
}

let zxcvbnLoading = null;
async function ensureZxcvbn() {
    if(window.zxcvbn) {
        return;
    }
    if(zxcvbnLoading) {
        return zxcvbnLoading;
    }
    zxcvbnLoading = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = "js/zxcvbn.js";
      script.onload = resolve;
      script.onerror = reject;
      document.body.appendChild(script);
    });
    return zxcvbnLoading;
}


const CRACK_TIME_TIERS = [
  { max: 1,          passwordCap: 15, riskFloor: 95 },
  { max: 60,         passwordCap: 30, riskFloor: 85 },
  { max: 3_600,      passwordCap: 45, riskFloor: 70 },
  { max: 86_400,     passwordCap: 60, riskFloor: 55 },
  { max: 604_800,    passwordCap: 75, riskFloor: 35 },
  { max: 2_592_000,  passwordCap: 85, riskFloor: 20 },
];  
function getCrackTier(crack_seconds) {
  if (crack_seconds === null) return null;
  return CRACK_TIME_TIERS.find(t => crack_seconds < t.max) || null;
}

async function compute_password_metrics(rawPassword, pwdType = "password", reused = false) {
 
  if(!rawPassword) {
    return {password_score: 0, predictability_score: 0, crack_seconds: null, risk_score: 0, risk_level: "Low", zx: null, analysis: null};
  } 

  let zx, crack_seconds, raw_zx_score, patterns; 
  //Phase 1: prepare zxcvbn response
 
  if(pwdType === "pin") {
    const len = rawPassword.length;
    const entropy = len * Math.log2(10);
    crack_seconds = Math.pow(2, entropy) / 1e10;  
    raw_zx_score = 0;                             
 
    const isAllSame = /^(.)\1+$/.test(rawPassword);
    const isSequential = /0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|3210/.test(rawPassword);
    patterns = [];

    if(isAllSame) {
      patterns.push({ pattern: "repeat",   dictionary: null });
    } 
    if(isSequential) {
      patterns.push({ pattern: "sequence", dictionary: null });
    }
 
    zx = {
      score: raw_zx_score,
      sequence: patterns,
      feedback: {warning: isAllSame ? "Avoid repeating the same digit" : isSequential ? "Avoid sequential numbers like 1234" : "",
      suggestions: (isAllSame || isSequential)
          ? ["Use a random combination of digits", "Avoid patterns like 1234 or 1111"]
          : rawPassword.length < 6
          ? ["Use at least 6 digits for better security"]
          : []
      },
      guesses_log10: entropy / Math.log2(10)
    };
 
  } else {
    await ensureZxcvbn();
    zx = zxcvbn(rawPassword);
    raw_zx_score  = zx.score;
    crack_seconds = zx.crack_times_seconds?.offline_fast_hashing_1e10_per_second ?? null;
    patterns = (zx.sequence || []).map(s => ({
      pattern:    s.pattern,
      dictionary: s.dictionary_name || null
    }));
  }

  //Phase 2: generate password score 
  const hasCommonDict = patterns.some(p => p.dictionary === "passwords");
  const hasDict = patterns.some(p => p.pattern === "dictionary");
  const hasSequence = patterns.some(p => p.pattern === "sequence");
  const hasDate = patterns.some(p => p.pattern === "date");
  let password_score;
 
  if(pwdType === "pin") {
    const len = rawPassword.length;
    let s = len * 14; 
    const isAllSame = /^(.)\1+$/.test(rawPassword);
    const isSequential = /0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|3210/.test(rawPassword);

    if(isAllSame) {
      s -= 45;
    } 
    if(isSequential) {
      s -= 35;
    }

    const tier = getCrackTier(crack_seconds);
    if(tier) {
      s = Math.min(s, tier.passwordCap);
    }
    const pinCeiling = len >= 8 ? 85 : len === 7 ? 75 : len === 6 ? 65 : len === 5 ? 40 : len === 4 ? 22 : 15;
    s = Math.min(s, pinCeiling);    
    password_score = Math.max(5, Math.min(100, Math.round(s)));
 
  } else {
    let s = (raw_zx_score + 1) * 20;
 
    if(hasCommonDict) {            
      s -= 25;
    } else if (hasDict) {         
      s -= 8;
    }

    if (hasDict && hasSequence) {
      s -=  8;
    }
    if (hasDate) {                  
      s -=  5;
    }
 
    const raw_pred = _zx_raw_predictability(zx);
    if(raw_pred > 80) {
      s -= 15;
    } else if (raw_pred > 60) {
      s -=  8;
    }
 
    const tier = getCrackTier(crack_seconds);
    if(tier) {
      s = Math.min(s, tier.passwordCap);
    } 
    password_score = Math.max(5, Math.min(100, Math.round(s)));
  }
 
  //Phase 3: predictability_score
  let predictability_score;
 
  if(pwdType === "pin") {
    predictability_score = Math.max(0, 100 - password_score);
  } else {
    const raw_pred = _zx_raw_predictability(zx);
    const pred_ceiling = Math.max(0, 110 - password_score);
    predictability_score = Math.min(raw_pred, pred_ceiling);
    predictability_score = Math.max(0, Math.round(predictability_score));
  }
 
  //Phase 4: risk_score + risk_level  
  let risk_score = 100 - password_score;
  let risk_level;

  const riskTier = getCrackTier(crack_seconds);
  if(riskTier) {
    risk_score = Math.max(risk_score, riskTier.riskFloor);
  } 
 
  if(reused) {
    risk_score = Math.min(100, risk_score + 15);
  }
 
  risk_score = Math.min(100, Math.round(risk_score));

  if(risk_score >= 60) {
    risk_level = "High";
  } else if(risk_score >= 40) {
    risk_level = "Medium";
  } else {
    risk_level = "Low";
  }

  return {
    password_score,
    predictability_score,
    crack_seconds,
    risk_score,
    risk_level,
    zx,
    analysis: {
      type:         pwdType,
      score:        raw_zx_score,
      warning:      zx?.feedback?.warning        || "",
      suggestions:  zx?.feedback?.suggestions    || [],
      patterns,
      guesses_log10: zx?.guesses_log10           || 0
    }
  };
}

//end deep analysis


function vm_generate_fingerprint(slug, data) {
  const cfg = category_config[slug];

  if (!cfg?.unique_fields?.length) {
    return null;
  }

  const values = [];

  for (const key of cfg.unique_fields) {
    let val = data[key];

    if (!val) {
      throw new Error(`Missing required unique field: ${key}`);
    }

    val = vm_normalize(val); 
    values.push(val);
  }

  const base = `${slug}|${values.join("|")}`;
  return {
    fingerprint: CryptoJS.SHA256(base).toString(),
    normalized_value: values.join("|")
  };
}

const pending_attachments = {};

function open_file_picker(key) {
  const input = dgi(`${key}`);
  if (!input) return;

  /*if(!input._vmBound) {
    input.addEventListener("change", () => {
      file_preview(key);
    });
    input._vmBound = true;
  }*/

  if(!input._vmBound) {
    input.addEventListener("change", () => {
      if(!pending_attachments[key]) {
        pending_attachments[key] = [];
      }
      Array.from(input.files).forEach(file => {
        const exists = pending_attachments[key].some(f =>
          f.name === file.name &&
          f.size === file.size &&
          f.lastModified === file.lastModified
        );
        if(!exists) {
          pending_attachments[key].push(file);
          console.log("Added to key:", key, pending_attachments);
        }
      });
      input.value = "";
      file_preview(key);
    });
    input._vmBound = true;
  }

  ons.openActionSheet({
    title: "Add Attachment",
    modifier: "vm-attach-sheet",
    cancelable: true,
    buttons: [
      { label: "Take Photo", icon: "fa-camera" },
      { label: "Choose Files", icon: "fa-folder-open"  },
      { label: "Cancel", icon: "fa-times"  }
    ]
  }).then(idx => {
    if(idx === 0) {
      input.setAttribute("capture", "environment");
      input.removeAttribute("multiple");
      input.setAttribute("accept", "image/*");
      input.click();
    } else if(idx === 1) {
      input.removeAttribute("capture");
      input.setAttribute("multiple");
      input.setAttribute("accept", "*/*");
      input.click();
    }
  });
}

function file_preview(key) {
  //const input = dgi(`${key}_file`);
  const countEl = dgi(`${key}_count`);
  const previewEl = dgi(`${key}_preview`);
  previewEl.innerHTML = "";

  /*if(!input.files || !input.files.length) {
    countEl.textContent = "";
    return;
  }
  countEl.textContent =  `${input.files.length} file${input.files.length > 1 ? "s" : ""} selected`;*/
  const files = pending_attachments[key] || [];
  if(!files.length) {
    countEl.textContent = "";
    return;
  }
  countEl.textContent = `${files.length} file${files.length > 1 ? "s" : ""} selected`;

  //Array.from(input.files).forEach((file, idx) => {
  files.forEach((file, idx) => {
    const card = document.createElement("div");
    card.className = "vm-file-card";

    if (file.type.startsWith("image/")) {
      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      card.appendChild(img);
    } else {
      const icon = document.createElement("i");
      if (file.type === "application/pdf") {
        icon.className = "fas fa-file-pdf vm-file-icon pdf";
      } else {
        icon.className = "fas fa-file vm-file-icon";
      }
      card.appendChild(icon);
    }

    const name = document.createElement("span");
    name.className = "vm-file-name";
    name.textContent = file.name;
    card.appendChild(name);

    const remove = document.createElement("i");
    remove.className = "fas fa-times vm-file-remove";
    remove.title = "Remove";
    remove.onclick = () => preview_remove_file(key, idx);
    card.appendChild(remove);
    previewEl.appendChild(card);
  });
}

function preview_remove_file(key, index) {
  /*const input = dgi(`${key}_file`);
  const dt = new DataTransfer();
  Array.from(input.files).forEach((file, i) => {
    if (i !== index) dt.items.add(file);
  });
  input.files = dt.files;*/

  if(!pending_attachments[key]) {
    return;
  }
  pending_attachments[key].splice(index, 1);
  file_preview(key);
}

function collect_attachments(slug) {
  const cfg = category_config[slug];
  if(!cfg || !cfg.fileField) {
    return [];
  }

  console.log("slug:", slug);
  console.log("cfg.fileField:", cfg.fileField);
  console.log("pending_attachments:", pending_attachments);

  /*const input = dgi(cfg.fileField);
  if(!input || !input.files || !input.files.length) {
    return [];
  }
  return Array.from(input.files);*/

  const files = pending_attachments[cfg.fileField];
  console.log("files:", files);

  if(!files || !files.length) {
    return [];
  }
  return [...files];
}

async function vm_check_duplicate(fingerprint, current_id = null) {
  let where = `fingerprint = ?`;
  let values = [fingerprint];

  if(current_id) {
    where += ` AND id != ?`;
    values.push(current_id);
  }

  const { sql } = VaultDB.buildSelect({
    table: VaultMateConfig.tables.vaults,
    columns: ["id"],
    where
  });

  const res = await VaultDB.dbExecute(sql, values);
  return res.rows.length ? res.rows.item(0) : null;
}

async function save_vault(e) {
  const btn = e?.currentTarget;
  set_button_loading(btn, true);

  const slug = dgi("category_slug").value;
  const id = dgi("vault_id").value; 
  const attachments = collect_attachments(slug);

  console.log("Collected attachments:", attachments);
  console.log("Count:", attachments.length);

  //console.log("attachments", attachments);
  const isPremium = await isPremiumUser();

  if(!id && !isPremium) {
    let total = await count_vaults_db();
    if(total >= VAULT_LIMIT) {
      show_toast(`Limit reached! Free tier allows up to ${VAULT_LIMIT} items.`);
      set_button_loading(btn, false);
      return;
    }
  }


  const res = await add_edit_vault({slug: slug, id:id, attachments: attachments});
  set_button_loading(btn, false);

  if(!res.success) {    
    show_errors(res.errors);
  } else {
    let status_msg = "";
    if(!id) {
      await insert_vault_card(res.vault); 
      refresh_dashboard_action("add_vault");
      status_msg = "Vault created successfully.";
    } else {
      update_vault_card(res.vault);  
      refresh_dashboard_action("edit_vault");
      status_msg = "Vault updated successfully.";
    }
    show_toast(status_msg);
    close_bottom_sheet();

    const cfg = category_config[slug];
    if(cfg?.fileField) {
      pending_attachments[cfg.fileField] = [];
      const input = dgi(cfg.fileField);
      if(input) {
        input.value = "";
      }
      file_preview(cfg.fileField);
    }

  }
}

async function insert_vault_card(item) {
    const list = document.querySelector("#vm_vault_list");
    if (!list) {
        return;
    }

    const empty = list.querySelector(".vm-empty-wrap");
    if (empty) {
        empty.remove();
    }

    list.insertAdjacentHTML("afterbegin", vault_item_html(item));
    const el = list.querySelector(`.list-item[data-vault-id="${item.id}"]`);
    if (el) {
        el.classList.add("vault-updated");

        clearTimeout(el._highlightTimer);
        el._highlightTimer = setTimeout(() => {
            el.classList.remove("vault-updated");
        }, 2500);

        const rect = el.getBoundingClientRect();
        if (rect.top < 0 || rect.bottom > window.innerHeight) {
            el.scrollIntoView({
                behavior: "smooth",
                block: "center"
            });
        }
    }

    const total = await count_vaults_db(vault_filters);
    render_vault_meta(total, 1);
    renderUpgradeInline("vault", total, VAULT_LIMIT);
}

function update_vault_card(item) {
    const selector = `.vm_vault_list .list-item[data-vault-id="${item.id}"]`;
    const oldEl = document.querySelector(selector);
    if (!oldEl) {
        return;
    }
    // Replace the card
    oldEl.outerHTML = vault_item_html(item);

    // Get the newly rendered element
    const newEl = document.querySelector(selector);
    if (!newEl) {
      return;
    }
    const rect = newEl.getBoundingClientRect();
    if (rect.top < 0 || rect.bottom > window.innerHeight) {
        newEl.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }
    newEl.classList.add("vault-updated");

    clearTimeout(newEl._highlightTimer);
    newEl._highlightTimer = setTimeout(() => {
        newEl.classList.remove("vault-updated");
    }, 2500);
}

function build_search_text(fields, category_config) {
  let parts = [];
  Object.entries(fields).forEach(([key, value]) => {
    const fieldConfig = category_config?.fields?.[key];
    if(fieldConfig?.secure || fieldConfig?.score) {
      return;
    }
    if(value !== null && value !== undefined) {
      parts.push(String(value).trim());
    }
  });
  return parts.join(" ").replace(/\s+/g, " ").trim().toLowerCase();
}

async function add_edit_vault({id = null, slug, attachments = [], opts = {}, importData = null}) {
  //const category_fields = collect_fields(slug);
  const category_fields = collect_fields(slug, importData);  

  const rem_enabled = category_fields.rem_enabled || 0;
  const rem_before = category_fields.rem_before || VaultMateConfig.default.reminder_before;
  let rem_expiry = null;
  
  if(category_config[slug]?.expiryField) {
    const expField = category_config[slug].expiryField;
    rem_expiry = category_fields[expField] ? new Date(category_fields[expField]).getTime() : null; 
  }

  delete category_fields.rem_enabled;
  delete category_fields.rem_before;

  const validation = validate_fields(slug, category_fields);
  if(!validation.valid) {
    return {success: false, errors: validation.errors};
  }

  let fpData = null;
  try {
    fpData = vm_generate_fingerprint(slug, category_fields);
  } catch (e) {
    return { success: false, errors: { general: e.message } };
  }

  if(fpData) {
    const duplicate = await vm_check_duplicate(fpData.fingerprint, id);
    if(duplicate) {
      return {success: false, errors: {general: "Duplicate entry already exists"}};
    }
  }

  const fields = await secure_fields(slug, category_fields);
  const title  = category_config[slug].title(category_fields);
  const now = Date.now();  
  //const password_score = 0; //get_vault_score(slug, category_fields);

  let pwdKey = null;
  let pwdType = "password";
  let rawPassword = null;
  const cfg = category_config[slug];

  if(cfg?.fields) {
    for(const key in cfg.fields) {
      const rule = cfg.fields[key];
      if(rule.secure && rule.score) {
        pwdKey = key;
        rawPassword = category_fields[key];
        pwdType = rule.type === "pin" ? "pin" : "password";
        break; 
      }
    }
  }

  let pw_hash = null;
  let pw_updated_at = null;
  let pw_sim_sig = "";
  let pw_predictability = 0;
  let pw_crack_seconds = null;
  let existing_vault = null;
  let has_attachment = attachments.length ? 1 : 0;  
  const search_text = build_search_text(category_fields, category_config[slug]);

  if(id && !has_attachment) {
    const existing = await get_attachments_by_vault_id(id);
    has_attachment = existing.length ? 1 : 0;
  }

  const vault_row = {
    slug,
    title,
    data_json: JSON.stringify({fields}),
    rem_enabled,
    rem_before,
    rem_expiry,
    has_attachment,
    search_text,
    fingerprint:fpData.fingerprint,
    normalized_value:fpData.normalized_value,
    updated_at: now
  };

  if(id) {
    try {
      existing_vault = await get_vault_by_id(id);
    } catch(e) {}
  }

  let existing_addl = {};
  if(existing_vault?.addl_json) {
    try {
      existing_addl = JSON.parse(existing_vault.addl_json);
    } catch {}
  }

  if(rawPassword) {
    pw_hash = CryptoJS.SHA256(rawPassword).toString();
    pw_sim_sig = generateSimSignature(rawPassword);
    const isPasswordChanged = !existing_vault || existing_vault.password_hash !== pw_hash;   
 
    if(isPasswordChanged) { 
      const metrics = await compute_password_metrics(rawPassword, pwdType, false); 

      /*let maskedPassword = "****";
      if(rawPassword && rawPassword.length >= 4) {
        maskedPassword = rawPassword.slice(0, 2) + "****" + rawPassword.slice(-2);
      }*/

      let maskedPassword = "****";
      if(rawPassword) {
          if(rawPassword.length <= 2) {
              maskedPassword = "*".repeat(rawPassword.length);
          } else {
              maskedPassword =
                  rawPassword.charAt(0) +
                  "*".repeat(rawPassword.length - 2) +
                  rawPassword.charAt(rawPassword.length - 1);
          }
      }

      vault_row.password_score = metrics.password_score; 
      vault_row.password_updated_at = now;

      existing_addl.password_analysis = metrics.analysis;
      existing_addl.masked_password = maskedPassword;
 
      vault_row.risk_score = metrics.risk_score;
      vault_row.risk_level = metrics.risk_level;

      vault_row.predictability_score = metrics.predictability_score;
      vault_row.crack_seconds = metrics.crack_seconds;

      vault_row.addl_json = JSON.stringify(existing_addl); 
    } 
    vault_row.similar_signature = pw_sim_sig;
    vault_row.password_hash = pw_hash;
  }

  let vault_id;
  if(!id) {
    vault_row.created_at = now;
    const q = VaultDB.buildInsert(VaultMateConfig.tables.vaults, vault_row);
    const res = await VaultDB.dbExecute(q.sql, q.values, opts);
    vault_id = res.insertId;
  } else {
    const q = VaultDB.buildUpdate(VaultMateConfig.tables.vaults, vault_row, {id});
    await VaultDB.dbExecute(q.sql, q.values, opts);
    vault_id = id;

    VaultCache.removeRow(id);
    invalidateVaultCache(id);
  }

  const notesKey = Object.keys(category_fields).find(k => k.endsWith("_notes"));
  const notes = notesKey ? category_fields[notesKey] : "";
  await sync_vault_reminder({vault_id:vault_id, slug:slug, category_fields:category_fields, title:title, rem_enabled:rem_enabled, rem_before:rem_before, notes:notes});

  if(id) {
    const deleteIds = edit_attachments.filter(a => a._deleted).map(a => a.id);
    if(deleteIds.length) {
      const ids = deleteIds.map(() => "?").join(",");
      const sql = `delete from ${VaultMateConfig.tables.attachments} where id in (${ids})`;
      await VaultDB.dbExecute(sql, deleteIds, opts);
    }
  }

  if(attachments.length) {
    const failed = [];
    for(const file of attachments) {
      try {
        const saved = await save_attachment(file);
        const ins = VaultDB.buildInsert(VaultMateConfig.tables.attachments, {
          vault_id: vault_id,
          file_name: saved.name,
          mime_type: saved.mime,
          encrypted_path: saved.path,
          file_size: saved.size,
          created_at: now,
          updated_at: now
        });
        await VaultDB.dbExecute(ins.sql, ins.values, opts);
      } catch (err) {
        console.error("Failed to save attachment:", file.name, err);
        failed.push(file.name);
      }
    }
    if(failed.length) {
      show_toast(`${failed.length} file(s) could not be saved. Please try again.`);
    }
  }

  //console.log("attachments.length", attachments.length);
  /*if(attachments.length) {
    const failed = [];
    for(const file of attachments) {
      //console.log("file", file);
      try {
        const saved = await save_attachment(file);
        const ins = VaultDB.buildInsert(VaultMateConfig.tables.attachments, {
          vault_id: vault_id,
          file_name: saved.name,
          mime_type: saved.mime,
          encrypted_path: saved.path,
          file_size: saved.size,
          created_at: now,
          updated_at: now
        });
        await VaultDB.dbExecute(ins.sql, ins.values, opts);
      } catch (err) {
        console.error("Failed to save attachment:", file.name, err);
        failed.push(file.name);
      }
    }
    if(failed.length) {
      show_toast(`${failed.length} file(s) could not be saved. Please try again.`);
    }
  }*/  


  const savedVault = await get_vault_by_id(vault_id);
  return {
    success: true,
    vault: savedVault
  };
}

function validate_fields(slug, fields) {
  const cfg = category_config[slug];
  const errors = {};

  Object.entries(cfg.fields).forEach(([k, r]) => {
    if(r.required && (fields[k] === "" || fields[k] === null)) 
      errors[k] = cfg.fields[k].label + " is required";
  });

  const chk = dgi("vm_enable_reminder");
  if(chk && chk.checked && cfg.expiryField) {
    const expVal = fields[cfg.expiryField];
    if(!expVal) {
      errors[cfg.expiryField] = cfg.fields[cfg.expiryField].label + " is required when reminder is enabled";
    } else {
      const expTs = new Date(expVal).setHours(0,0,0,0);
      const todayTs = new Date().setHours(0,0,0,0);
      if(expTs <= todayTs) {
        errors[cfg.expiryField] = cfg.fields[cfg.expiryField].label + " must be a future date";
      }
    }
  }
  return { valid: Object.keys(errors).length === 0, errors };
}

async function secure_fields(slug, fields) {
  const cfg = category_config[slug];
  const out = {};

  await Promise.all(
    Object.entries(fields).map(async ([k, v]) => {
      const rule = cfg.fields[k];
      out[k] = rule?.secure && v ? await enc(v) : v;
    })
  );
  return out;
}

function show_errors(errors) {
  if(!errors || typeof errors !== "object") {
    show_toast("Something went wrong");
    return;
  }

  const fields = Object.keys(errors);
  const firstField = fields[0];

  show_toast(errors[firstField]);
  fields.forEach(id => {
    const el = dgi(id);
    if(el) {
      el.classList.add("error-field");
      el.addEventListener("input", () => {
        el.classList.remove("error-field");
      }, {once: true});
    } else {
      //console.log("id is not available ", id)
    }
  });
}

function collect_fields(slug, overrideData = null) {
  if(overrideData) {
    return {...overrideData};
  }  
  const cfg = category_config[slug];
  const fields = {};
  Object.keys(cfg.fields).forEach(id => {
    const el = dgi(id);
    fields[id] = el ? el.value.trim() : "";
  });

  if(cfg.expiryField) {
    const chk = dgi("vm_enable_reminder");
    const sel = dgi("vm_rem_before");
    fields.rem_enabled = chk && chk.checked ? 1 : 0;
    fields.rem_before = sel ? parseInt(sel.value, 10) : 7;
  }
  return fields;
}

async function score_by_type(value, type = "password") {
  if (!value) return 1;
  const m = await compute_password_metrics(value, type === "pin" ? "pin" : "password");
  return m.password_score;
}

function get_vault_score(slug, rawFields) {
  const cfg = category_config[slug];
  let score = 0;
  Object.entries(cfg.fields).forEach(([key, rules]) => {
    if(rules.score && rawFields[key]) {
      const type = rules.type;
      const fieldScore = score_by_type(rawFields[key], type);
      score = Math.max(score, fieldScore);
    }
  });
  return score;
}

async function save_attachment(file) {

  if(!file || file.size === 0) {
    throw new Error(`File "${file.name}" is not accessible. If it's stored in cloud, download it first.`);
  }

  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onloadend = function () {

      if(reader.error) {
        reject(new Error(`Failed to read "${file.name}": ${reader.error}`));
        return;
      }


      window.resolveLocalFileSystemURL(
        cordova.file.dataDirectory,
        dir => {
          dir.getDirectory(VaultMateConfig.default.vault_dir, {create: true}, folder => {
            const fileName = Date.now() + "_" + Math.random().toString(36).slice(2) + "_" + file.name;

            folder.getFile(fileName, { create: true, exclusive: false }, fileEntry => {
              fileEntry.createWriter(writer => {
                writer.onwriteend = () => {
                  resolve({
                    path: fileEntry.nativeURL,
                    name: file.name,
                    size: file.size,
                    mime: file.type
                  });
                };
                //writer.onerror = reject;
                writer.onerror = (e) => reject(new Error(`Write failed for "${file.name}": ${e}`));
                writer.write(new Blob([reader.result], {type: file.type}));
              }, reject);
            }, reject);
          }, reject);
        },
        reject
      );
    };
    //reader.onerror = reject;
    reader.onerror = () => reject(new Error(`Cannot read "${file.name}". Try downloading it locally first.`));
    reader.readAsArrayBuffer(file);
  });
}

function open_attachment(path, mime) {
  console.log("Opening:", path);
  console.log("Mime:", mime);
  window.resolveLocalFileSystemURL(path, fileEntry => {
    console.log("Native URL:", fileEntry.nativeURL);
    console.log("Name:", fileEntry.name);


    cordova.plugins.fileOpener2.open(fileEntry.nativeURL, mime, {
      error: err => {
          console.log(err);
          show_toast("Unable to open file");
      }
    });


  });
}

function open_attachment(path, mime) {
  window.resolveLocalFileSystemURL(path, fileEntry => {
    cordova.plugins.fileOpener2.open(
      fileEntry.nativeURL,
      mime, {
        error: err => {
          show_toast("Unable to open file");
        },
        success: () => {
          //console.log("File opened successfully");
        }
      }
    );
  }, err => {
    show_toast("File not found");
  });
}


/*
######################
######################
######################
######################
*/

function format_date(ts, mode = "relative") {
  if (!ts) return "";

  if(ts > 1e12) {
    ts = Math.floor(ts / 1000); 
  }

  const date = new Date(ts * 1000);

  if(mode === "datetime") {
    const pad = n => String(n).padStart(2, "0");
    let hours = date.getHours();
    const minutes = pad(date.getMinutes());
    const seconds = pad(date.getSeconds());

    const ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12;

    return (
      date.getFullYear() + "-" +
      pad(date.getMonth() + 1) + "-" +
      pad(date.getDate()) + " " +
      pad(hours) + ":" +
      minutes + ":" +
      seconds + " " +
      ampm
    );
  }

  const now = Math.floor(Date.now() / 1000);
  let diff = now - ts;

  if(diff < 5) {
    return "just now";
  }

  const units = [
    {label: "year", secs: 31536000},
    {label: "month", secs: 2592000},
    {label: "day", secs: 86400},
    {label: "hour", secs: 3600},
    {label: "min", secs: 60},
    {label: "sec", secs: 1}
  ];

  for(const u of units) {
    const value = Math.floor(diff / u.secs);
    if(value >= 1) {
      return `${value} ${u.label}${value > 1 ? "s" : ""} ago`;
    }
  }
  return "just now";
}

function get_notes(row) {
  const fields = extract_fields(row);
  return Object.keys(fields).find(k => k.endsWith("_notes")) ? fields[Object.keys(fields).find(k => k.endsWith("_notes"))] : "";
}

function has_password_score(slug) {
  const cfg = category_config[slug];
  if(!cfg || !cfg.fields) return false;
  return Object.values(cfg.fields).some(f => f.score === true);
}

function get_password_status_safe(slug, score) {
  if(!has_password_score(slug) || typeof score !== "number") {
    return null; 
  }
  return get_password_status(score);
}

function get_listview_copy_value(row) {
  const cfg = category_config[row.slug];
  if (!cfg) return null;
  const fields = extract_fields(row);

  for(const key in cfg.fields) {
    const rules = cfg.fields[key];
    if(rules.score === true && fields[key]) {
      return fields[key];
    }
  }
  return null;
}

//list view copy
async function list_copy_vault(enc_str, id) {
  if(!enc_str) {
    show_toast("Nothing to copy");
    return;
  }
  try {
    const decrypted = await dec(enc_str);
    if(!decrypted) {
      show_toast("Copy failed");
      return;
    }
    await copy_vault(decrypted, id);
  } catch (e) {
    console.error("List copy failed", e);
    show_toast("Unable to copy");
  }
}

function get_fallback_field(v) {
  const cfg = category_config[v.slug];
  if (!cfg || !cfg.fields) {
    return "";
  }

  const fields = extract_fields(v);
  const titleStr = (v.title || "").toLowerCase();
  const subtitleRaw = cfg.subtitle ? (cfg.subtitle(fields) || "") : "";
  const subtitleStr = subtitleRaw.replace(/<[^>]*>/g, "").toLowerCase();

  for(const [key, rules] of Object.entries(cfg.fields)) {
    if(rules.secure) {    
      continue; 
    }
    if(key.endsWith("_notes")) {
      continue; 
    }

    const val = fields[key];
    if (!val || typeof val !== "string") {
      continue;
    }

    const valLower = val.toLowerCase();
    if(titleStr.includes(valLower)) {
      continue;
    }  
    if(subtitleStr.includes(valLower)) {
      continue;
    }
    return val; 
  }
  return "";
}

function vault_item_html(v) {

  const enc_pwd = get_listview_copy_value(v);
  const icon = enc_pwd ? "fa-copy" : "fa-eye";
  //const notes = escape_html(get_notes(v) || "");
  const notes = get_notes(v) || "";
  const fallback = notes ? "" : get_fallback_field(v);
  const displaySub = notes || fallback;

  const favIcon  = v.is_favorite ? `<i class="fas fa-star vm-fav"></i>` : '';
  const pinIcon = v.is_pinned ? `<i class="fas fa-thumbtack vm-pin"></i>` : '';
  const attachIcon = v.has_attachment ? `<i class="fas fa-paperclip vm-attach"></i>` : '';  

  const fields = extract_fields(v);
  const cfg = category_config[v.slug];

  let subtitle = "";
  if(cfg?.subtitle) {
    subtitle = cfg.subtitle(fields) || "";
  }
  //subtitle = escape_html(subtitle);

  return `<div class="list-item" onclick="view_vault('${v.id}')" data-vault-id="${v.id}">
    <div class="list-icon">
      <i class="fa ${get_vault_icon(v.slug)}"></i>
    </div>
    <div class="list-content">
      <div class="vm-list-title">${favIcon}${attachIcon}${v.title}</div>
      ${subtitle ? `<div class="vm-list-subtitle">${subtitle}</div>` : ``}
      ${displaySub ? `<div class="vm-list-notes">${displaySub}</div>` : ``}
    </div>
    <div class="list-action">
      ${pinIcon} <i class="far ${icon} fa-fw copy-icon" onclick="event.stopPropagation(); ${enc_pwd ? `handleCopyClick(this, '${enc_pwd}', '${v.id}')` : `view_vault('${v.id}')`}"></i>
    </div>
  </div>`;
}

function load_image_thumbnail(img, path) {
  if(!window.resolveLocalFileSystemURL) {
    img.src = path;
    return;
  }

  window.resolveLocalFileSystemURL(path, entry => {
    img.src = entry.toInternalURL();
  }, () => {
    img.outerHTML = `<i class="fas fa-file-image vm-attach-fallback-icon"></i>`;
  });
}

function render_attachments(files = [], edit_mode = false) {
  if (!Array.isArray(files) || !files.length) {
    return "";
  }

  vm_log("render_attachments", files);

  return `<div class="vm-view-section vm-view-section-ind-item">
    <div class="vm-attach-title">Attachments</div>
    <div class="vm-attachments">
      ${files.map(f => {
        const mime = f.mime_type || "";
        const isImage = mime.startsWith("image/");

        let icon = "fa-file";
        if(mime === "application/pdf") icon = "fa-file-pdf";
        else if (mime.includes("excel") || mime.includes("spreadsheet")) icon = "fa-file-excel";
        else if (mime.includes("word")) icon = "fa-file-word";
        else if (mime.includes("zip") || mime.includes("rar")) icon = "fa-file-archive";

        const mediaHTML = isImage
          ? `<img class="vm-attach-thumb" data-path="${f.encrypted_path}" alt="${escape_html(f.file_name)}">`
          : `<i class="fas ${icon}"></i>`;

        return `<div class="vm-attachment-card ${isImage ? 'image' : ''}" data-id="${f.id}"
                     onclick="open_attachment('${f.encrypted_path}','${mime}')">
          ${mediaHTML}
          <div class="vm-attach-info">
            <span class="vm-attach-name">${escape_html(f.file_name)}</span>
            <span class="vm-attach-size">${format_file_size(f.file_size)}</span>
          </div>
          ${edit_mode ? `<span class="vm-attach-remove" onclick="remove_attachment(${f.id}, event)">
              <i class="fas fa-trash"></i>
            </span>` : ""}
        </div>`;
      }).join("")}
    </div>
  </div>`;
}

function format_file_size(bytes) {
  if(!bytes) {
    return "0 KB";
  }
  if(bytes < 1024 * 1024) {
    return (bytes / 1024).toFixed(1) + " KB";
  }  
  if(bytes < 1024 * 1024 * 1024) {
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }
  return (bytes / (1024 * 1024 * 1024)).toFixed(1) + " GB";
}

function extract_fields(row) {
  if(!row || !row.id) {
    return {};
  }

  const key = String(row.id);
  const cached = VaultCache.get(key);
  if(cached) {
    //console.log("cache key ", key);
    return cached;
  }
  //console.log("fallback key ", key);

  try {
    const parsed = typeof row.data_json === "string" ? JSON.parse(row.data_json) : row.data_json;
    const fields = parsed?.fields || {};
    VaultCache.set(key, fields);  
    return fields;
  } catch (e) {
    //console.error("parse error", e);
    VaultCache.set(key, {}); 
    return {};
  }
}

function invalidateVaultCache(id) {
  if(!id) {
    return;
  }

  const key = String(id);  
  VaultCache.remove(key);
}

async function get_vault_by_id(id) {
  const key = String(id);

  if(_MODE_ == "dev") {
    return getMockVaultById(id);
  }

  const cached = VaultCache.getRow(key);
  if(cached) {
    return cached;
  }

  const { sql, values } = VaultDB.buildSelect({
    table: VaultMateConfig.tables.vaults,
    columns: ["*"],
    where: { id: id, status: 1 },
    limit: 1
  });

  const res = await VaultDB.dbExecute(sql, values);
  if(res.rows.length > 0) {
    const row = res.rows.item(0);
    VaultCache.setRow(key, row);
    return row;
  }
  return null;
}

async function get_attachments_by_vault_id(id) {
  const {sql, values} = VaultDB.buildSelect({table: VaultMateConfig.tables.attachments, columns: ["*"], where: {vault_id: id}, orderBy: [{ col: "created_at", dir: "ASC" }]});
  const res = await VaultDB.dbExecute(sql, values);
  const files = [];
  for(let i = 0; i < res.rows.length; i++) {
    files.push(res.rows.item(i));
  }
  return files;
}

async function get_pinned_vault_count() {
    const {sql, values} = VaultDB.buildSelect({
        table: VaultMateConfig.tables.vaults,
        columns: ["count(id) as ct"],
        where: {status: 1, is_pinned: 1}
    });
    const res = await VaultDB.dbExecute(sql, values);
    return res.rows.item(0)?.ct || 0;
}

async function toggle_flag({id, action, field, successOn, successOff, updateView = false, hideSheet = false}) {
  if(!id) {
    return;
  }

  const row = await get_vault_by_id(id);
  if(!row) {
    return;
  }

  const wasOn = row[field] === 1;
  if(!wasOn && field === "is_pinned") {
    const pinnedCount = await get_pinned_vault_count();
    const allowed_count = (await secure_storage(VaultMateConfig.storageKeys.allowed_pin)) ?? VaultMateConfig.default.allowed_pin;
    if(pinnedCount >= allowed_count) {
      show_toast(`Only ${allowed_count} items can be pinned`);
      return;
    }
  }
  await mark_vault_action(id, action, row, true);
  if(updateView) {
    row[field] = wasOn ? 0 : 1;
    render_view_vault_header_state(row);
  }

  if(hideSheet) {
    hide_action_sheet();
  }
  show_toast(wasOn ? successOff : successOn);
}

async function mark_vault_action(id, action, row, update_html = false) {
  const now = Date.now();
  let table = VaultMateConfig.tables.vaults;  
  let shouldRefresh = false;
  let newValue = null;

  vm_log("inside mark_vault_action", id, "action", action, "update_html", update_html);

  if(_MODE_ === "dev") {
    if(action == "favorite") {
        row.is_favorite = row?.is_favorite ? 0 : 1;
    } else if(action == "pin") {
      row.is_pinned = row?.is_pinned ? 0 : 1;
    }    
    update_vault_card(row);
    hide_action_sheet();
    return;
  }

  if(action === "open") {
    await VaultDB.dbExecute(`update ${table} 
      set total_viewed = COALESCE(total_viewed, 0) + 1, last_opened = ?
      where id = ? `, [now, id]);
  } else if(action === "copy" || action == "share") {
    const should_update_usage  = !row?.last_used_at || (now - row.last_used_at) > VaultMateConfig.default.usage_window;
    if(should_update_usage) {
      await VaultDB.dbExecute(`update ${table}
        set total_copied = COALESCE(total_copied, 0) + 1, usage_score = COALESCE(usage_score, 0) + 1, last_used_at = ?
        WHERE id = ?`, [now, id]);
      shouldRefresh = true;
    } else {
      await VaultDB.dbExecute(`update ${table} set total_copied = COALESCE(total_copied, 0) + 1 WHERE id = ?`, [id]);
    }
  } else if(action === "pin") {
    newValue = row?.is_pinned ? 0 : 1;
    await VaultDB.dbExecute(`UPDATE ${table} SET is_pinned = ? WHERE id = ?`, [newValue, id]);
    shouldRefresh = true;
  } else if(action === "favorite") {
    newValue = row?.is_favorite ? 0 : 1;
    await VaultDB.dbExecute(`UPDATE ${table} SET is_favorite = ? WHERE id = ?`, [newValue, id]);
    shouldRefresh = true;
  }

  if(shouldRefresh) {
    refresh_dashboard_action(action);
  }

  if(update_html && row) {
    if(action == "pin") {
      row.is_pinned = newValue;
    } else if(action == "favorite") {
      row.is_favorite = newValue;
    }
    update_vault_card(row);
    hide_action_sheet();
  }
  vm_log("shouldRefresh", shouldRefresh, "action", action);
  return;
}

async function view_vault(id) {
  open_bottom_sheet("vm_view_sheet");
  dgi("view_vault_detail").textContent = "Loading...";
  current_vault_id = id;

  const row = await get_vault_by_id(id); 
  if (!row) {
    show_toast("Vault not found");
    close_bottom_sheet();
    return;
  }

  const encryptedFields = extract_fields(row);
  dgi("vm_view_content").innerHTML = render_view_vault(row, encryptedFields, [], true);
  dgi("view_vault_detail").textContent = row.title || "Vault Item";

  if(_MODE_ !== "dev") {
    mark_vault_action(id, "open", row).catch(() => {});

    if(row.has_attachment) {
      get_attachments_by_vault_id(id).then(attachments => {
        const el = dgi("vm_attach_container");
        if (!el || !attachments.length) return;
        el.innerHTML = render_attachments(attachments);
        el.querySelectorAll(".vm-attach-thumb[data-path]").forEach(img =>
          load_image_thumbnail(img, img.dataset.path)
        );
      }).catch(() => {});
    }
  } else {
    const attachments = window._mock_attachments || [];
    if (attachments.length) {
      const el = dgi("vm_attach_container");
      if (el) {
        el.innerHTML = render_attachments(attachments);
        el.querySelectorAll(".vm-attach-thumb[data-path]").forEach(img =>
          load_image_thumbnail(img, img.dataset.path)
        );
      }
    }
  }
}


function get_field_label(slug, field) {
  return (category_config[slug]?.fields?.[field]?.label || field.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase()));
}

function render_view_vault_header_state(row) {
  //console.log("row render_view_vault_header_state", row);

  dgi("vv-pin").classList.toggle('vm-active', row.is_pinned === 1);
  dgi("vv-fav").classList.toggle('vm-active', row.is_favorite === 1);
}

async function copy_note(el) {
  const value = decodeURIComponent(el.dataset.value);
  const id = el.dataset.id;
  const icon = el.querySelector("i");
  const originalClass = icon.className;

  try {
    await copy_vault(value, id);

    clearTimeout(el._copyTimer);
    icon.className = "fas fa-check fa-fw";
    el._copyTimer = setTimeout(() => {
      icon.className = originalClass;
    }, 1800);

  } catch (e) {
    console.error(e);
    show_toast("Copy failed");
  }
}

async function copyField(btn, value, id) {
    const icon = btn.querySelector("i");
    const originalClass = icon.className;
    try {
      await copy_vault(value, id);

      clearTimeout(btn._copyTimer);
      icon.className = "fas fa-check fa-fw";      

      btn._copyTimer = setTimeout(() => {
        icon.className = originalClass;
      }, 1800);

    } catch (e) {
      console.error(e);
      show_toast("Copy failed");
    }
}

function render_view_vault(row, fields, attachments = [], lazyDecrypt = false) {
  const cfg = category_config[row.slug];
  if(!cfg) {
    return "<div class='invalid_category'>Invalid Category</div>";
  }

  let rowsHTML = "";
  let notesHTML = "";
  let extraHTML = "";
  let ActRowsHTML = "";

  let analysis = null;
  try {
    analysis = row.addl_json ? JSON.parse(row.addl_json).password_analysis : null;
  } catch {}

  const pwdType = analysis?.type || "password";
  const isPin = pwdType === "pin";

  render_view_vault_header_state(row);

  for(const key in cfg.fields) {
    const rules = cfg.fields[key];
    const value = fields[key];
    if(!value) {
      continue;
    }
    const label = get_field_label(row.slug, key);

    if(key.endsWith("_notes")) {
      if(rules.copy) {
        rowsHTML += `<div class="vm-view-row">
            <div class="vm-secure-head">
              <span class="vm-label">${label}</span>
              <div class="vm-actions-view">
                <span class="copy-note" data-value="${encodeURIComponent(value)}" data-id="${row.id}" onclick="copy_note(this)">
                  <i class="far fa-copy fa-fw"></i>
                </span>
              </div>
            </div>
            <div class="vm-value notes_view">${escape_html(value)}</div>
          </div>`;
      } else {
        rowsHTML += `<div class="vm-view-row">
            <span class="vm-label">${label}</span>
            <div class="vm-value notes_view">${escape_html(value)}</div>
          </div>`;
      }
      continue;
    }

    if(rules.secure) {

      if(lazyDecrypt) {
        rowsHTML += `<div class="vm-view-row secure">
            <div class="vm-secure-head">
              <span class="vm-label">${label}</span>
              <div class="vm-actions-view">
                <span onclick="vm_reveal(this)"><i class="far fa-eye fa-fw"></i></span>
                <span onclick="vm_copy_secure(this, '${row.id}')"><i class="far fa-copy fa-fw"></i></span>
              </div>
            </div>
            <div class="vm-value" data-revealed="0" data-enc="${escape_html(value)}">••••••</div>
          </div>`;

      } else {
        rowsHTML += `<div class="vm-view-row secure">
            <div class="vm-secure-head">
              <span class="vm-label">${label}</span>
              <div class="vm-actions-view">
                <span onclick="vm_reveal(this)"><i class="far fa-eye fa-fw"></i></span>
                <span onclick="copyField(this, '${escape_html(value)}', '${row.id}')"><i class="far fa-copy fa-fw"></i></span>
              </div>
            </div>
            <div class="vm-value" data-revealed="0" data-value="${escape_html(value)}">••••••</div>
          </div>`;
      }
      continue;
    }

    if(rules.copy) {
      rowsHTML += `<div class="vm-view-row secure">
          <div class="vm-secure-head">
            <span class="vm-label">${label}</span>
            <div class="vm-actions-view">
              <span onclick="copyField(this, '${escape_html(value)}', '${row.id}')"><i class="far fa-copy fa-fw"></i></span>
            </div>
          </div>
          <div class="vm-value">${escape_html(value)}</div>
        </div>`;
      continue;
    }
    
    const is_url_field = rules.type === "url" || isUrl(value);
    if(is_url_field) {
      rowsHTML += `<div class="vm-view-row">
          <span class="vm-label">${label}</span>
          <span class="vm-value link" onclick="open_external_url('${escape_html(value)}')">${escape_html(value)}</span>
        </div>`;
      continue;
    }
    rowsHTML += `<div class="vm-view-row">
        <span class="vm-label">${label}</span>
        <span class="vm-value">${escape_html(value)}</span>
      </div>`;
  }

  if(has_password_score(row.slug) && Number.isFinite(row.password_score)) {
    const ps = get_password_status(row.password_score);
    extraHTML = `<div class="vm-view-section vm-view-section-ind-item">
      <span class="vm-label">Password security</span>
      <div class="vm-strength-bar">
        <div class="vm-strength-fill ${ps.classname}" style="width:${row.password_score}%"></div>
      </div>
      <div class="vm-password-row">
        <span>${row.password_score}%</span>
        <span class="vm-score-badge ${ps.classname}">
          ${ps.label}
        </span>
      </div>
      <div class="vm-hint">How secure this password is</div>
    </div>`;


    if(row.crack_seconds !== null || row.predictability_score !== null || row.risk_level) {

      extraHTML += `<div class="vm-view-section vm-view-section-ind-item">
        <span class="vm-label">Security Details</span>

        <div class="vm-stat-grid">

          ${row.crack_seconds !== null ? `
          <div class="vm-stat">
            <span class="vm-stat-label">Crack Time</span>
            <span class="vm-stat-value danger">${format_crack_time(row.crack_seconds)}</span>
          </div>` : ``}

          ${row.predictability_score !== null ? `
          <div class="vm-stat">
            <span class="vm-stat-label">Pattern Risk</span>
            <span class="vm-stat-value danger">${row.predictability_score}%</span>
          </div>` : ``}

          ${row.password_updated_at ? `
          <div class="vm-stat">
            <span class="vm-stat-label">Password Updated</span>
            <span class="vm-stat-value">${format_ts(row.password_updated_at)}</span>
          </div>` : ``}

          ${row.risk_level ? `
          <div class="vm-stat">
            <span class="vm-stat-label">Risk Level</span>
            <span class="vm-risk-badge vm-risk-${row.risk_level.toLowerCase()}">
              ${row.risk_level} · ${row.risk_score}%
            </span>
          </div>` : ``}

        </div>
      </div>`;
    }   

    let insights = [];
    let suggestions = [];
    let hasRisk = false;   

    const patterns = analysis?.patterns || [];
    const hasDictionary = patterns.some(p => p.pattern === "dictionary");
    const hasCommon = patterns.some(p => p.dictionary === "passwords");
    const hasDate = patterns.some(p => p.pattern === "date");
    const hasDigits = patterns.some(p => p.pattern === "digits");

    if (row.password_score < 50) {
      if (isPin) {
        if (row.crack_seconds < 60) {
          insights.push("This PIN can be guessed almost instantly");
        } else if (row.crack_seconds < 3600) {
          insights.push("This PIN can be cracked very quickly");
        } else {
          insights.push("This PIN is weak and predictable");
        }
      } 
      else {
        if (hasCommon) {
          insights.push("This password is commonly used and easily guessable");
        } 
        else if (hasDictionary && hasDigits) {
          insights.push("This password combines a word and numbers, making it easy to guess");
        } 
        else if (hasDictionary) {
          insights.push("This password contains common words that attackers can guess");
        } 
        else if (hasDate) {
          insights.push("This password includes predictable date patterns");
        } 
        else if (row.crack_seconds < 60) {
          insights.push("This password can be cracked instantly");
        } 
        else if (row.crack_seconds < 3600) {
          insights.push("This password can be cracked within minutes");
        } 
        else {
          insights.push("This password is weak and can be easily guessed");
        }
      }
    }

    if (!isPin && analysis?.suggestions?.length) {
      suggestions = analysis.suggestions;
    } 
    else if (row.password_score < 50) {
      if (isPin) {
        suggestions.push("Use at least 6 digits");
        suggestions.push("Avoid sequences like 1234 or repeated numbers like 1111");
        suggestions.push("Use random numbers instead of patterns");
      } 
      else {
        if(hasDictionary) {
          suggestions.push("Avoid using names or common words");
        }

        if(hasDigits) {
          suggestions.push("Avoid predictable number patterns");
        }

        if(row.password_score < 30) {
          suggestions.push("Use a longer password with more randomness");
        }
        suggestions.push("Add symbols and uppercase letters");
      }
    }

    if(analysis?.patterns?.length) {
      hasRisk = analysis.patterns.some(p => p.dictionary === "passwords");
    }

    
    if(insights.length || suggestions.length || hasRisk) {
      const typeClass = hasRisk ? "vm-insight-danger" : "vm-insight-warning";

      extraHTML += `
        <div class="vm-view-section vm-insight ${typeClass}">
          
          <div class="vm-insight-title">
            ${
              isPin
                ? "Weak PIN"
                : row.password_score < 30
                ? "Weak Password"
                : hasRisk
                ? "Security Risk"
                : "Security Insights"
            }
          </div>

          ${insights.length
            ? `<div class="vm-insight-problem">${insights.join("<br>")}</div>`
            : ""
          }

          ${hasRisk && !hasCommon ? `<div class="vm-insight-text">
                 This password is commonly used and easily guessable
               </div>`
            : ""}

          ${suggestions.length
            ? `
              <div class="vm-insight-sub">How to improve</div>
              <ul class="vm-insight-list">
                ${suggestions.map(s => `<li>${s}</li>`).join("")}
              </ul>
            `
            : ""
          }

        </div>
      `;
    }

  }

  if(row.rem_enabled) {
    let before = row.rem_before ? row.rem_before + " days" : '';
    rowsHTML += `<div class="vm-view-row">
      <span class="vm-label">Reminder</span>
      <div class="vm-value">Enabled</div>
      <div class="vm-value">Notify before: ${before}</div>
    </div>`;
  }

  //console.log("row", row)

  rowsHTML += `<input type="hidden" id="view_vault_slug" value="${row.slug}">`;  

  ActRowsHTML += `<div class="vm-view-section vm-view-section-ind-item">
  <span class="vm-label">Vault Activity</span>

  <div class="vm-stat-grid">

    <div class="vm-stat">
      <span class="vm-stat-label">Usage</span>
      <span class="vm-stat-value">${row.usage_score || 0} times</span>
    </div>

    <div class="vm-stat">
      <span class="vm-stat-label">Last Used</span>
      <span class="vm-stat-value">${row.last_used_at ? format_ts(row.last_used_at) : "never"}</span>
    </div>

    <div class="vm-stat">
      <span class="vm-stat-label">Created</span>
      <span class="vm-stat-value">${format_ts(row.created_at)}</span>
    </div>

    <div class="vm-stat">
      <span class="vm-stat-label">Updated</span>
      <span class="vm-stat-value">${format_ts(row.updated_at)}</span>
    </div>

  </div>
</div>`;



  return `<div class="vm-view-wrapper">
      <div class="vm-view-section">
        ${rowsHTML}
      </div>
      ${notesHTML}  
      ${extraHTML}  
      ${ActRowsHTML}              
      <div id="vm_attach_container">${render_attachments(attachments)}</div>
    </div>`;
}

async function decrypt_fields(slug, fields) {
  const cfg = category_config[slug];
  if(!cfg || !cfg.fields) {
    return fields;
  }
  const out = {};
  for(const [k, v] of Object.entries(fields)) {
    const rule = cfg.fields[k];
    if (rule?.secure && v) {
      try {
        out[k] = await dec(v);
      } catch (e) {
        out[k] = "";
      }
    } else {
      out[k] = v;
    }
  }
  return out;
}

async function vm_reveal(btn) {
  const wrap = btn.closest(".secure");
  const valEl = wrap.querySelector(".vm-value");
  const icon  = btn.querySelector("i");

  if(valEl.dataset.revealed === "1") {
    valEl.textContent = "••••••";
    valEl.dataset.revealed = "0";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
    return;
  }

  if (!valEl.dataset.decrypted) {
    const encValue = valEl.dataset.enc;
    if (!encValue) { show_toast("Nothing to reveal"); return; }
    btn.style.opacity = "0.4";
    try {
      const plain = await dec(encValue);
      valEl.dataset.value    = plain;
      valEl.dataset.decrypted = "1";
    } catch (e) {
      show_toast("Decrypt failed");
      return;
    } finally {
      btn.style.opacity = "";
    }
  }

  valEl.textContent = valEl.dataset.value;
  valEl.dataset.revealed = "1";
  icon.classList.remove("fa-eye");
  icon.classList.add("fa-eye-slash");
}

async function vm_copy_secure(btn, vaultId) {
  const wrap = btn.closest(".secure");
  const valEl = wrap.querySelector(".vm-value");
  const icon = btn.querySelector("i");
  const originalClass = icon.className;
  let plain;

  if (valEl.dataset.decrypted) {
    plain = valEl.dataset.value;
  } else {
    const encValue = valEl.dataset.enc;
    if (!encValue) {
      show_toast("Nothing to copy");
      return;
    }
    btn.style.opacity = "0.4";
    try {
      plain = await dec(encValue);
      valEl.dataset.value = plain;
      valEl.dataset.decrypted = "1";
    } catch (e) {
      console.error(e);
      show_toast("Copy failed");
      return;
    } finally {
      btn.style.opacity = "";
    }
  }

  try {
    await copy_vault(plain, vaultId);
    clearTimeout(btn._copyTimer);
    icon.className = "fas fa-check fa-fw";

    btn._copyTimer = setTimeout(() => {
      icon.className = originalClass;
    }, 1800);

  } catch (e) {
    console.error(e);
    show_toast("Copy failed");
  }
}


async function edit_view_vault() {
  const id = current_vault_id;
  close_bottom_sheet();
  setTimeout(async () => {await edit_vault(id);}, 100); 
}

async function edit_vault(id) {
  let attachments;
  clear_upgrade_mdl();

  let row = await get_vault_by_id(id);
  vm_log("edit vault", row);

  if(!row) {
    show_toast("Vault not found");
    return;
  }

  const fileField = category_config[row.slug]?.fileField;
  if(fileField) {
    pending_attachments[fileField] = [];
    const input = dgi(fileField);
    if(input) {
      input.value = "";
    }
  }

  if(_MODE_ !== "dev") {    
    attachments = await get_attachments_by_vault_id(id);
    vm_log("attachments", attachments);
  }
  hide_action_sheet();

  is_edit_mode = true;
  edit_vault_id = id;

  edit_attachments = attachments ? [...attachments] : [];

  const encrypted = extract_fields(row);
  const data = await decrypt_fields(row.slug, encrypted);
  
  open_bottom_sheet("vm_bottomSheet");

  set_active_category(row.slug);
  lock_categories(true);

  requestAnimationFrame(() => {
    fill_vault_fields(row.slug, data);
    dgi("vault_id").value = id;
    dgi("category_slug").value = row.slug;
    dgi("vm_edit_attachments").innerHTML = render_attachments(edit_attachments, true);

    document.querySelectorAll(".vm-attach-thumb[data-path]").forEach(img => {
      load_image_thumbnail(img, img.dataset.path);
    });

    if(category_config[row.slug]?.expiryField) {
      const chk = dgi("vm_enable_reminder");
      const sel = dgi("vm_rem_before");
      const opt = dgi("vm_reminder_options");

      if(chk && sel && opt) {
        chk.checked = !!row.rem_enabled;
        sel.value = row.rem_before || VaultMateConfig.default.reminder_before;
        opt.classList.toggle("vm_rem_visible", row.rem_enabled);
        opt.classList.toggle("vm_rem_hidden", !row.rem_enabled);
      }
    }    
  });
}

function remove_attachment(id, e) {
  e.stopPropagation();

  ons.notification.confirm({
    message: "Remove this attachment?",
    title: "Confirm",
    buttonLabels: ["Cancel", "Remove"],
    primaryButtonIndex: 1
  }).then(confirmed => {
    if (!confirmed) {
      return;
    }

    const idx = edit_attachments.findIndex(a => a.id === id);
    if (idx === -1) {
      return;
    }

    edit_attachments[idx]._deleted = true;
    
    const card = e.target.closest(".vm-attachment-card");
    if (card) {
      card.remove();
    }

    const wrap = dgi("vm_edit_attachments");
    if(wrap && !wrap.querySelector(".vm-attachment-card")) {
      wrap.innerHTML = "<div class='vm-no-attachments'>No attachments</div>";
    }
  });
}

function fill_vault_fields(slug, data) {
  const cfg = category_config[slug];
  if(!cfg || !cfg.fields) {
    return;
  }
  for(const field in cfg.fields) {
    const el = dgi(field);
    if(el && data[field] !== undefined) {
      el.value = data[field];
    }
  }
}

async function resolve_include_password(msg) {
  const pref = (await secure_storage(VaultMateConfig.storageKeys.key_share_copy_data)) ?? VaultMateConfig.default.key_share_copy_data;
  if(pref === "yes") {
    return true;
  }
  if(pref === "no") {
    return false;
  }
  return confirm(msg);
}

function category_has_secure_field(slug) {
  const cfg = category_config[slug];
  if(!cfg || !cfg.fields) return false;
  for(const key in cfg.fields) {
    if(cfg.fields[key].secure) return true;
  }
  return false;
}

async function build_vault_text(includeSecure = false) {
    if(!current_vault_id) {
        return "";
    }
    const row = await get_vault_by_id(current_vault_id);
    if(!row) {
        return "";
    }
    const cfg = category_config[row.slug];
    if(!cfg) {
        return "";
    }
    const encryptedFields = extract_fields(row);
    const fields = await decrypt_fields(row.slug, encryptedFields);
    const lines = [];
    for(const key in cfg.fields) {
        const rule = cfg.fields[key];
        const value = fields[key];
        if(value === null || value === undefined || value === "") {
            continue;
        }
        if(rule.secure && !includeSecure) {
            continue;
        }
        let label = rule.label || key;
        if(typeof value === "string") {
            lines.push(`${label}: ${value.trim()}`);
        } else {
            lines.push(`${label}: ${value}`);
        }
    }
    if(row.rem_enabled) {
        lines.push("");
        lines.push("Reminder: Enabled");
        lines.push(`Notify Before: ${row.rem_before} day${row.rem_before > 1 ? "s" : ""}`);
    }
    return lines.join("\n");
}

async function all_action(action = "copy") {
  let cur_slug = dgi("view_vault_slug").value;  
  let inc_sec = false;
  const has_secure = category_has_secure_field(cur_slug);
  if(has_secure) {
    let msg = action === "share" ? "share" : "copy";
    inc_sec = await resolve_include_password(
      `Include passwords?\n\n` +
      `Tap OK to ${msg} passwords.\n` +
      `Tap Cancel to ${msg} without passwords.\n\n` +
      `You can change this anytime in Settings.`
    );
  }
  const text = await build_vault_text(inc_sec);
  if(!text) {
    return;
  }
  if(action == "share") {
      if(window.plugins?.socialsharing) {
        window.plugins.socialsharing.share(
          text,                 // message
          "Vault Details",      // subject
          null,                 // files
          null,                 // url
          async () => {
            let row = await get_vault_by_id(current_vault_id);
            await mark_vault_copied(current_vault_id, "share", row);
          }, () => {
        });
      }
      else {
        await copy_vault(text, current_vault_id);
      }
    
  } else {
    await copy_vault(text, current_vault_id);
  }
}

let lastCopiedText = null;
let clipboardTimer = null;

async function copy_vault(text, id) {

  if(!text || text.trim() === "") {
    show_toast("Nothing to copy");
    throw new Error("Nothing to copy");
  }

  if(!id) {
    show_toast("Invalid vault!");
    throw new Error("Invalid vault");
  }

  let row;
  try {
    row = await get_vault_by_id(id);
  } catch (e) {
    console.error(e);
    show_toast("Error fetching vault");
    throw e;
  }

  if(!row) {
    show_toast("Vault not found");
    throw new Error("Vault not found");
  }

  if(!cordova?.plugins?.clipboard) {
    show_toast("Clipboard not available");
    throw new Error("Clipboard not available");
  }

  let clear_duration = parseInt( (await secure_storage(VaultMateConfig.storageKeys.key_clear_clipboard)) || VaultMateConfig.default.clear_clipboard_duration, 10);
  if(!clear_duration || clear_duration < 5000) {
    clear_duration = VaultMateConfig.default.clear_clipboard_duration;
  }

  return new Promise((resolve, reject) => {
    cordova.plugins.clipboard.copy(text, async () => {
      lastCopiedText = text;
      const seconds = Math.round(clear_duration / 1000);
      show_toast(`Copied • clears in ${seconds}s`);
      clear_clipboard_after(clear_duration);
      try {
        await mark_vault_action(id, "copy", row);
      } catch (e) {
        console.error(e);
      }
      resolve(true);
    }, () => {
      show_toast("Copy failed");
      reject(new Error("Copy failed"));
    });
  });
}

function remove_vault_item_from_list(id) {
  const el = document.querySelector(`.vm_vault_list .list-item[data-vault-id="${id}"]`);
  if(!el) {
    return;
  }
  el.classList.add("fade-out");
  setTimeout(() => {
    el.remove();
    if(document.querySelectorAll(".vm_vault_list .list-item").length < 1) {
      dgi("vm_vault_list").innerHTML = empty_html("fa-shield-alt", etitle, edesc);
    }
    show_visible(); 
  }, 300);
}

//from view bottom sheet
function delete_sheet_vault() {
  if(!current_vault_id) {
    show_toast("Invalid vault. Please try again.");
    return;
  }
  delete_vault(current_vault_id);
}

async function delete_vault(id) {
  const row = await get_vault_by_id(id);
  if(!row) {
    show_toast("Invalid vault. Please try again.");
    return;
  }

  hide_action_sheet();
  const index = await ons.notification.confirm("Are you sure you want to delete this vault?");
  if(index !== 1) {
    return;
  }

  try {
    const q1 = VaultDB.buildDelete(VaultMateConfig.tables.attachments, {vault_id: id});
    await VaultDB.dbExecute(q1.sql, q1.values);

    const q2 = VaultDB.buildDelete(VaultMateConfig.tables.vaults, {id:id});
    await VaultDB.dbExecute(q2.sql, q2.values);

    const existing = await get_vault_reminder(id);
    if(existing && existing.id) {      
        await delete_rm_with_notification(existing.id, 1)
    }

    VaultCache.removeRow(id);
    invalidateVaultCache(id);

    show_toast("Vault deleted successfully");
    close_bottom_sheet();
    remove_vault_item_from_list(id);
    refresh_dashboard_action("delete_vault");

    let total = await count_vaults_db(vault_filters);
    render_vault_meta(total, 1);
    renderUpgradeInline("vault", total, VAULT_LIMIT);

  } catch (e) {
    show_toast("Delete failed");
    if(_MODE_ == "dev") {
      close_bottom_sheet();
      remove_vault_item_from_list(id);
    }    
  }
}

function get_vault_sort(filters = {}) {
  switch (filters.sort) {
    case "recent_used":
      return [{col: "last_used_at", dir: "DESC"}];
    case "old_first":
      return [{col: "created_at", dir: "ASC"}];
    // case "recently_viewed":
    //   return [{col: "last_opened", dir: "DESC"}];
    case "most_used":
      return [{col: "usage_score", dir: "DESC"}];
    case "weak_pwd":
      return [{col: "CASE WHEN password_score > 0 THEN password_score ELSE 999 END", dir: "ASC", raw: true}];
    case "strong_pwd":
      return [{col: "CASE WHEN password_score > 0 THEN password_score ELSE -1 END", dir: "DESC", raw: true}];
    default:
      return [{col: "created_at", dir: "DESC"}];
  }
}

function get_vault_selected_categories() {
  return Array.from(document.querySelectorAll('#vault_category_checklist input:checked')).map(el => el.value);
}

function render_category_checklist(container, categories) {
  container.innerHTML = categories.map(cat => `
    <label class="vm_category_item">
      <input type="checkbox" value="${cat.slug}">
      <span class="vm_category_icon">
        <i class="fa ${cat.icon}"></i>
      </span>
      <span class="vm_category_name">${cat.name}</span>
    </label>
  `).join("");
}

function is_vault_filter_applied(f = {}) {
    return !!(
        f.search ||
        f.pinFav ||
        f.vaultReminderMissing ||
        f.weakPassword || 
        f.hashPassword ||
        f.security ||
        (Array.isArray(f.categories) && f.categories.length) ||
        (f.dateRange && (f.dateRange.from || f.dateRange.to))
    );
}

function select_all_categories() {
  document.querySelectorAll('#vault_category_checklist input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function unselect_all_categories() {
  document.querySelectorAll('#vault_category_checklist input[type="checkbox"]').forEach(cb => cb.checked = false);
}

function get_vault_reminder_html(slug) {
  const cfg = vault_category_map[slug];
  if(!cfg || !cfg.reminder) {
    return "";
  }
  return `
    <div class="vm_reminder_block" id="vm_reminder_block">
      <div class="vm_reminder_row">
        <label class="vm_checkbox_inline">
          <input type="checkbox" id="vm_enable_reminder">
          <span>Enable Reminder</span>
        </label>
        <div class="vm_rem_inline hidden" id="vm_reminder_options">
          <span class="vm_rem_text">Remind me before</span>
          <select id="vm_rem_before" class="vm_rem_select_inline">
            <option value="1">1 day</option>
            <option value="3">3 days</option>
            <option value="7" selected>7 days</option>
            <option value="15">15 days</option>
            <option value="30">30 days</option>
          </select>
        </div>
      </div>
    </div>
  `;
}

function init_vault_reminder_events() {
  const chk = dgi("vm_enable_reminder");
  const opt = dgi("vm_reminder_options");
  if(!chk || !opt) {
    return;
  }
  opt.classList.add("vm_rem_hidden");
  chk.onchange = () => {
    if(chk.checked) {
      opt.classList.remove("vm_rem_hidden");
      opt.classList.add("vm_rem_visible");
    } else {
      opt.classList.remove("vm_rem_visible");
      opt.classList.add("vm_rem_hidden");

      const slug = dgi("category_slug")?.value;
      const cfg = category_config[slug];
      if(cfg && cfg.expiryField) {
        const expEl = dgi(cfg.expiryField);
        if(expEl) {
          expEl.classList.remove("error-field");
        }
      }
    }
  };
}





let copy_select_prev_title = "";

async function copy_selected_fields() {
  const menu = dgi("vaultMenu");
  if(menu) {
    menu.classList.add("hide");
  }

  if(!current_vault_id) {
    show_toast("Invalid vault!");
    return;
  }

  const row = await get_vault_by_id(current_vault_id);
  if(!row) {
    show_toast("Vault not found");
    return;
  }

  const cfg = category_config[row.slug];
  if(!cfg || !cfg.fields) {
    show_toast("Invalid category");
    return;
  }

  const encryptedFields = extract_fields(row);
  const decryptedFields = await decrypt_fields(row.slug, encryptedFields);

  let html = "";

  for(const key in cfg.fields) {
    const rawValue = encryptedFields[key];
    if(rawValue === null || rawValue === undefined || rawValue === "") {
      continue;
    }
    const rule = cfg.fields[key];
    const label = get_field_label(row.slug, key);

    let displayValue;
    if(rule.secure) {
      displayValue = "••••••••";
    } else {
      const val = String(decryptedFields[key] ?? "").trim();
      displayValue = val.length > 60 ? val.slice(0, 60) + "…" : val;
    }

    html += `<label class="vm_copy_select_card" data-key="${key}">
        <input type="checkbox" class="vm_copy_select_chk" value="${key}">
        <span class="vm_copy_select_check"><i class="fas fa-check"></i></span>
        <span class="vm_copy_select_text">
          <span class="vm_copy_select_label">${escape_html(label)}</span>
          <span class="vm_copy_select_value">${escape_html(displayValue)}</span>
        </span>
      </label>`;
  }

  if(!html) {
    show_toast("No fields available to copy");
    return;
  }

  const listEl = dgi("vm_copy_select_list");
  const titleEl = dgi("view_vault_detail");
  const viewAll = dgi("vm_view_content_all");
  const viewActions = dgi("vm_view_actions");
  const selectPanel = dgi("vm_copy_select_panel");
  const selectActions = dgi("vm_copy_select_actions");

  const missing = [];
  if(!listEl) missing.push("vm_copy_select_list");
  if(!titleEl) missing.push("view_vault_detail");
  if(!viewAll) missing.push("vm_view_content_all");
  if(!viewActions) missing.push("vm_view_actions");
  if(!selectPanel) missing.push("vm_copy_select_panel");
  if(!selectActions) missing.push("vm_copy_select_actions");

  if(missing.length) {
    console.error("copy_selected_fields: missing elements ->", missing);
    show_toast("Setup incomplete: missing " + missing.join(", "));
    return;
  }

  listEl.innerHTML = html;

  copy_select_prev_title = titleEl.textContent;
  titleEl.textContent = "Select fields to copy";

  viewAll.classList.add("hide");
  viewActions.classList.add("hide");
  selectPanel.classList.remove("hide");
  selectActions.classList.remove("hide");

}

function cancel_copy_selected() {
  dgi("vm_copy_select_panel").classList.add("hide");
  dgi("vm_copy_select_actions").classList.add("hide");
  dgi("vm_view_content_all").classList.remove("hide");
  dgi("vm_view_actions").classList.remove("hide");
  if(copy_select_prev_title) {
    dgi("view_vault_detail").textContent = copy_select_prev_title;
  }
}


async function build_selected_vault_text() {
  if(!current_vault_id) {
    return "";
  }
  const row = await get_vault_by_id(current_vault_id);
  if(!row) {
    return "";
  }
  const cfg = category_config[row.slug];
  if(!cfg) {
    return "";
  }

  const selectedKeys = Array.from(
    document.querySelectorAll("#vm_copy_select_list .vm_copy_select_chk:checked")
  ).map(cb => cb.value);

  if(!selectedKeys.length) {
    show_toast("Select at least one field");
    return "";
  }

  const encryptedFields = extract_fields(row);
  const fields = await decrypt_fields(row.slug, encryptedFields);

  const lines = [];
  for(const key of selectedKeys) {
    const rule = cfg.fields[key];
    const value = fields[key];
    if(value === null || value === undefined || value === "") {
      continue;
    }
    const label = rule?.label || get_field_label(row.slug, key);
    lines.push(`${label}: ${String(value).trim()}`);
  }
  return lines.join("\n");
}

async function copy_selected_action(action = "copy") {
  const text = await build_selected_vault_text();
  if(!text) {
    return;
  }

  const id = current_vault_id;

  if(action === "share") {
    if(window.plugins?.socialsharing) {
      window.plugins.socialsharing.share(
        text,
        "Vault Details",
        null,
        null,
        async () => {
          const row = await get_vault_by_id(id);
          await mark_vault_action(id, "share", row);
        },
        () => {}
      );
    } else {
      await copy_vault(text, id);
    }
  } else {
    await copy_vault(text, id);
  }

  cancel_copy_selected();
}







async function count_vaults_db(filters = {}) {

    if(_MODE_ === "dev") {
        return Math.floor(Math.random() * 2) + 2;
    }

    const {whereClause, whereValues} = build_vault_where(filters);
    const {sql, values} = VaultDB.buildSelect({
        table: VaultMateConfig.tables.vaults,
        columns: ["count(*) as ct"],
        where: whereClause
    });
    values.push(...whereValues);
    const res = await VaultDB.dbExecute(sql, values);
    return res.rows.item(0).ct;
}


async function openVaultOptions(id) {
    activeVaultId = id;
    if(navigator.vibrate) {
        navigator.vibrate(20);
    }
    const row = await get_vault_by_id(activeVaultId);
    if(!row) {
        return;
    }

    const sheet = dgi("vaultActionSheet");
    sheet.title = row.title || "Vault";    

    dgi("as-pin-text").textContent =  row.is_pinned ? "Unpin" : "Pin";
    dgi("as-fav-text").textContent = row.is_favorite ? "Unfavorite" : "Favorite";
    sheet.show();
}


window._mock_attachments = [
  {
    id: 1,
    vault_id: 1,
    file_name: "passport.jpg",
    mime_type: "image/jpeg",
    encrypted_path: "https://picsum.photos/200/200",
    file_size: 34503
  },
  {
    id: 2,
    vault_id: 1,
    file_name: "sample.pdf",
    mime_type: "application/pdf",
    encrypted_path: "/fake/path/sample.pdf",
    file_size: 18400
  }
];


function getMockVaultById(id) {
  const list = getMockVaultData();
  return list.find(item => String(item.id) === String(id)) || null;
}

function getMockVaultData() {
  return [
    {
      id: 1,
      slug: "general",
      title: "Google Account",
      created_at: 1700000000,
      updated_at: 1789000000,
      password_score: 99,
      is_pinned : 1,
      is_favorite : 1,

      crack_seconds: 120,    
      predictability_score: 40,
      password_updated_at: 1789000000,
      risk_level: "Medium",
      risk_score: 67,

      data_json: JSON.stringify({
        fields: {
          general_account_name: "Google Account",
          general_username: "vasagan@gmail.com",
          general_password: "eyJjdCI6ImR4Vi9yY2ZhNi9lUVBSa1Y3VlRaVmc9PSIsIml2IjoiY2VmNDRlMmE3NmRmZDE3YWZkMjZjOWFjZjY2ZDM2YzQiLCJzIjoiOWI2YmE3MjIyYTM1MTFjOTQ4ZDJmMDdlYzVkNzc1OWMifQ==",
          general_website: "https://google.com",
          general_phone_no: "9876543210",
          general_notes: "Primary personal account"
        }
      })
    },
    {
      id: 1000,
      slug: "general",
      title: "Test Gmail Account",
      created_at: 1700000000,
      updated_at: 1789000000,

      is_pinned: 1,
      is_favorite: 1,

      password_score: 20,                 // smooth score
      password_hash: "937e8d5fbb48bd4949536cd65b8d35c426b80d2f830c5c308e2cdec422ae2244",
      password_updated_at: 1789000000,

      similar_signature: "pre|rem|ema|ma1|a14|142|426",
      predictability_score: 55,
      crack_seconds: 120,                 // 2 mins
      risk_score: 90,
      risk_level: "Medium",

      usage_score: 5,
      last_used_at: 1788900000000,
      last_opened: 1788990000000,
      total_copied: 3,
      total_viewed: 25,

      has_attachment: 1,
      deleted_at: null,
      status: 1,

      search_text: "gmail account vasagan@gmail.com google personal mail",
      fingerprint: "e562ec2b3488b401e2dc31ff61d2c0a5ca0e2675fbcbb399e39ca713dd539638",
      normalized_value: "gmail account|vasagan@gmail.com",

      addl_json: JSON.stringify({
        password_analysis: {
          score: 2,
          warning: "This password contains predictable patterns",
          suggestions: [
            "Add another word or two",
            "Avoid using names or common words",
            "Include symbols and uppercase letters"
          ],
          patterns: [
            { pattern: "dictionary", dictionary: "english_wikipedia" },
            { pattern: "sequence", dictionary: null }
          ],
          guesses_log10: 7.2
        }
      }),

      data_json: JSON.stringify({
        fields: {
          general_account_name: "Gmail Account",
          general_username: "vasagan@gmail.com",
          general_password: "eyJjdCI6ImR4Vi9yY2ZhNi9lUVBSa1Y3VlRaVmc9PSIsIml2IjoiY2VmNDRlMmE3NmRmZDE3YWZkMjZjOWFjZjY2ZDM2YzQiLCJzIjoiOWI2YmE3MjIyYTM1MTFjOTQ4ZDJmMDdlYzVkNzc1OWMifQ==",
          general_website: "https://mail.google.com",
          general_phone_no: "9876543210",
          general_notes: "Primary personal Gmail account"
        }
      })
  }];
}
