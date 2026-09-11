let _isPremiumCache = null;
let app_enc_key = null;
async function get_app_key() {
  if(app_enc_key) {
    return app_enc_key;
  }

  let key = await secure_storage(VaultMateConfig.storageKeys.app_enc_key);
  if(!key) {
    key = CryptoJS.lib.WordArray.random(32).toString(); 
    await secure_storage(VaultMateConfig.storageKeys.app_enc_key, key);
  }
  app_enc_key = key;
  return key;
}

//platforms/android/app/build/outputs/bundle/release/app-release.aab

async function isPremiumUser(force = false) {
  if (!force && _isPremiumCache !== null) {
    return _isPremiumCache;
  }

  try {
    const value = await secure_storage(VaultMateConfig.storageKeys.isPaidUser); 
    //const value = "1";
    _isPremiumCache = value === "1";
    return _isPremiumCache;
  } catch (e) {
    console.error("isPremiumUser error", e);
    return false;
  }
}

const VaultCache = {
  fields: {},
  rows: {},

  getRow(id) {
    return this.rows[id];
  },
  setRow(id, row) {
    this.rows[id] = row;
  },
  removeRow(id) {
    delete this.rows[id];
    delete this.fields[id]; 
  },

  get(id) {
    return this.fields[id];
  },
  set(id, value) {
    this.fields[id] = value;
  },
  remove(id) {
    delete this.fields[id];
  },
  clear() {
    this.fields = {};
  }
};

function show_page_loader() {
  dgi("vmi_loader_overlay").classList.remove("hide");
}

function hide_page_loader() {
  dgi("vmi_loader_overlay").classList.add("hide");
}

function format_crack_time(sec) {
  if (sec === null || sec === undefined) return "-";
  if (sec < 1) return "Instantly";
  if (sec < 60) return `${Math.round(sec)} sec`;
  if (sec < 3600) return `${Math.round(sec / 60)} min`;
  if (sec < 86400) return `${Math.round(sec / 3600)} hrs`;
  if (sec < 2592000) return `${Math.round(sec / 86400)} days`;
  if (sec < 31536000) return `${Math.round(sec / 2592000)} months`;

  const years = sec / 31536000;
  if (years < 1000) return `${Math.round(years)} years`;
  if (years < 1000000) return "Centuries";
  return "Millennia";
}

function vm_normalize(val) {
  if (!val || !String(val).trim()) return "";

  return String(val)
    .toLowerCase()
    .trim()
    .replace(/\s+/g, " "); 
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function goback_home() {
  window.location.href = "index.html";
}

function copy_text(text) {
  cordova.plugins.clipboard.copy(text, () => {
      show_toast(`Copied`);
    }, () => {
      show_toast("Copy failed");
  });
}

function log_step(label, start, last) {
  const now = performance.now();
  vm_log(`${label} | step: ${(now - last).toFixed(2)}ms | total: ${(now - start).toFixed(2)}ms`);
  return now;
}

function billing_debug(msg) {
    if(!PRINT_LOG) {
        return false;
    }
    const el = dgi("billing_debug");

    if(!el) {
        return;
    }

    let output = msg;

    if(typeof msg === "object" && msg !== null) {
        try {
            output = JSON.stringify(msg, null, 2);
        } catch(e) {
            output = String(msg);
        }
    }

    el.value += output + "\n\n";
    el.scrollTop = el.scrollHeight;
}

function safe_alert(message, callback) {
  try {
    if(navigator.notification && navigator.notification.alert) {
      navigator.notification.alert(message, callback || function(){}, VaultMateConfig.default.app_name, "OK");
      return;
    }
    if(typeof ons !== "undefined" && ons.notification) {
      ons.notification.alert(message).then(callback || function(){});
      return;
    }
  } catch(e) {
    
  }
  window.alert(message);
  if(callback) {
    callback();
  }
}

var left_back;
function set_custom_title(title, class_name, tab_back = 0) {
  document.getElementById("custom_page_title").innerHTML = title;
  const custom_header = document.querySelector('.custom_header_all');
  custom_header.classList.remove("vm-hidden");
  custom_header.classList.add("cur_page", class_name);

  left_back = document.querySelector("."+class_name+" .left");
  if(left_back) {
    left_back.addEventListener("click", tab_back ? handleLeftClick : handleLeft);
  }
}

function is_internet(callback) {
  const online = (typeof navigator !== "undefined") ? navigator.onLine : true;

  if(!online) {
    ons.notification.confirm({
      title: 'Connection Required',
      message: 'Please connect to the internet to see pricing and activate Premium.',
      buttonLabels: ["Cancel", "Retry"],
      primaryButtonIndex: 1,
      callback: function(index) {
        if(index === 1) {
          show_toast("Retrying...");
          setTimeout(() => {
            const retryOnline = navigator.onLine;
            if(retryOnline) {
              if(typeof callback === "function") {
                callback();
              }
            } else {
              is_internet(callback);
            }
          }, 1500);
        } else {
          const tabbar = dgi("mainTabbar");
          if(tabbar && tabbar.getActiveTabIndex() === 4) {
            let prevTab = LAST_TAB_INDEX;
            if(prevTab === undefined || prevTab === null || prevTab === 4) {
              prevTab = 0; 
            }
            tabbar.setActiveTab(prevTab);
            LAST_TAB_INDEX = null;
          } else {
            close_bottom_sheet();
          }
        }
      }
    });
    return false;
  }
  if(typeof callback === "function") {
    callback();
  }
  return true;
}

async function renderUpgradeInline(type, used, limit) {
  let parent;
  if(type === "vault") {
    parent = dgi("vm_v_sec");
  } else {
    parent = dgi("vm_r_sec");
  }

  if(!parent) {
    return;
  }
  const el = parent.querySelector(".vm_upgrade_inline");
  const textEl = parent.querySelector(".vm_upgrade_text");
  if(!el || !textEl) {
    return;
  }

  try {
      const isPremium = await isPremiumUser();
      if(isPremium || used < 1) {
          el.classList.add("hide");
          return;
      }

      const remaining = Math.max(0, limit - used);
      const noun = type === "vault" ? "vault" : "reminder";
      let label = "";

      if(remaining < 1) {
          label = `${noun.charAt(0).toUpperCase() + noun.slice(1)} limit reached`;
      } else {
          label = `${remaining} ${noun}${remaining > 1 ? "s" : ""} left`;
      }
      textEl.innerText = label;
      el.classList.remove("hide");

  } catch (err) {
      console.error("renderUpgradeInline error", err);
  }
}

function handleLeftClick() {
  const tabbar = dgi("mainTabbar");
  if(!tabbar) {
    return;
  }

  const prevTab = LAST_TAB_INDEX ?? 0; // fallback dashboard
  tabbar.setActiveTab(prevTab);
  LAST_TAB_INDEX = null;
}
        
function generate_hash(mpin) {
  return CryptoJS.SHA256(mpin).toString();
}

function generate_salt() {
  return CryptoJS.lib.WordArray.random(16);
}

function generate_salt_base64(salt) {
  return CryptoJS.enc.Base64.stringify(salt);
}

function show_button_loader(btn) {
  btn.classList.add("loading");
}

function toggleVaultMenu(e) {
  e.stopPropagation();
  document.getElementById('vaultMenu').classList.toggle('hide');
}

function hide_button_loader(btn) {
  btn.classList.remove("loading");
}

function ucfirst(str) {
  if(!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function close_db_fully() {
  return new Promise((resolve) => {
    VaultDB.resetDB(VaultMateConfig.database.name, function() {
      //console.log("DB fully closed");
      resolve();
    });
  });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function format_expiry(val) {
  if (!val) {
    return "";
  }

  let ts;
  if(!isNaN(val)) {
    ts = Number(val);
    if (ts > 1e12) ts = Math.floor(ts / 1000);
  } else {
    ts = Math.floor(new Date(val).getTime() / 1000);
  }

  const now = Math.floor(Date.now() / 1000);
  const diff = ts - now;

  const day = 86400;
  const days = Math.floor(diff / day);

  if(diff < 0) {
    const past = Math.abs(days);

    if (past === 0) return "Expired today";
    if (past === 1) return "Expired yesterday";
    if (past < 30) return `Expired ${past} day${past > 1 ? "s" : ""} ago`;

    const months = Math.floor(past / 30);
    if(past < 365) {
      return `Expired ${months} month${months > 1 ? "s" : ""} ago`;
    }
    const years = Math.floor(past / 365);
    return `Expired ${years} year${years > 1 ? "s" : ""} ago`;
  }

  if (days === 0) return "Expires today";
  if (days === 1) return "Expires tomorrow";
  if (days < 30) return `Expires in ${days} day${days > 1 ? "s" : ""}`;

  const months = Math.floor(days / 30);
  if(days < 365) {
    return `Expires in ${months} month${months > 1 ? "s" : ""}`;
  }

  const years = Math.floor(days / 365);
  return `Expires in ${years} year${years > 1 ? "s" : ""}`;
}

function reminderRequiredSlugs() {
  return vault_categories.filter(c => c.reminder).map(c => c.slug);
}

function generate_cipher_key(mpin, salt) {
  return CryptoJS.PBKDF2(mpin, salt, {
    keySize: 256/32,
    iterations: VaultMateConfig.default.iterations,
    hasher: CryptoJS.algo.SHA256
  }).toString();
}

async function secure_storage(key, value = undefined) {
  return new Promise((resolve, reject) => {   
    if(_MODE_ === "dev") {
      try {
        if(value !== undefined) {
          localStorage.setItem(key, String(value));
          resolve(true);
        } else {
          resolve(localStorage.getItem(key));
        }
      } catch (e) {
        reject(e);
      }
      return;
    }
    if(value !== undefined) {
      secureStorage.set(() => resolve(true), err => reject(err), key, String(value));
      return;
    }
    secureStorage.get(val => resolve(val), () => resolve(null), key);
  });
}

async function secure_remove(key) {
  return new Promise((resolve, reject) => {
    if(_MODE_ === "dev") {
      try {
        localStorage.removeItem(key);
        resolve(true);
      } catch(e) {
        reject(e);
      }
      return;
    }
    secureStorage.remove(() => resolve(true), () => resolve(false), key);
  });
}


function dgi(id) {
  return document.getElementById(id);
}

function handleLeft() {
  document.querySelector("ons-navigator").popPage();
}

// function show_toast(message, timeout = VaultMateConfig.default.toast_timeout) {
//   ons.notification.toast(message, {timeout});
// }


let toastTimer = null;
let toastRequestId = 0;

function show_toast(message, timeout = VaultMateConfig.default.toast_timeout) {

  const requestId = ++toastRequestId;

  const toast = document.getElementById("app_toast");
  if (!toast) return;

  setTimeout(() => {

    if (requestId !== toastRequestId) return;

    // clear previous timer
    if (toastTimer) {
      clearTimeout(toastTimer);
    }

    // reset animation
    toast.classList.remove("show");
    void toast.offsetWidth;

    // update message
    toast.innerText = message;

    // show
    toast.classList.add("show");

    // hide
    toastTimer = setTimeout(() => {
      toast.classList.remove("show");
    }, timeout);

  }, 50); // 🔥 important (debounce window)
}

/*async function ensureMetaDB() {
  const createTableSql = `CREATE TABLE IF NOT EXISTS ${VaultMateConfig.tables.meta} (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      value TEXT,
      created INTEGER DEFAULT (strftime('%s','now')),
      updated INTEGER DEFAULT (strftime('%s','now'))
    )`;
  const createIndexSql = `CREATE UNIQUE INDEX IF NOT EXISTS idx_vault_meta_name ON vault_meta(name)`;
  await VaultDB.dbExecute(createTableSql, [], { name: VaultMateConfig.database.meta, encrypt: false });
  await VaultDB.dbExecute(createIndexSql, [], { name: VaultMateConfig.database.meta, encrypt: false });
}*/

/*async function loadVaultMeta() {
  try {
    await ensureMetaDB();
    const q = VaultDB.buildSelect({table: VaultMateConfig.tables.meta, columns: ["value"], where: {name: "salt64"}, limit: 1});
    const res = await VaultDB.dbExecute(q.sql, q.values, { name: VaultMateConfig.database.meta, encrypt: false});

    if(res.rows.length > 0) {
      return {initialized: true, salt64: res.rows.item(0).value};
    } else {
      return {initialized: false};
    }
  } catch (err) {
    return {initialized: false};
  }
}*/

/*async function saveSalt64(salt64) {
  try {
    await ensureMetaDB();
    var now = Date.now();
    const q = VaultDB.buildInsert(VaultMateConfig.tables.meta, {name:"salt64", value:salt64, created:now, updated:now}, {orReplace:true});
    await VaultDB.dbExecute(q.sql, q.values, {name: VaultMateConfig.database.meta, encrypt:false});
  } catch (err) {

  }
}*/

const BLACKLIST = new Set([
  "password","password1","password123","qwerty","qwerty123","asdfgh",
  "abc123","admin","admin123","letmein","welcome","iloveyou","monkey",
  "dragon","master","shadow","sunshine","princess","football","baseball",
  "trustno1","123456","654321","112233","121212","123123","000000","111111",
  "1234567","12345678","12345","123456789","0000","1111","2222","3333",
  "4444","5555","6666","7777","8888","9999","1234","4321","0123"
]);

const WEAK_BASES = [
  "password","pass","test","user","name","hello","welcome","login",
  "admin","root","guest","temp","demo","access","change","master",
  "dragon","monkey","shadow","sun","princess","football","baseball",
  "love","angel","ninja","batman","superman","hunter","ranger",
  "abc","xyz","qwerty","asdf","zxcv","iphone","google","amazon","apple"
];

const KEYBOARD_PATTERNS = [
  "qwerty","qwertz","azerty","asdfg","zxcvb","qwer","asdf","zxcv",
  "qazwsx","1qaz","2wsx","3edc","4rfv"
];

function normalizeLeet(s) {
  return s.toLowerCase()
    .replace(/@/g,"a").replace(/4/g,"a")
    .replace(/3/g,"e")
    .replace(/1/g,"i").replace(/!/g,"i")
    .replace(/0/g,"o")
    .replace(/5/g,"s").replace(/\$/g,"s")
    .replace(/7/g,"t")
    .replace(/8/g,"b");
}

function detectPatterns(pwd) {
  const lower = pwd.toLowerCase();
  const norm  = normalizeLeet(pwd);
  const found = [];
  const isAllDigits = /^\d+$/.test(pwd);

  if (/^(.)\1+$/.test(pwd))
    found.push("all_same_char");

  if (/0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|3210/.test(pwd))
    found.push("sequential_digits");

  if (/abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz|zyxw|yxwv|xwvu|wvut/.test(lower))
    found.push("sequential_letters");

  if (KEYBOARD_PATTERNS.some(p => lower.includes(p)))
    found.push("keyboard_pattern");

  // Only check dictionary base if not a pure numeric password
  // "209038209323" should NOT match "sun", "demo", etc. inside it
  if (!isAllDigits && WEAK_BASES.some(w => lower.includes(w) || norm.includes(w)))
    found.push("dictionary_base");

  if (/^[A-Z][a-z]+[^A-Za-z0-9]?\d+$/.test(pwd) || /^[A-Za-z]+[!@#$%^&*]+\d+$/.test(pwd))
    found.push("word_symbol_number");

  if (/\d{4}$/.test(pwd) && /^[A-Za-z]/.test(pwd))
    found.push("ends_with_year_or_number");

  // Only penalize year if user deliberately embedded it alongside letters/symbols
  if (!isAllDigits && /(19|20)\d{2}/.test(pwd))
    found.push("contains_year");

  const len = pwd.length;
  for (let i = 2; i <= len / 2; i++) {
    const chunk = pwd.slice(0, i);
    if (pwd.startsWith(chunk.repeat(Math.floor(len / i))) && i * 2 <= len) {
      found.push("repeated_pattern"); break;
    }
  }

  return found;
}

function get_score(password = "", mpin = "") {
  if (password) {
    if (BLACKLIST.has(password.toLowerCase())) return { score: 1, category: "Weak" };

    const hasLower   = /[a-z]/.test(password);
    const hasUpper   = /[A-Z]/.test(password);
    const hasDigit   = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);
    const len        = password.length;
    const variety    = [hasLower, hasUpper, hasDigit, hasSpecial].filter(Boolean).length;

    // Length is the primary strength driver (0–40)
    let lengthScore = 0;
    if (len >= 16)      lengthScore = 40;
    else if (len >= 12) lengthScore = 35;
    else if (len >= 10) lengthScore = 28;
    else if (len >= 8)  lengthScore = 20;
    else if (len >= 6)  lengthScore = 10;

    // Variety bonus (0–24) — reward mixing types
    const varietyScore = (variety - 1) * 8;

    // Entropy bonus (0–20) — rewards truly random passwords
    let charset = 0;
    if (hasLower)   charset += 26;
    if (hasUpper)   charset += 26;
    if (hasDigit)   charset += 10;
    if (hasSpecial) charset += 32;
    const entropy      = len * Math.log2(charset || 1);
    const entropyBonus = Math.min(20, (entropy / 80) * 20);

    let score      = lengthScore + varietyScore + entropyBonus;
    const patterns = detectPatterns(password);

    // Pattern penalties
    if (patterns.includes("all_same_char"))           score -= 40;
    if (patterns.includes("sequential_digits"))        score -= 20;
    if (patterns.includes("sequential_letters"))       score -= 20;
    if (patterns.includes("keyboard_pattern"))         score -= 20;
    if (patterns.includes("dictionary_base"))          score -= 25;
    if (patterns.includes("word_symbol_number"))       score -= 20;
    if (patterns.includes("ends_with_year_or_number")) score -= 10;
    if (patterns.includes("contains_year"))            score -= 10;
    if (patterns.includes("repeated_pattern"))         score -= 20;
    if (/^\d+$/.test(password)) {
      if (len < 8)       score -= 30;
      else if (len < 12) score -= 15;
      else               score -= 5;
    }

    const finalScore = Math.max(10, Math.min(100, Math.round(score)));
    return finalScore;
  }

  if (mpin) {
    if (BLACKLIST.has(mpin)) return { score: 1, category: "Weak" };

    const entropy = mpin.length * Math.log2(10);
    let mpinScore = (entropy / 20) * 100;

    if (/^(.)\1+$/.test(mpin)) mpinScore -= 20;
    if (/0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321|3210/.test(mpin)) mpinScore -= 20;

    const finalScore = Math.max(10, Math.min(100, Math.round(Math.max(0, mpinScore) * 0.5)));
    return finalScore;
  }

  return 10;
}


/*function get_score(password = "", mpin = "") {
  let totalScore = 0;

  if (password) {
    let charset = 0;

    if (/[a-z]/.test(password)) charset += 26;
    if (/[A-Z]/.test(password)) charset += 26;
    if (/[0-9]/.test(password)) charset += 10;
    if (/[^A-Za-z0-9]/.test(password)) charset += 32;

    if(charset > 0) {
      const entropy = password.length * Math.log2(charset);
      let pwdScore = (entropy / 120) * 100;

      if (/^(.)\1+$/.test(password)) pwdScore -= 30;
      if (/^\d+$/.test(password)) pwdScore -= 40;

      totalScore += Math.max(0, pwdScore);
    }
  }
    
  if(mpin) {
    const entropy = mpin.length * Math.log2(10);
    let mpinScore = (entropy / 20) * 100; // scale for PIN

    if (/^(.)\1+$/.test(mpin)) mpinScore -= 20;
    if (/0123|1234|2345|3456|4567|5678|6789|9876|8765|7654/.test(mpin))
      mpinScore -= 20;

    totalScore += Math.max(0, mpinScore * 0.5); 
  }

  return Math.max(1, Math.min(100, Math.round(totalScore)));
}*/

function get_password_status(score) {
  if(score >= 70) {
    return {label: "Strong", color: "green", classname: "strong_pwd"};
  }
  if(score >= 40) {
    return {label: "Medium", color: "orange", classname: "avarage_pwd"};
  }
  if(score >= 25) {
    return {label: "Weak", color: "red", classname: "week_pwd"};
  }
  return {label: "Very Weak", color: "red", classname: "week_pwd"};
}

// function get_password_status(score) {
//   if(score >= 70) {
//     return {label:"Strong", color:"green", classname:"strong_pwd"};
//   }
//   if(score >= 40) {
//     return {label:"Medium", color:"orange", classname:"avarage_pwd"};
//   }  
//   return {label: "Very Weak", color: "red", classname:"week_pwd"};
// }

function toggle_password_icon(id, el) {
  const input = dgi(id);
  if(input.type === "password") {
    input.type = "text";
    el.classList.remove("fa-eye");
    el.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    el.classList.remove("fa-eye-slash");
    el.classList.add("fa-eye");
  }
}

function toggleMPIN(el, mode = 1) {
    let section_class = ".mpin-section";
    let input_class = ".mpin-input";
    if(mode == 2) {
      section_class = ".setting_page_form_group";
      input_class = ".setting_page_pin_input";
    }
    const section = el.closest(section_class);
    const inputs = section.querySelectorAll(input_class);
    const icon = el.querySelector("i");
    const isHidden = inputs[0].style.webkitTextSecurity !== "none";
    inputs.forEach(input => {
        input.style.webkitTextSecurity = isHidden ? "none" : "disc";
    });

    if (isHidden) {
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
        el.classList.add("active");
    } else {
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
        el.classList.remove("active");
    }
}

let page_loader_timer = null;
let page_loader_shown = false;

function is_page_loading(show){
  const loader = dgi("pageloader");
  if(show) {
    clearTimeout(page_loader_timer);
    page_loader_shown = false;
    page_loader_timer = setTimeout(() => {
      page_loader_shown = true;
      loader.style.display = "flex";
    }, 200);
  } else {
    clearTimeout(page_loader_timer); 
    page_loader_timer = null;
    if(page_loader_shown){
      loader.style.display = "none";
    }
    page_loader_shown = false;
  }
}

function isUrl(value) {
  return typeof value === "string" && /^(https?:\/\/|www\.)/i.test(value);
}

function normalizeUrl(url) {
  if (!/^https?:\/\//i.test(url)) {
    return "https://" + url;
  }
  return url;
}

/*function open_external_url(url) {
  if (!url) return;
  const finalUrl = normalizeUrl(url);
  if(window.cordova && cordova.InAppBrowser) {
    cordova.InAppBrowser.open(finalUrl, "_blank", "location=yes,hideurlbar=yes,hardwareback=yes,clearcache=yes,clearsessioncache=yes");
  } else {
    window.open(finalUrl, "_blank");
  }
}*/

function open_external_url(url) {
    if (!url) return;
    url = normalizeUrl(url);
    if (window.cordova && cordova.InAppBrowser) {
        cordova.InAppBrowser.open(url, "_system");
    } else {
        window.open(url, "_blank");
    }
}

function escape_html(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function clear_clipboard_after(ms = 30000) {
  if(clipboardTimer) {
    clearTimeout(clipboardTimer);
  }
  clipboardTimer = setTimeout(() => {
    if(window.SilentClipboard) {
      window.SilentClipboard.clear(
        () => console.log("Clipboard cleared silently"),
        (err) => console.error("Error clearing clipboard", err)
      );
    }
  }, ms);
}

// function clear_clipboard_after(ms = 30000) {
//   if(clipboardTimer) {
//     clearTimeout(clipboardTimer); 
//   }

//   clipboardTimer = setTimeout(() => {
//     if(!cordova?.plugins?.clipboard) {
//       return;
//     }

//     cordova.plugins.clipboard.paste((currentText) => {
//       if (currentText === lastCopiedText) {
//         cordova.plugins.clipboard.copy("");
//         //console.log("Clipboard cleared");
//       } else {
//         //console.log("User copied something else → skip clear");
//       }
//     });
//   }, ms);
// }

function vm_log(...args) {
  if(DEBUG) {
    console.log(...args);
  }
}

function render_file_html(key, accept = "image/*,application/pdf,text/plain,text/csv,application/csv,.csv,application/json,.bin,.doc,.docx,.xls,.xlsx", desc = "Images, PDFs, text or document files", title = 'Upload documents') {
  return `<div class="vm-upload-card" onclick="open_file_picker('${key}_file')">
      <i class="fas fa-paperclip vm-upload-icon"></i>
      <div class="vm-upload-text">
        <div class="vm-upload-title">${title}</div>
        <div class="vm-upload-sub">${desc}</div>
      </div>
    </div>
    <div id="${key}_file_count" class="vm-upload-count"></div>
    <div id="${key}_file_preview" class="vm-file-preview"></div>
    <input type="file" id="${key}_file" capture="environment" multiple ${accept ? `accept="${accept}"` : ""} hidden>`;
}

function renderPasswordField(label, id, placeholder) {
  return `
    <div class="vm-row input-wrapper">
      <label class="vm_remainder_form_label">${label}</label>

      <div class="vm-password-wrap">
        <input 
          type="password" 
          id="${id}" 
          placeholder="${placeholder}" 
          autocomplete="new-password"
          class="vm-password-input"
        >

        <div class="vm-password-actions">
          <i 
            class="fa fa-eye toggle-eye" 
            onclick="toggle_password_icon('${id}', this)"
            title="Show / Hide Password"
          ></i>

          <i 
            class="fa fa-key vm-generate-icon" 
            onclick="vm_generate_password('${id}')"
            title="Generate strong password"
          ></i>
        </div>
      </div>
      <div class="vm-password-hint">
        Tap <i class="fa fa-key"></i> to generate secure password
      </div>
    </div>
  `;
}

function vm_generate_password(fieldId) {

  const length = 20;

  const upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  const lower = "abcdefghijklmnopqrstuvwxyz";
  const nums  = "0123456789";
  const sym   = "!@#$%^&*()_+";

  const all = upper + lower + nums + sym;

  let pwd = "";

  // ensure strong
  pwd += upper[Math.floor(Math.random() * upper.length)];
  pwd += lower[Math.floor(Math.random() * lower.length)];
  pwd += nums[Math.floor(Math.random() * nums.length)];
  pwd += sym[Math.floor(Math.random() * sym.length)];

  for (let i = pwd.length; i < length; i++) {
    pwd += all[Math.floor(Math.random() * all.length)];
  }

  // shuffle
  pwd = pwd.split('').sort(() => Math.random() - 0.5).join('');

  const input = dgi(fieldId);
  if (!input) return;

  input.value = pwd;
  input.dispatchEvent(new Event("input"));

  //show_toast("Strong password generated");
}

function setBtnLoading(btn, isLoading, loadingText = "Loading...") {
  if(!btn) {
    return;
  }
  const textEl = btn.querySelector(".btn_text");
  if(!btn.dataset.originalText && textEl) {
      btn.dataset.originalText = textEl.innerHTML;
  }
  if(isLoading) {
      btn.disabled = true;
      if(textEl) {
        textEl.innerHTML = `${loadingText}`;
      }
  } else {
    btn.disabled = false;
    if(textEl) {
      textEl.innerHTML = btn.dataset.originalText || "Submit";
    }
  }
}


function set_button_loading(btn, isLoading = true) {
  if(!btn || !btn.hasAttribute("data-loader")) {
    return;
  }
  
  const actions = btn.closest(".vm-actions");
  const cancelBtn = actions?.querySelector(".cancel_btn");

  if(isLoading) {
    btn.classList.add("btn-loading");
    btn.disabled = true;
    if (cancelBtn) {
      cancelBtn.disabled = true;
    }
    vault_sheet_locked = true;
  } else {
    btn.classList.remove("btn-loading");
    btn.disabled = false;
    if(cancelBtn) {
      cancelBtn.disabled = false;
    }
    vault_sheet_locked = false;
  }
}

function set_active_category(slug) {
  const tabs = document.querySelectorAll("#vm_catsHorizontal .vm-cat-tab");
  for(const tab of tabs) {
    if(tab.dataset.slug === slug) {
      tab.click();      
      break;
    }
  }
}

function empty_html(icon, title, desc) {
  return `<div class="vm-empty-wrap">
      <div class="vm-empty-card">
          <i class="fa ${icon} vm-empty-icon"></i>
          <div class="vm-empty-title">${title}</div>
          <div class="vm-empty-desc">${desc}</div>
      </div>
  </div>`;
}

function get_daterange(prefix) {
  const now = Math.floor(Date.now() / 1000);

  if(filter_date_range === "7") {
    return {from: Date.now() - 7 * 86400 * 1000};
  }

  if(filter_date_range === "14") {
    return {from: Date.now() - 14 * 86400 * 1000};
  }

  if(filter_date_range === "30") {
    return {from: Date.now() - 30 * 86400 * 1000};
  }

  if(filter_date_range === "90") {
    return {from: Date.now() - 90 * 86400 * 1000};
  }

  if(filter_date_range === "custom") {
    const fromVal = dgi(prefix+"_date_from").value;
    const toVal = dgi(prefix+"_date_to").value;

    if(!fromVal && !toVal) {
      return null;
    }

    const range = {};
    if(fromVal) {
      range.from = new Date(fromVal + "T00:00:00").getTime();
    }

    if(toVal) {
      range.to   = new Date(toVal + "T23:59:59").getTime();
    }
    return range;
  }
  return null; 
}

function hide_action_sheet() {
  dgi("vaultActionSheet").hide();
  activeVaultId = null;
}

function format_ts(ts) {
  if (!ts) return "";
  return new Date(ts).toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false
  });
}

function format_last_used(ts) {
  if(!ts) return "never";
  return format_ts(ts);
}

function get_vault_icon(slug) {
  return vault_category_map[slug]?.icon || "fa-lock";
}

function get_vault_name(slug) {
  return vault_category_map[slug]?.name || "";
}

function open_upgrade() {
  close_bottom_sheet();
  const vaults = parseInt(dgi("vm_total_vaults").textContent) || 0;
  const reminders = parseInt(dgi("vm_total_reminders").textContent) || 0;
  window.UPGRADE_CONTEXT = {
    vaults_used: vaults,
    reminders_used: reminders,
  };
  open_overlay('upgrade.html');
}

function import_csv(context = {}) {
  open_overlay('csv.html', context);
}

function redirect_to(tabId, actionValue = null) {
  document.querySelector("ons-tabbar").setActiveTab(tabId);
  dashboard_action = actionValue;
}

function open_overlay(pageUrl, data = {}) {
    const overlayNav = document.getElementById("overlayNavigator");

    overlayNav.classList.remove("hide");
    overlayNav.pushPage(pageUrl, {
        data,
        animation: "slide",
        animationOptions: {
            duration: 0.2,
        }
    });
}

function close_overlay() {
    const overlayNav = document.getElementById("overlayNavigator");

    if(!overlayNav) {
      return;
    }

    if (overlayNav.pages.length > 1) {
        overlayNav.popPage({
            animation: "slide",
            animationOptions: {
                duration: 0.2, 
            }
        }).then(() => {
            if (overlayNav.pages.length === 1) {
                overlayNav.classList.add("hide");
            }
        });
    } else {
        overlayNav.classList.add("hide");
    }
}

function maskKey(key) {
    if (!key || key.length < 4) {
      return "****";
    }
    const start = key.slice(0, 2);
    const end = key.slice(-2);
    const masked = "*".repeat(Math.max(4, key.length - 4));
    return `${start}${masked}${end}`;
}

let reminderChannelReady = false;
async function ensureReminderChannel() {
  if(reminderChannelReady) {
    return true;
  }
  await createReminderChannel();
  reminderChannelReady = true;
  return true;
}

async function createReminderChannel() {
  await new Promise(resolve => {
    cordova.plugins.notification.local.createChannel({
      androidChannelId: reminder_channel_id,
      androidChannelName: "Reminders",
      androidChannelDescription: "Vault reminder notifications",
      androidChannelImportance: "IMPORTANCE_HIGH",
      androidChannelEnableLights: true,
      androidChannelEnableVibration: true,
      sound: "default"
    }, resolve);
  });
}

/*async function createReminderChannel() {
  cordova.plugins.notification.local.createChannel({
    androidChannelId: reminder_channel_id,
    androidChannelName: "Reminders",
    androidChannelDescription: "Vault reminder notifications",
    androidChannelImportance: "IMPORTANCE_HIGH",
    androidChannelEnableLights: true,
    androidChannelEnableVibration: true,
    sound: "default"
  });
  
  cordova.plugins.notification.local.hasPermission(function(granted) {
    if(granted) {
        return;
    }
    cordova.plugins.notification.local.requestPermission(function(grantedNow) {
      if(grantedNow) {
          return;
      }
      //ons.notification.alert({title: "Notifications Disabled", message: "Notifications are disabled. You won't receive reminder alerts until they are enabled from Android Settings."});
    });
  });
}*/
