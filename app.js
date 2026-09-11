var secureStorage, sessionActive = false;
let lastCatchupRun = 0;
var loggedInPages = ["indexPage", "dashboardPage", "settingsPage", "home"];

document.addEventListener("deviceready", function () {  
  _MODE_ = "production";
  change_status_bar();  
  secureStorage = new SecureStorage(async () => {
    try {
      await new Promise((resolve, reject) => {
        secureStorage.set(
          () => { secureStorage.remove(() => resolve(), () => resolve(), "__init_test"); },
          err => reject(err),
          "__init_test", "1"
        );
      });
      runVaultMateFlow();
    } catch (e) {
      console.warn("SecureStorage init test failed, attempting auto-recovery...", e);
      await handleSecureStorageRecovery();
      return;
    }
  }, (err) => {    
    window.location.replace("device-security.html");
  }, VaultMateConfig.storageKeys.namespace);
});

async function handleSecureStorageRecovery() {
  try {
    await reinitSecureStorage();

    await new Promise((resolve, reject) => {
      secureStorage.set(
        () => { secureStorage.remove(() => resolve(), () => resolve(), "__init_test"); },
        err => reject(err),
        "__init_test", "1"
      );
    });
    //console.log("SecureStorage recovered successfully, redirecting to intro...");
    window.location.replace("intro.html");
  } catch (finalErr) {
    console.error("SecureStorage recovery failed completely:", finalErr);
    window.location.replace("storage-issue.html");
  }
}

async function runVaultMateFlow() {
  const currentPage = document.body.id;

  if(currentPage === "blankPage") {
    await checkBlankPage();
  } else if(currentPage === "introPage") {
    await checkIntroStatus();
  } else if(currentPage === "loginPage") {
    await checkMPINStatus();
  } else if(loggedInPages.includes(currentPage)) {
    await validateSession(currentPage);

    setTimeout(async () => {
      check_app_updates();
    }, 1200);

    const pendingReminderId = localStorage.getItem("pendingReminderId");
    if (pendingReminderId) {
        localStorage.removeItem("pendingReminderId");
        setTimeout(async () => {
            redirect_to(2);
            await open_reminder_detail(pendingReminderId);
        }, 800);
    }   
    
    setTimeout(async () => {
      const isPremium = await isPremiumUser();
      if(!isPremium && typeof initBilling === "function") {
        initBilling();
      }
    }, 1000);
    
    //await catchup_reminders();  
    setTimeout(() => catchup_reminders().catch(console.error), 0);

    //const shouldRunBackup = sessionActive && loggedInPages.includes(document.body.id);
    /*setTimeout(() => {
        check_auto_backup(true).catch(err => {
            console.error("Auto backup failed:", err);
        });
    }, 500);*/
  } 

  document.addEventListener("pause", () => {
    sessionActive = false;
    teardownActivityTracking();
    clear_identity_verification();

  }, false);

  document.addEventListener("resume", async () => {
    const page = document.body.id;
    if(!loggedInPages.includes(page)) {
        return;
    }
    await validateSession(page);
    const now = Date.now();
    if(now - lastCatchupRun > 120000) {
        setTimeout(() => catchup_reminders().catch(console.error), 0);
        lastCatchupRun = now;
    }
  }, false);

  document.addEventListener("visibilitychange", () => {
    if(document.hidden) {
      sessionActive = false;
      teardownActivityTracking();
    }
  });

  let back_pressed_once = false;
  document.addEventListener("backbutton", function(e) {
    e.preventDefault(); e.stopImmediatePropagation();
    if(vault_sheet_locked) {
      show_toast("Please wait, saving...");
      return;
    }

    if(isImporting) {
      e.preventDefault();
      ons.notification.confirm({
        message: "Import is in progress. Leaving will stop it.",
        buttonLabels: ["Stay", "Cancel Import"]
      }).then(index => {
        if(index === 1) {
          cancelImportAndExit(); 
        }
      });
      return;
    }

    const copySelectPanel = dgi("vm_copy_select_panel");
    if(copySelectPanel && !copySelectPanel.classList.contains("hide")) {
      cancel_copy_selected();
      return;
    }
    is_page_loading(false);

    const actionSheet = dgi("vaultActionSheet");
    if(actionSheet && actionSheet.visible) {
      hide_action_sheet();
      return;
    }

    const alertDialog = document.querySelector("ons-alert-dialog[visible]");
    if(alertDialog) {
      alertDialog.hide();
      return;
    }

    if(active_bottom_sheet) {
      close_bottom_sheet();
      return;
    }    
        
    const settingsNavigator = document.querySelector("#settingsNavigator"); 
    if(settingsNavigator && settingsNavigator.pages.length > 1) {
      settingsNavigator.popPage();
      return;
    } 

    const tabbar = document.querySelector("ons-tabbar");
    if(tabbar) {
      const activeIndex = tabbar.getActiveTabIndex?.();
      if(typeof activeIndex === "number" && activeIndex !== 0) {
        if(activeIndex === 4 && LAST_TAB_INDEX != null) {
          tabbar.setActiveTab(LAST_TAB_INDEX);
          LAST_TAB_INDEX = null;
          return;
        }
        tabbar.setActiveTab(0);
        return;
      }
    }

    const overlayNav = document.getElementById("overlayNavigator");
    if(overlayNav && overlayNav.pages.length > 1) {
      close_overlay();
      return;
    }

    if(!back_pressed_once) {
      back_pressed_once = true;
      show_toast("Press back again to exit");
      setTimeout(() => (back_pressed_once = false), 2000);
    } else {
      navigator.app.exitApp();
    }
  }, false); 

  if(_MODE_ != "dev") {
    cordova.plugins.notification.local.on("trigger", async function (notification) {
      //const rm_id = notification.id;
      const rm_id = notification.data?.rm_id || notification.id;

      const now = Date.now();
      let rtable = VaultMateConfig.tables.reminders;
      const reminder = await get_rm_by_id(rm_id);
      if(!reminder) {
        return;
      }

      if(reminder.reminder_type === "onetime") {
          if(notification.data?.snooze) {
            return;
          }
          const {sql, values} = VaultDB.buildUpdate(rtable, {status: 0, updated_at: now}, {id: rm_id});
          await VaultDB.dbExecute(sql, values);
      } else if(reminder.reminder_type === "repeat") {
          if(reminder.repeat_end_date && now > Number(reminder.repeat_end_date)) {
            await cancel_rm_notification(rm_id);
            const {sql, values} = VaultDB.buildUpdate(rtable, {status: 0, updated_at: now}, {id: rm_id});
            await VaultDB.dbExecute(sql, values);
          }
      }
      refresh_dashboard_action("reminder", true, true);
    });

    cordova.plugins.notification.local.on("action", async function (notification, actionId) {
      let delay = 0;
      if(actionId === "dismiss") return;
      if(actionId === "snooze_10") delay = 10 * 60 * 1000;
      if(actionId === "snooze_30") delay = 30 * 60 * 1000;
      if(actionId === "snooze_60") delay = 60 * 60 * 1000;
      if(!delay) return;

      const rm_id = notification.data?.rm_id || notification.id;
      const nextTs = Date.now() + delay;

      const {sql, values} = VaultDB.buildUpdate(VaultMateConfig.tables.reminders, {next_trigger_at:nextTs, updated_at:Date.now()}, {id:rm_id});
      await VaultDB.dbExecute(sql, values);

      cordova.plugins.notification.local.schedule({
          id: notification.id + id_snooze,
          title: notification.title + " (Snoozed)",
          text: notification.text,
          trigger: {at: new Date(nextTs)},
          androidAllowWhileIdle: true,
          androidSmallIcon: "res://ic_notification",
          androidChannelId: reminder_channel_id,
          data: {rm_id: rm_id, snooze: true}
      });
      refresh_dashboard_action("reminder");
    });

    cordova.plugins.notification.local.on("click", async function (notification) {
      const rm_id = notification.data?.rm_id || notification.id;
      localStorage.setItem("pendingReminderId", rm_id);

      setTimeout(async () => {
        const reminder = await get_rm_by_id(rm_id);
        if(!reminder) {
          return;
        }
        redirect_to(2); 
        setTimeout(async () => {
          await open_reminder_detail(rm_id);
        }, 500);
      }, 800);
    });
  }
}

document.addEventListener('focusin', function (e) {
  if(!e.target.matches('[data-date="start"]')) return;

  const start = e.target;
  const rule  = start.getAttribute('data-start-rule');
  const today = new Date().toISOString().split('T')[0];

  if(rule === 'today') {
      start.min = today;
  } else if(rule === 'future') {
      const t = new Date();
      t.setDate(t.getDate() + 1);
      start.min = t.toISOString().split('T')[0];
  }
});

document.addEventListener("change", function (e) {
    const startInput = e.target.closest('[data-date="start"]');
    if(!startInput) {
      return;
    }

    const group = startInput.closest('[data-date-group]') || document;
    const endInput = group.querySelector('[data-date="end"]');
    const rule = startInput.getAttribute('data-start-rule');
    const today = new Date().toISOString().split('T')[0];

    if(rule === 'today') {
        startInput.min = today;
    } else if(rule === 'future') {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        startInput.min = tomorrow.toISOString().split('T')[0];
    }

    if(!startInput.value) {
      return;
    }

    if(endInput) {
        endInput.min = startInput.value;
        if(endInput.value && endInput.value <= startInput.value) {
            endInput.value = '';
        }
    }
    if(startInput.value < startInput.min) {
        startInput.value = '';
    }
});

async function checkBlankPage() {
  try {
    const introFlag = await secure_storage(VaultMateConfig.storageKeys.intro);
    if(introFlag === "done") {
      window.location.replace("login.html");
    } else {
      window.location.replace("intro.html");
    }
  } catch (err) {
    const errMsg = String(err?.message || err).toLowerCase();
    if(errMsg.includes("doesn't contain alias") || errMsg.includes("keystore")) {
      try {
        await reinitSecureStorage();
      } catch (e) {}
      window.location.replace("intro.html");
    } else {
      console.error("checkBlankPage error:", err);
      window.location.replace("intro.html");
    }
  }
}

async function checkIntroStatus() {
  try {
    const value = await secure_storage(VaultMateConfig.storageKeys.intro);
    if(value === "done") {
      window.location.replace("login.html");
    }
  } catch (err) {
    console.error("checkIntroStatus error:", err);
  }
}

async function reinitSecureStorage() {
  return new Promise((resolve, reject) => {
    if(secureStorage && typeof secureStorage.destroy === "function") {
      const onCreate = () => resolve(true);
      const onError = (err) => reject(err);
      secureStorage.destroy(() => {
        secureStorage = new SecureStorage(onCreate, onError, VaultMateConfig.storageKeys.namespace);
      }, () => {
        secureStorage = new SecureStorage(onCreate, onError, VaultMateConfig.storageKeys.namespace);
      });
    } else {
      reject(new Error("SecureStorage destroy not supported"));
    }
  });
}

async function finishIntro() {
  try {
    await secure_storage(VaultMateConfig.storageKeys.intro, "done");
    await initializeDatabase();
    window.location.replace("login.html");
  } catch (err) {
    const errMsg = String(err?.message || err).toLowerCase();
    if(errMsg.includes("lock")) {
      show_toast("Please enable screen lock and try again!");
    } else if(errMsg.includes("doesn't contain alias") || errMsg.includes("does not contain alias") || errMsg.includes("keystore")) {
      try {
        show_toast("Reinitializing secure storage...");
        await reinitSecureStorage();
        await secure_storage(VaultMateConfig.storageKeys.intro, "done");
        await initializeDatabase();
        window.location.replace("login.html");
      } catch (reinitErr) {
        window.location.replace("storage-issue.html");
      }
    } else {
      show_toast("Database initialization failed "+err.message);
      vm_log(err);
      //alert("Error: " + err.message + "\n\nStack Trace:\n" + err.stack);
    }
  }
}

async function initializeDatabase() {
  try {
    const conn = await VaultDB.openDB();
    return new Promise((resolve, reject) => {
      conn.transaction(tx => {
          window.VaultMateSchema.forEach(sql => {
            if(sql && sql.length > 0) {
              tx.executeSql(sql);
            }
          });
        },
        err => reject(err),
        () => resolve(true)
      );
    });
  } catch (err) {
    throw err;
  }
}

async function checkMPINStatus() {
  const quickFlag = localStorage.getItem(VaultMateConfig.storageKeys.mpin_exists);
  console.log("quickFlag", quickFlag);
  if(quickFlag === "1") {
    showLoginView();
  } else {
    showCreateView();
  }

  try {
    const cipherKey = await secure_storage(VaultMateConfig.storageKeys.enc_key_name);
    const actuallyExists = !!cipherKey;

    if(actuallyExists && quickFlag !== "1") {
      localStorage.setItem(VaultMateConfig.storageKeys.mpin_exists, "1");
      showLoginView();
    } else if(!actuallyExists && quickFlag === "1") {
      localStorage.removeItem(VaultMateConfig.storageKeys.mpin_exists);
      showCreateView();
    }
  } catch (err) {
    if(quickFlag !== "1") {
      showCreateView();
    }
  }
}

function showCreateView() {
  hideAllAuthViews();
  document.getElementById("mpinSetup")?.classList.remove("hidden");
  clear_login_inputs();
}

async function showLoginView() {
  hideAllAuthViews();
  dgi("mpinLogin")?.classList.remove("hidden");
  dgi("finger_login_btn")?.classList.remove("hidden");
  clear_login_inputs();

  const fpBtn = dgi("finger_login_btn");
  const fpText = fpBtn.querySelector(".fp-text");

  const cachedLockUntil = parseInt(localStorage.getItem(VaultMateConfig.storageKeys.lockoutTime), 10);
  if(cachedLockUntil && Date.now() < cachedLockUntil) {
    startLockoutCountdown(cachedLockUntil, false);
  }

  const lockUntil = parseInt(await secure_storage(VaultMateConfig.storageKeys.lockoutTime), 10);
  if(lockUntil && Date.now() < lockUntil) {
    if(lockUntil !== cachedLockUntil) {
      startLockoutCountdown(lockUntil, false);
    }
    return;
  } else if(cachedLockUntil) {
    localStorage.removeItem(VaultMateConfig.storageKeys.lockoutTime);
    await startLockoutCountdown(0, false);
  }

  const finger_enabled = await secure_storage(VaultMateConfig.storageKeys.fingerprint);
  if(finger_enabled == "1") {
    fpText.textContent = "Use Fingerprint";
    fingerprintLogin();
  } else {
    fpText.textContent = "Enable Fingerprint";
  }
}

function showRecoveryChoice() {
  hideAllAuthViews();
  document.getElementById("mpinLogin")?.classList.remove("hidden");
  document.getElementById("login_recovery")?.classList.remove("hidden");
  clear_login_inputs();
}

function hideAllAuthViews() {
  ["mpinLogin", "mpinSetup", "login_recovery", "finger_login_btn"].forEach(id => {
    dgi(id)?.classList.add("hidden");
  });
}

function clear_login_inputs() {
  document.querySelectorAll(".mpin-input").forEach(input => {
    input.value = "";
  });
  const loader = document.getElementById("loadingScreen");
  if(loader) {
    loader.style.display = "none";
  }
}

let identity_verified_at = null;
function mark_identity_verified() {
  identity_verified_at = Date.now();
}

function is_identity_verified() {
  if(!identity_verified_at) {
    return false;
  }
  return (Date.now() - identity_verified_at < 120000); // 2 minutes
}
function clear_identity_verification() {
  identity_verified_at = null;
}

async function reset_mpin(e) {
  const btn = e?.currentTarget;
  set_button_loading(btn, true);

  try {
    let newMPIN = "", confirmMPIN = "", oldMPIN = "";
    let identity_verified = false;

    if(is_identity_verified()) {
      identity_verified = true;
    } else {
      const oldInputs = document.querySelectorAll(".old_pin_section .setting_page_pin_input");
      oldInputs.forEach(i => oldMPIN += i.value);

      if(oldMPIN.length !== 6) {
        show_toast("Please enter a 6-digit current MPIN");
        return;
      }
      const old_pin_status = await validateMPIN(oldMPIN, null, true);
      if(!old_pin_status) {
        show_toast("Invalid current MPIN");
        return;
      }
      identity_verified = true;
    }

    if(!identity_verified) {
      show_toast("Verification required");
      return;
    }   

    const newInputs = document.querySelectorAll(".new_pin_section_1 .setting_page_pin_input");
    const confirmInputs = document.querySelectorAll(".new_pin_section_2 .setting_page_pin_input");

    newInputs.forEach(i => newMPIN += i.value);
    confirmInputs.forEach(i => confirmMPIN += i.value);

    if(newMPIN.length !== 6 || confirmMPIN.length !== 6) {
      show_toast("Please enter a 6-digit MPIN");
      return;
    }

    if(newMPIN !== confirmMPIN) {
      show_toast("MPINs do not match");
      return;
    }

    if(newMPIN === oldMPIN) {
      show_toast("New MPIN cannot be same as current MPIN");
      return;
    }
    var obj = await saveMPIN(null, newMPIN);
    if(obj || 1) {
      show_toast("MPIN changed successfully");
      document.querySelectorAll(".setting_page_pin_input").forEach(input => {
        input.value = "";
      });      
      setTimeout(() => {logout();}, 800);
    } else {
      show_toast("Something went wrong. Try again.");
    }
  } catch (error) {
    show_toast("Something went wrong. Try again.");
  } finally {
    set_button_loading(btn, false);
  }
}

async function saveMPIN(th, newmpin = "") {

  //console.log("save mpin", th);

  let newMPIN = "", confirmMPIN = "";
  let return_status = false;
  if(newmpin == "") {
    
    //console.log("before loader");

    show_button_loader(th);
    await new Promise(r => setTimeout(r, 50));

    //console.log("after loader");

    const newInputs = document.querySelectorAll('#mpinSetup .new_mpin_section .mpin-input');
    const confirmInputs = document.querySelectorAll('#mpinSetup .confirm_mpin_section .mpin-input');

    newInputs.forEach(i => newMPIN += i.value);
    confirmInputs.forEach(i => confirmMPIN += i.value);

    //console.log("comes here", th);

    if(newMPIN.length !== 6 || confirmMPIN.length !== 6) {
      hide_button_loader(th);
      show_toast("Please enter a 6-digit MPIN");
      return;
    } else if(newMPIN !== confirmMPIN) {
      hide_button_loader(th);
      show_toast("MPINs do not match. Please try again.");
      return;
    }

    //console.log("newMPIN", newMPIN, "confirmMPIN", confirmMPIN);

  } else {
    newMPIN = newmpin;
    return_status = true;
  }
  
  const now = Date.now();
  const hash = generate_hash(newMPIN); 
  const salt = generate_salt(); 
  const salt_64 = generate_salt_base64(salt);
  const cipher_key = generate_cipher_key(newMPIN, salt);

  try {
    var table = VaultMateConfig.tables.settings;
    let ins = VaultDB.buildInsert(table, {name:"mpin_hash", value:hash, created_at:now, updated_at:now}, {orReplace:true});
    await VaultDB.dbExecute(ins.sql, ins.values);

    ins = VaultDB.buildInsert(table, {name:"mpin_salt", value:salt_64, created_at:now, updated_at:now}, {orReplace:true});
    await VaultDB.dbExecute(ins.sql, ins.values);   

  } catch (e) {     

    if(return_status) {
      return false;
    }

    if(_MODE_ != "dev") {
      hide_button_loader(th);
      show_toast("Failed to save MPIN settings");
      return;
    }    
  }

  try {    
    await VaultDB.rekey(cipher_key);
    await secure_storage(VaultMateConfig.storageKeys.enc_key_name, cipher_key);
    localStorage.setItem(VaultMateConfig.storageKeys.mpin_exists, "1");
    await get_app_key();

    const autoBackupInterval = await secure_storage(VaultMateConfig.storageKeys.backup_interval);
    if (autoBackupInterval) {
        const backupKey = await secure_storage(VaultMateConfig.storageKeys.backup_key);
        const appEncKey = await secure_storage(VaultMateConfig.storageKeys.app_enc_key);
        if (backupKey) {
            MahaBackgroundBackup.cacheKeysForBackground(backupKey, cipher_key, appEncKey, () => {}, console.error);
        }
    }

    //await saveSalt64(salt_64);
  } catch (err) {

    console.log("err", err);

    if(return_status) {
      return false;
    }

    if(_MODE_ != "dev") {
      hide_button_loader(th);
      show_toast("Encryption failed. Try again.");
      return;
    } else {
      await secure_storage(VaultMateConfig.storageKeys.enc_key_name, cipher_key);
    }
  }

  if(return_status) {
    return true;
  }

  hide_button_loader(th);
  show_toast("MPIN set successfully. Please log in.");
  showLoginView();
} 

let isCheckingMPIN = false;
async function checkMPIN(th) {
  if(isCheckingMPIN) {
    return;
  }
  isCheckingMPIN = true;

  const mpinInputs = document.querySelectorAll("#mpinLogin .mpin-input");
  let entered = "";
  mpinInputs.forEach(input => entered += input.value);

  if(entered.length !== 6) {
    isCheckingMPIN = false;
    show_toast("Please enter a 6-digit MPIN");
    return;
  }
  show_button_loader(th);
  
  try {
    const lockTime = await secure_storage(VaultMateConfig.storageKeys.lockoutTime);
    const now = Date.now();
    const lockUntil = parseInt(lockTime, 10);

    console.log("lockUntil", lockUntil);
    console.log("Date.now()", Date.now());
    console.log("Locked?", Date.now() < lockUntil);

    if(!lockTime || isNaN(lockUntil)) {
      await validateMPIN(entered, th);
      return;
    }
    if(now < lockUntil) {
      hide_button_loader(th);
      show_toast("Too many attempts");
      startLockoutCountdown(lockUntil);
      return;
    }
    await validateMPIN(entered, th);
  } catch (err) {
    await validateMPIN(entered, th); 
  } finally {
    isCheckingMPIN = false;
  }
}

/*async function attemptRecovery(mpin) {
  const existingKey = await secure_storage(VaultMateConfig.storageKeys.enc_key_name);
  if(existingKey) {
    return 1; 
  }

  const vaultData = await loadVaultMeta();
  if(!vaultData || !vaultData.salt64) {
    showCreateView();
    return 2;
  }

  const salt = CryptoJS.enc.Base64.parse(vaultData.salt64);    
  const derived_key = generate_cipher_key(mpin, salt);

  try {
    var unlockedDB = await VaultDB.openDB(derived_key); 
    if(unlockedDB) {      
      return 4;
    } else {
      show_toast('Invalid MPIN');
      return 3;
    }
  } catch (err) {
    show_toast('Invalid MPIN');
    return 3;    
  }
}*/

async function validateMPIN(enteredMPIN, th, return_status = false) {
  /*const recovered = await attemptRecovery(enteredMPIN);
  if(recovered === 1 || recovered == 4) {
  } else if(recovered === 2) {    
    hide_button_loader(th);
    return; 
  } else if(recovered === 3) {
    hide_button_loader(th);
    incrementRetryCount();
    return;
  }*/ 

  try {    
    var q = VaultDB.buildSelect({table: VaultMateConfig.tables.settings, columns: ["value"], where: {name:"mpin_hash"}});
    var obj = await VaultDB.dbExecute(q.sql, q.values);
    if(obj.rows.length === 0) {
      if(return_status) {
        return false;
      }
      show_toast("No MPIN found. Please create MPIN.");
      hide_button_loader(th);
      showCreateView();
      return;
    }

    const savedHash = obj.rows.item(0).value;
    const enteredHash = generate_hash(enteredMPIN);
    if(enteredHash !== savedHash) {
      if(return_status) {
        return false;
      }

      hide_button_loader(th);
      incrementRetryCount();
      //show_toast("Incorrect MPIN. Try again.");
      return;
    }

    const s = VaultDB.buildSelect({table: VaultMateConfig.tables.settings, columns: ["value"], where: {name:"mpin_salt"}});
    const resSalt = await VaultDB.dbExecute(s.sql, s.values);

    if(resSalt.rows.length === 0) {
      if(return_status) {
        return false;
      }
      hide_button_loader(th);
      show_toast("Security salt missing. Cannot proceed.");
      showCreateView();
      return;
    }

    const salt64 = resSalt.rows.item(0).value;
    const salt = CryptoJS.enc.Base64.parse(salt64);    
    const derived_key = generate_cipher_key(enteredMPIN, salt);

    var unlockedDB = await VaultDB.openDB(derived_key); 
    await new Promise((resolve, reject) => {
      unlockedDB.executeSql("select count(*) as c from " + VaultMateConfig.tables.settings, [], () => resolve(), (err) => reject(err));
    });

    if(return_status) {
      return true;
    }
  
    await secure_storage(VaultMateConfig.storageKeys.enc_key_name, derived_key);
    await resetRetryCount();
    await markSessionActive();
    hide_button_loader(th);
    window.location.href = "index.html";

  } catch (err) {
    if(return_status) {
      return false;
    }

    hide_button_loader(th);
    incrementRetryCount();
    show_toast("Incorrect MPIN. Try again.");
  }
}

ons.ready(function() {
  ons.disableDeviceBackButtonHandler();  
});

async function incrementRetryCount() {
  try {
    let count = await secure_storage(VaultMateConfig.storageKeys.retryCount);
    let retries = parseInt(count, 10) || 0;
    retries += 1;

    let maxAttempts = parseInt(await secure_storage(VaultMateConfig.storageKeys.max_attempt), 10) || VaultMateConfig.default.max_attempt;
    if(isNaN(maxAttempts)) {
      maxAttempts = VaultMateConfig.default.max_attempt;
    }

    const remainingAttempts = maxAttempts - retries;

    if(remainingAttempts > 0) {
      show_toast(`Incorrect MPIN. You have ${remainingAttempts} attempt${remainingAttempts > 1 ? "s" : ""} left.`);
    } else {
      show_toast("Too many incorrect attempts");
    }

    if(retries >= maxAttempts) {
      let duration = await secure_storage(VaultMateConfig.storageKeys.lockoutDuration);
      let minutes = parseInt(duration, 10);
      if(isNaN(minutes) || minutes <= 0) {
        minutes = VaultMateConfig.default.lockout_duration;
      }
      const lockUntil = Date.now() + minutes * 60 * 1000;

      // secure_storage is authoritative; localStorage is a display-only cache
      // so the lockout banner can paint instantly on next launch/resume.
      await secure_storage(VaultMateConfig.storageKeys.lockoutTime, lockUntil.toString());
      localStorage.setItem(VaultMateConfig.storageKeys.lockoutTime, lockUntil.toString());

      startLockoutCountdown(lockUntil);
    } else {
      await secure_storage(VaultMateConfig.storageKeys.retryCount, retries.toString());
    }

  } catch (err) {
    await secure_storage(VaultMateConfig.storageKeys.retryCount, "1");
  }
}

async function resetRetryCount() {
  try {
    await secure_storage(VaultMateConfig.storageKeys.retryCount, "0");
    await secure_remove(VaultMateConfig.storageKeys.lockoutTime);
    localStorage.removeItem(VaultMateConfig.storageKeys.lockoutTime); // clear the display cache too
  } catch (err) {
    console.error("resetRetryCount error:", err);
  }
}

async function fingerprintLogin(mode = "login") {

  if(typeof Fingerprint === "undefined") {
    show_toast("Fingerprint not supported on your device");
    return false;
  }

  const lockTime = await secure_storage(VaultMateConfig.storageKeys.lockoutTime);
  if(lockTime && Date.now() < Number(lockTime)) {
      startLockoutCountdown(Number(lockTime));
      return false;
  }

  return new Promise((resolve) => {
    Fingerprint.show({ description: "Authenticate using fingerprint" }, async () => {
      try {
        const cipherKey = await secure_storage(VaultMateConfig.storageKeys.enc_key_name);
        if(!cipherKey) {
          show_toast("Please login with MPIN once");
          resolve(false);
          return;
        }

        if(mode === "login") {
          await VaultDB.openDB(cipherKey);
          await resetRetryCount();
          await markSessionActive();
          const finger_enabled = await secure_storage(VaultMateConfig.storageKeys.fingerprint);
          if(finger_enabled !== "1") {
            await secure_storage(VaultMateConfig.storageKeys.fingerprint, 1);
          }
          window.location.href = "index.html";
          resolve(true);
        }
        if(mode === "verify_mpin_change") {
          mark_identity_verified(); 
          resolve(true);
        }
      } catch (err) {
          show_toast("Fingerprint verification failed");
          resolve(false);
        }
      }, () => {
        show_toast("Fingerprint failed. Use MPIN");
        resolve(false);
      }
    );
  });
}

async function markSessionActive() {
  try {
    sessionActive = true;
    const token = generateSessionToken();
    await secure_storage(VaultMateConfig.storageKeys.session, token);
    show_toast("Login successful");
  } catch (err) {
    show_toast("Login failed. Please try again");
  }
}

async function validateSession(currentPage) {
  //alert("currentPage - "+currentPage);
  try {
    const value = await secure_storage(VaultMateConfig.storageKeys.session);
    if(!value) {
      throw new Error("No session");
    }
    const decoded = JSON.parse(atob(value));
    const now = Date.now();

    if(decoded.device !== (device.uuid || "unknown")) {
      throw new Error("Device mismatch");
    }

    let minutes = parseInt(await secure_storage(VaultMateConfig.storageKeys.inactivity_duration), 10);
    if(isNaN(minutes)) {
      minutes = VaultMateConfig.default.inactivity_duration;
    }
    minutes = Math.min(Math.max(minutes, VaultMateConfig.default.inactivity_min), VaultMateConfig.default.inactivity_max);

    let max_duration = minutes * 60 * 1000;
    if(now - decoded.time < max_duration) {
      sessionActive = true;
      setupActivityTracking();

      if(currentPage == "indexPage") {
        refresh_dashboard(currentPage);
      }

      return;
    }
    throw new Error("Session expired");
  } catch (err) {
    sessionActive = false;
    window.location.href = "login.html";
  }
}

let lastRefresh = 0;
async function refreshSessionTime() {
  if(!sessionActive) {
    return;
  }
  const now = Date.now();
  if(now - lastRefresh < 60000) {
    return;
  }
  lastRefresh = now;
  try {
    const value = await secure_storage(VaultMateConfig.storageKeys.session);
    if(!value) {
      return;
    }
    const decoded = JSON.parse(atob(value));
    decoded.time = Date.now();
    const updatedToken = btoa(JSON.stringify(decoded));
    await secure_storage(VaultMateConfig.storageKeys.session, updatedToken);
  } catch (err) {
    window.location.href = "login.html";
  }
}

let activityTrackingEnabled = false;
function setupActivityTracking() {
  if(activityTrackingEnabled) {
    return;
  }
  activityTrackingEnabled = true;
  const events = ["click", "touchstart"];
  events.forEach(event => {
    document.addEventListener(event, refreshSessionTime, { passive: true });
  });
}

function teardownActivityTracking() {
  const events = ["click", "touchstart"];
  events.forEach(event => {
    document.removeEventListener(event, refreshSessionTime);
  });
  activityTrackingEnabled = false;
}

async function logout() {
  sessionActive = false;
  try {
    teardownActivityTracking();
    await secure_remove(VaultMateConfig.storageKeys.session);
  } catch (e) {}
  window.location.href = "login.html";
}

function generateSessionToken() {
  const payload = {
    time: Date.now(),
    device: device.uuid || "unknown", 
    salt: Math.random().toString(36).substring(2)
  };
  const token = btoa(JSON.stringify(payload)); 
  return token;
}

let lockoutInterval = null;
async function startLockoutCountdown(future, showLogin = true) {
  const now = Date.now();
  const loginBtn = document.getElementById("log_btn");
  const fingerBtn = document.getElementById("finger_login_btn");
  const banner = document.getElementById("lockout-banner");
  const mpinInputs = document.querySelectorAll("#mpinLogin .mpin-input");

  if(!future || future <= now) {
    await resetRetryCount();
    banner.style.display = "none";
    if(loginBtn) {
      loginBtn.disabled = false;
    }
    if(fingerBtn) {
      fingerBtn.disabled = false;
    }
    mpinInputs.forEach(input => {
      input.disabled = false;
    });
    clear_login_inputs();
    return;
  }

  if(lockoutInterval) {
    clearInterval(lockoutInterval);
    lockoutInterval = null;
  }

  let remainingSec = Math.ceil((future - now) / 1000);
  if(showLogin) {
    await showLoginView();
  }
  banner.style.display = "block";

  if(loginBtn) {
    loginBtn.disabled = true;
  }
  if(fingerBtn) {
    fingerBtn.disabled = true;
  }
  mpinInputs.forEach(input => {
    input.disabled = true;
    input.blur(); // drop focus so the keyboard doesn't stay open on a disabled field
  });

  const updateBanner = () => {
    const mins = Math.floor(remainingSec / 60);
    const secs = remainingSec % 60;
    banner.textContent = `Too many incorrect attempts. Try again in ${mins}m ${secs}s`;
  };

  updateBanner();
  lockoutInterval = setInterval(async () => {
    remainingSec--;
    if(remainingSec <= 0) {
      clearInterval(lockoutInterval);
      lockoutInterval = null;
      banner.style.display = "none";
      if(loginBtn) {
        loginBtn.disabled = false;
      }
      if(fingerBtn) {
        fingerBtn.disabled = false;
      }
      mpinInputs.forEach(input => {
        input.disabled = false;
      });
      await resetRetryCount();
    } else {
      updateBanner();
    }
  }, 1000);
}









let currentFormType = null;
let activeCategory = null;

function vm_toggleBottomSheet() {
   const el = document.getElementById('vm_bottomSheet');
   const overlay = document.getElementById('vm_overlay');
   const actions = document.getElementById('vm_sheetActions');

   el.classList.toggle('open');
   overlay.classList.toggle('active');

   if (el.classList.contains('open')) {
      el.setAttribute('aria-hidden', 'false');

      //console.log("currentFormType ", currentFormType);

      if (!currentFormType) {
         const firstTab = document.querySelector('.vm-cat-tab');
         if (firstTab) {
            vm_loadForm('general', firstTab, 1);
         }
      }
      if (currentFormType && actions) actions.style.display = 'flex';
   } else {
      el.setAttribute('aria-hidden', 'true');
      if (actions) actions.style.display = 'none';
   }
}

function close_vault_bottomsheet() {
   const el = document.getElementById('vm_bottomSheet');
   const overlay = document.getElementById('vm_overlay');
   const actions = document.getElementById('vm_sheetActions');

   el.classList.remove('open');
   overlay.classList.remove('active');

   el.setAttribute('aria-hidden', 'true');
   if (actions) actions.style.display = 'none';

   if (activeCategory) {
      activeCategory.classList.remove('active');
   }
   currentFormType = null;
}

function vm_loadForm(type, element, open_sheet) {
   // Update active category styling
   const tabs = document.querySelectorAll('.vm-cat-tab');
   tabs.forEach(tab => tab.classList.remove('active'));

   activeCategory = element;
   element.classList.add('active');

   const container = document.getElementById('vm_formContainer');
   //const titleEl = document.getElementById('vm_sheetTitle');
   const actions = document.getElementById('vm_sheetActions');
   if (!container) return;

   let html = '';
   let title = '';
   let textarea_rows = 4;
   var reminder_html = get_vault_reminder_html(type);

   switch(type) {
      case 'general':
         html = `<h3>General Accounts</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Account Name *</label>
              <input type="text" id="general_account_name" placeholder="e.g., Facebook, Amazon">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Username / Email *</label>
              <input type="text" id="general_username" placeholder="e.g. john.doe@gmail.com" autocomplete="off">
            </div>

            ${renderPasswordField("Password *", "general_password", "Enter your password")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Password *</label>
              <input type="password" id="general_password" placeholder="Enter your password" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('general_password', this)"></i>
            </div>--> 


            <div class="vm-row">
              <label class="vm_remainder_form_label">Website / App URL</label>
              <input type="text" id="general_website" placeholder="https://example.com or app link" autocapitalize="none" autocomplete="off" autocorrect="off" spellcheck="false">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Phone Number</label>
              <input type="tel" id="general_phone_no" inputmode="numeric" autocomplete="tel" placeholder="Enter registered mobile number">
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("general_doc")}              
            </div>   
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="general_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
      break;
      case 'payment_apps':
         html = `<h3>UPI & Mobile Payment Apps</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">App Name *</label>
              <input type="text" id="upi_app_name" placeholder="e.g., PayPal, Apple Pay, Google Pay, Venmo">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Mobile Number / Login ID *</label>
              <input type="text" id="upi_mobile_no" placeholder="Email, phone number, or username">
            </div>
            <div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Login Password / PIN</label>
              <input type="password" id="upi_login_pin" placeholder="Enter your login password or PIN" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('upi_login_pin', this)"></i>
            </div>
            <div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Payment PIN</label>
              <input type="password" id="upi_payment_pin" placeholder="PIN used for payments or authorizing transactions">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('upi_payment_pin', this)"></i>
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("upi_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="upi_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
      break;
      case 'bank_accounts':
         html = `<h3>Bank Accounts</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Bank Name *</label>
              <input type="text" id="bank_name" placeholder="e.g., Chase, SBI, Citi, Barclays">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Account Holder Name *</label>
              <input type="text" id="bank_acc_holder_name" placeholder="Enter name as per bank records">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Account Number *</label>
              <input type="text" id="bank_account_no" placeholder="Enter your account number">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Routing / SWIFT / IBAN / IFSC</label>
              <input type="text" id="bank_ifsc_code" placeholder="e.g., SWIFT: CHASUS33 or IBAN: GB29NWBK60161331926819">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Online Banking Username</label>
              <input type="text" id="bank_online_username" placeholder="Enter your online banking username">
            </div>
            ${renderPasswordField("Online Banking Password", "bank_online_password", "Enter your online banking password")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Online Banking Password</label>
              <input type="password" id="bank_online_password" placeholder="Enter your online banking password" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('bank_online_password', this)"></i>
            </div>-->

            ${renderPasswordField("Transaction Security Code", "bank_tran_sec_code", "PIN / OTP / security password used for transactions")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Transaction Security Code</label>
              <input type="password" id="bank_tran_sec_code" placeholder="PIN / OTP / security password used for transactions">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('bank_tran_sec_code', this)"></i>
            </div>-->

            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("bank_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="bank_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
          </div>`;
      break;
      case 'cards':
         html = `<h3>Credit / Debit / Prepaid Cards</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Card Type *</label>
              <select id="card_cardtype" placeholder="Card Type">
                <option value="">Card Type</option>
                <option value="credit">Credit Card</option>
                <option value="debit">Debit Card</option>
                <option value="prepaid">Prepaid Card</option>
              </select>
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Card Holder Name *</label>
              <input type="text" id="card_holder_name" placeholder="Name on card">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Card Network *</label>
              <input type="text" id="card_provider_name" placeholder="e.g., Visa, Mastercard, Amex">
            </div> 
            <div class="vm-row">
              <label class="vm_remainder_form_label">Card Number *</label>
              <input type="tel" id="card_card_no" placeholder="xxxx xxxx xxxx xxxx" maxlength="23">
            </div>

            <div class="vm-row-d-flex">
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Expiry Date *</label>
                <input type="text" id="card_expiry_date" inputmode="numeric" placeholder="MM / YY" maxlength="7">
              </div>
              <div class="vm-row vm-row-flex input-wrapper">
                <label class="vm_remainder_form_label">CVV / CVC / CID</label>
                <input type="password" id="card_cvv_no" inputmode="numeric" pattern="[0-9]{3,4}" maxlength="4" placeholder="CVV" autocomplete="cc-csc">
                <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('card_cvv_no', this)"></i>
              </div>
            </div>

            <div class="vm-row-d-flex">
              <div class="vm-row vm-row-flex input-wrapper">
                <label class="vm_remainder_form_label">Card PIN</label>
                <input type="password" id="card_card_pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="Enter card PIN" autocomplete="off">
                <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('card_card_pin', this)"></i>
              </div>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Billing Postal Code</label>
                <input type="text" id="card_zip_code" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="e.g., 10001 or 560001 or 400066" autocomplete="postal-code">
              </div>
            </div>  

            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("card_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="card_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'work_accounts':
         html = `<h3>Work / Business Accounts</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Account Name *</label>
              <input type="text" id="work_account_name" placeholder="e.g., Jira, Slack, GitHub, AWS Console">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Username / Email / Employee ID *</label>
              <input type="text" id="work_username" placeholder="Work email, username, or employee ID">
            </div>
            ${renderPasswordField("Password *", "work_password", "Enter account password")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Password *</label>
              <input type="password" id="work_password" placeholder="Enter account password" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('work_password', this)"></i>
            </div>-->

            <div class="vm-row">
              <label class="vm_remainder_form_label">Company / Project Name</label>
              <input type="text" id="work_company" placeholder="e.g., Google, Deloitte, Project X">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">2FA / Token / Security Code Info</label>
              <input type="text" id="work_token" placeholder="Add authenticator info, backup codes, or token details">
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("work_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="work_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'government_ids':
         html = `<h3>Government IDs / Identity Documents</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Document Name *</label>
              <input type="text" id="gov_doc_name" placeholder="e.g., Passport, National ID, Driver's License">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Document Number *</label>
              <input type="text" id="gov_doc_id" placeholder="Enter ID number">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Full Name on ID *</label>
              <input type="text" id="gov_name_id" placeholder="Name as printed on the ID">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Country / Issuing Authority *</label>
              <input type="text" id="gov_country" placeholder="e.g., USA, UK Home Office, Govt. of India">
            </div>
            <div class="vm-row-d-flex" data-date-group>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Issue Date</label>
                <input type="date" id="gov_issue_date" placeholder="Select issue date" data-date="start">
              </div>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Expiry Date</label>
                <input type="date" id="gov_exp_date" placeholder="Select expiry date" data-date="end">
              </div>
            </div>
            ${reminder_html}
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("gov_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="gov_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'subscriptions':
         html = `<h3>Subscriptions / Services</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Service Name *</label>
              <input type="text" id="subs_name" placeholder="e.g., Netflix, Spotify, Amazon Prime">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Username / Email *</label>
              <input type="text" id="subs_username" placeholder="Login email or username">
            </div>
         
            ${renderPasswordField("Password *", "subs_password", "Enter account password")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Password *</label>
              <input type="password" id="subs_password" placeholder="Enter account password" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('subs_password', this)"></i>
            </div>-->

            <div class="vm-row">
              <label class="vm_remainder_form_label">Plan Type</label>
              <input type="text" id="subs_plan_type" placeholder="e.g., Monthly, Yearly, Premium, Family Plan">
            </div>
            <div class="vm-row-d-flex">
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Subscription Cost</label>
                <input type="text" id="subs_cost" placeholder="e.g., $9.99/month or ₹149/month">
              </div>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Renewal Date</label>
                <input type="date" id="subs_renewal" placeholder="Select renewal date">
              </div>
           </div>
            ${reminder_html}
            <div class="vm-row">
              <label class="vm_remainder_form_label">Payment Method</label>
              <input type="text" id="subs_pay_method" placeholder="e.g., Visa **** 1234 or PayPal">
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("subs_doc")}              
            </div>            
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="subs_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'wifi':
         html = `<h3>Wi-Fi / Device Credentials</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">SSID / Wi-Fi Name *</label>
              <input type="text" id="wifi_name" placeholder="e.g., MyHomeWiFi">
            </div>

            ${renderPasswordField("Password *", "wifi_password", "Enter account password")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">Password *</label>
              <input type="password" id="wifi_password" placeholder="Enter Wi-Fi password" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('wifi_password', this)"></i>
            </div>-->

            <div class="vm-row">
              <label class="vm_remainder_form_label">Wi-Fi Type</label>
              <input type="text" id="wifi_type" placeholder="e.g., 2.4 GHz, 5 GHz, Guest">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Router Admin URL</label>
              <input type="url" id="wifi_admin_url" placeholder="e.g., http://192.168.1.1" inputmode="url" autocomplete="off">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Router Admin Username</label>
              <input type="text" id="wifi_username" placeholder="e.g., admin">
            </div>

            ${renderPasswordField("Router Admin Password", "wifi_admin_password", "Enter router admin password")}

            <!--<div class="vm-row">
              <label class="vm_remainder_form_label">Router Admin Password</label>
              <input type="password" id="wifi_admin_password" placeholder="Enter router admin password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('wifi_admin_password', this)"></i>
            </div>-->

            <div class="vm-row">
              <label class="vm_remainder_form_label">Location</label>
              <input type="text" id="wifi_location" placeholder="e.g., Home, Office, Living Room">
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("wifi_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="wifi_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'software_keys':
         html = `<h3>License Keys / Software Keys</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Software / Product Name *</label>
              <input type="text" id="lic_soft_name" placeholder="e.g., Windows 11 Pro, Adobe Photoshop">
            </div>

            ${renderPasswordField("License Key / Product Key *", "lic_key", "Enter license key")}

            <!--<div class="vm-row input-wrapper">
              <label class="vm_remainder_form_label">License Key / Product Key *</label>
              <input type="password" id="lic_key" placeholder="Enter license key" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('lic_key', this)"></i>
            </div>-->
         
            <div class="vm-row">
              <label class="vm_remainder_form_label">Version / Edition</label>
              <input type="text" id="lic_version" placeholder="e.g., Pro, Enterprise, 2024 version">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Purchase Email</label>
              <input type="text" id="lic_pur_email" placeholder="Email used during purchase">
            </div>
            <div class="vm-row-d-flex" data-date-group>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Purchase Date</label>
                <input type="date" id="lic_pur_date" placeholder="Select purchase date" data-date="start">
              </div>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Expiry / Renewal Date</label>
                <input type="date" id="lic_exp_date" placeholder="Select expiry or renewal date" data-date="end">
              </div>
            </div>  
            ${reminder_html}      
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("lic_doc")}              
            </div>
            <div class="vm-row">              
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="lic_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'crypto_wallets':
         html = `<h3>Crypto Wallets</h3>
            <div class="vm-row">              
              <label class="vm_remainder_form_label">Wallet Name *</label>
              <input type="text" id="crypto_name" placeholder="e.g., MetaMask, Phantom, Ledger, Exodus">
            </div>
            <div class="vm-row">              
              <label class="vm_remainder_form_label">Wallet Address *</label>
              <input type="text" id="crypto_address" placeholder="Enter public wallet address">
            </div>
            <div class="vm-row">              
              <label class="vm_remainder_form_label">Blockchain / Network</label>
              <input type="text" id="crypto_block_chain" placeholder="e.g., Bitcoin, Ethereum, Solana">
            </div>
            <div class="vm-row">              
              <label class="vm_remainder_form_label">Wallet Type</label>
              <input type="text" id="crypto_wallet_type" placeholder="e.g., Hardware, Mobile, Browser Extension">
            </div>
            <div class="vm-row input-wrapper">              
              <label class="vm_remainder_form_label">Recovery Phrase / Seed Words</label>
              <input type="password" id="crypto_seed" placeholder="Enter 12 or 24-word recovery phrase" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('crypto_seed', this)"></i>
            </div>
            <div class="vm-row input-wrapper">              
              <label class="vm_remainder_form_label">Private Key</label>
              <input type="password" id="crypto_private" placeholder="Enter private key if applicable" autocomplete="new-password">
              <i class="fa fa-eye toggle-eye" onclick="toggle_password_icon('crypto_private', this)"></i>
            </div>        
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("crypto_doc")}              
            </div>
            <div class="vm-row">                            
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="crypto_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'insurance':
         html = `<h3>Insurance Policies</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Policy Name *</label>
              <input type="text" id="ins_policy_name" placeholder="e.g., Health Insurance, Auto Insurance, Life Insurance">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Policy Number *</label>
              <input type="text" id="ins_policy_no" placeholder="Enter policy number">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Insurer Name</label>
              <input type="text" id="ins_insurer" placeholder="e.g., Allianz, AXA, LIC, Aetna">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Policy Type</label>
              <input type="text" id="ins_policy_type" placeholder="e.g., Health, Life, Auto, Home, Travel">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Policy Holder Name</label>
              <input type="text" id="ins_policy_holder_name" placeholder="Name of insured person">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Coverage Amount</label>
              <input type="text" id="ins_coverage_amt" placeholder="e.g., $100,000 or ₹5,00,000">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Premium Amount</label>
              <input type="text" id="ins_pre_amt" placeholder="e.g., $50/month or ₹1200/year">
            </div>
            <div class="vm-row-d-flex" data-date-group>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Start / Issue Date</label>
                <input type="date" id="in_st_date" placeholder="Select start date" data-date="start">
              </div>         
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Expiry / Renewal Date</label>
                <input type="date" id="ins_exp_date" placeholder="Select expiry or renewal date" data-date="end">
              </div>    
            </div>  
            ${reminder_html}            
            <div class="vm-row">
              <label class="vm_remainder_form_label">Attachments</label>
              ${render_file_html("ins_doc")}              
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="ins_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
      case 'certificates':
         html = `<h3>Education / Certificates</h3>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Certificate Name *</label>
              <input type="text" id="edu_cer_name" placeholder="e.g., Degree Certificate, AWS Certification, Completion Certificate">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Enrollment / Certificate ID *</label>
              <input type="text" id="edu_enr_id" placeholder="Enrollment ID, Certificate ID, or Registration No.">
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Institution / University</label>
              <input type="text" id="edu_ins_name" placeholder="e.g., Harvard University, AWS, Google">
            </div>            
            <div class="vm-row-d-flex" data-date-group>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Issue Date</label>
                <input type="date" id="edu_issue_date" placeholder="Select issue date" data-date="start">
              </div>
              <div class="vm-row vm-row-flex">
                <label class="vm_remainder_form_label">Expiry Date</label>
                <input type="date" id="edu_exp_date" placeholder="Select expiry date" data-date="end">
              </div>
            </div>
            ${reminder_html}
            <div class="vm-row">
              <label class="vm_remainder_form_label">Certificate File</label>
              ${render_file_html("edu_doc")}  
            </div>
            <div class="vm-row">
              <label class="vm_remainder_form_label">Notes</label>
              <textarea id="edu_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
            </div>`;
         break;
       case 'medical_records':
         html = `<h3>Medical Records</h3>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Hospital / Portal Name *</label>
                <input type="text" id="medical_hospital_name" placeholder="e.g., Apollo Hospital, Mayo Clinic, NHS">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Patient Name *</label>
                <input type="text" id="medical_patient_name" placeholder="Name of the patient">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Patient ID / Medical Record Number</label>
                <input type="text" id="medical_patient_id" placeholder="e.g., MRN12345 or Patient ID">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Doctor / Provider Name</label>
                <input type="text" id="medical_doctor_name" placeholder="Doctor or healthcare provider name">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Visit / Report Date</label>
                <input type="date" id="medical_visit_date" placeholder="Select date of visit or report">
              </div>    
              ${reminder_html}                 
              <div class="vm-row">
                <label class="vm_remainder_form_label">Attachments</label>
                ${render_file_html("medical_doc")}              
              </div>
              <div class="vm-row">                
                <label class="vm_remainder_form_label">Notes</label>
                <textarea id="medical_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
              </div>`;
        break;
      case 'vehicles':
         html = `<h3>Vehicles</h3>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Vehicle Name / Model *</label>
                <input type="text" id="vehicle_name" placeholder="e.g., Tesla Model 3, Honda Civic, Yamaha MT-15">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Registration / Plate Number *</label>
                <input type="text" id="vehicle_no" placeholder="e.g., ABC 1234 or MH12AB1234">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Vehicle Type</label>
                <input type="text" id="vehicle_type" placeholder="e.g., Car, Bike, Scooter, EV">
              </div>              
              <div class="vm-row">
                <label class="vm_remainder_form_label">Insurance Expiry Date</label>
                <input type="date" id="vehicle_ins_exp" placeholder="Select insurance expiry date">
              </div>
              ${reminder_html}
              <div class="vm-row">
                <label class="vm_remainder_form_label">Attachments</label>
                ${render_file_html("vehicle_doc")}
              </div>
              <div class="vm-row">                
                <label class="vm_remainder_form_label">Notes</label>
                <textarea id="vehicle_notes" rows="${textarea_rows}" placeholder="Optional notes"></textarea>
              </div>`;
        break;
      case 'secure_notes':
         html = `<h3>Secure Notes</h3>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Title *</label>
                <input type="text" id="secure_title" placeholder="Enter note title">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Tag / Category</label>
                <input type="text" id="secure_tag" placeholder="e.g., Personal, Work, Server Info">
              </div>
              <div class="vm-row">
                <label class="vm_remainder_form_label">Attachments</label>
                ${render_file_html("secure_doc")}
              </div>
              <div class="vm-row">                
                <label class="vm_remainder_form_label">Notes *</label>
                <textarea id="secure_notes" rows="${textarea_rows}" placeholder="Write your secure note here"></textarea>
              </div>`;
        break;
      default:
      html = `<p>Coming soon.</p>`;
  }

  html += `<input id="category_slug" type="hidden" value="${type}">
          <input id="vault_id" type="hidden" value=""><div id="vm_edit_attachments"></div>`
  container.innerHTML = html;
  init_vault_reminder_events();
  if(actions) {
    actions.style.display = 'flex';
  }
  container.scrollTo({top: 0, behavior: "smooth"});
  if(open_sheet == 1) {    
    open_bottom_sheet("vm_bottomSheet");
  }
}

function formatDate(dateString) {
  const options = {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
  };
  return new Date(dateString).toLocaleDateString(undefined, options);
}

function change_status_bar() {
  var current_mode = (typeof app_mode !== 'undefined') ? app_mode : "light";
  var status_bar_color = current_mode === "dark" ? "#121212FF" : "#4361EEFF";
  var nav_bar_color = current_mode === "dark" ? "#1e1e1e" : "#ffffff";
  var light_icons = current_mode !== "dark"; // dark mode -> light (white) icons

  if(window.statusbar) {
    window.statusbar.visible = true;
    window.statusbar.setBackgroundColor(status_bar_color);
  } else {
    console.warn("Built-in statusbar API not available yet.");
  }

  if(window.MahaNavBar) {
    MahaNavBar.setColor(nav_bar_color, light_icons);
  } else {
    console.warn("MahaNavBar plugin not available yet.");
  }
}

function check_app_updates() {
  var LAST_CHECK_KEY = "mahavault_last_update_check";
  var CHECK_INTERVAL_MS = 15 * 60 * 1000; // 15 minutes
  var lastCheck = parseInt(localStorage.getItem(LAST_CHECK_KEY) || '0', 10);
  var now = Date.now();

  if (now - lastCheck > CHECK_INTERVAL_MS) {
    if(window.InAppUpdate) {
      window.InAppUpdate.check(function(res) {
        localStorage.setItem(LAST_CHECK_KEY, Date.now());
        if(res.updateAvailable && res.immediateAllowed) {
          triggerImmediateUpdate();
        } else {
          console.log("No immediate update available or not allowed:", JSON.stringify(res));
        }
      }, function(err) {
        console.log("Silent update check failed/offline:", err);
      });
    }
  }
}

function triggerImmediateUpdate() {
  window.InAppUpdate.startUpdate("immediate", function(status) {
    console.log("InAppUpdate Status:", status);
  }, function(err) {
    console.log("Immediate update flow error or user cancelled:", err);
  });
}

function get_current_context(fromEl) {
  const page = fromEl?.closest("ons-page");
  if(!page) return null;
  if(page.id === "vault_page") return "vault";
  if(page.id === "reminder_page") return "reminder";
  return null;
}

async function reset_add_bs() {
  const wrap = dgi("vm_catsHorizontal");
  const items = wrap?.querySelectorAll(".category-item");
  if(!items || !items.length) {
    return;
  }
  clear_upgrade_mdl();

  Object.keys(pending_attachments).forEach(key => {
    pending_attachments[key] = [];
    const input = dgi(key);
    if(input) {
      input.value = "";
    }
    const preview = dgi(key.replace("_file","_preview"));
    if(preview) {
      preview.innerHTML = "";
    }
    
    const count = dgi(key.replace("_file","_count"));
    if(count) {
        count.textContent = "";
    }
  });

  items.forEach(el => el.classList.remove("active"));
  const first = items[0];
  first.classList.add("active");

  const firstSlug = first.dataset.slug;
  if(firstSlug) {
    vm_loadForm(firstSlug, first, 0);
  }
  wrap.scrollTo({left:0, behavior:"auto"});
  const form = dgi("vm_formContainer");
  if(form){
    form.scrollTop = 0;
  }
}

function lock_categories(lock = true) {
  const tabs = document.querySelectorAll("#vm_catsHorizontal .vm-cat-tab");
  const wrap = dgi("vm_catsHorizontal");
  if(wrap) {
    wrap.style.overflowX = lock ? "hidden" : "";
  }

  tabs.forEach(tab => {
    tab.style.pointerEvents = lock ? "none" : "";
    if(lock) {
      tab.style.opacity = tab.classList.contains("active") ? "1" : "0.5";
    } else {
      tab.style.opacity = "";
    }
  });
}

async function render_categories() {
  const wrap = dgi("vm_catsHorizontal");
  wrap.innerHTML = "";


  //alert("inside render_categories")
  const categories = await get_ordered_categories(); 
  //console.log("categories", categories);

  categories.forEach((cat, index) => {
    const div = document.createElement("div");
    div.className = "vm-cat-tab category-item";
    div.dataset.slug = cat.slug;

    //console.log("index", index, "slug", cat.slug)

    if(index === 0) {
      div.classList.add("active");
      vm_loadForm(cat.slug, div, 0);
    }
    div.onclick = function () {
      vm_loadForm(cat.slug, this, 1);
      this.scrollIntoView({behavior: "smooth", inline: "center", block: "nearest"});
    };

    div.innerHTML = `<div class="category-circle"><i class="fas ${cat.icon}"></i></div><div class="category-name">${cat.name}</div>`;
    wrap.appendChild(div);
  });
}

async function get_ordered_categories() {
  const saved = await secure_storage(VaultMateConfig.storageKeys.category_sequence);
  
  //alert("saved "+ saved);
  let categories = [...vault_categories];

  if(!saved || saved === "null" || saved === "") {
    return categories;
  }

  try {
    const orderedSlugs = JSON.parse(saved);
    const slugMap = Object.fromEntries(categories.map(c => [c.slug, c]));
    const ordered = [];

    orderedSlugs.forEach(slug => {
      if(slugMap[slug]) {
        ordered.push(slugMap[slug]);
        delete slugMap[slug];
      }
    });

    Object.values(slugMap).forEach(cat => {
      ordered.push(cat);
    });
    return ordered;
  } catch (e) {
    return categories;
  }
}

function open_bottom_sheet(sheetId) {
  //close_bottom_sheet();
  const current_sheet = dgi(sheetId);
  if(!current_sheet) {
      return;
  }
  active_bottom_sheet = current_sheet;
  current_sheet.classList.add("open");
  bottom_sheet_overlay.classList.add("active");
}

function reset_edit_reminder() {
  is_rm_mode = false;
  editing_rm_id = null;

  dgi("rm_title").value = "";
  dgi("rm_desc").value = "";

  dgi("rm_start_date").value = "";
  dgi("rm_time").value = "";

  dgi("repeat_interval").value = "";
  dgi("rm_repeat_end_date").value = "";
  dgi("rm_category").selectedIndex = 0; 
  
  type = "onetime";

  dgi("reminder_sheet_title").innerHTML = "Add New Reminder"; 
  document.querySelectorAll(".vm_remainder_type_option").forEach(el => el.classList.remove("active"));

  const selected = document.querySelector(`[data-type="${type}"]`);
  if (selected) selected.classList.add("active");
  dgi("vm_remainder_repeat_options").classList.toggle("active", type === "repeat");

}

function reset_edit_vault() {
  is_edit_mode = false;
  edit_vault_id = null;
  edit_attachments = [];
  lock_categories(false);
  dgi("vault_id").value = "";

  document.querySelectorAll("#vm_formContainer input, #vm_formContainer textarea").forEach(el => {
    if (el.type !== "hidden") el.value = "";
  });

  /*const cfg = category_config[dgi("category_slug")?.value];
  if(cfg?.fileField) {
    const fileInput = dgi(cfg.fileField);
    if (fileInput) fileInput.value = "";
  }*/

  Object.keys(pending_attachments).forEach(key => {
    pending_attachments[key] = [];

    const input = dgi(key);
    if(input) {
        input.value = "";
    }

    const preview = dgi(key.replace("_file", "_preview"));
    if(preview) {
        preview.innerHTML = "";
    }

    const count = dgi(key.replace("_file", "_count"));
    if(count) {
        count.textContent = "";
    }
  });

  const wrap = dgi("vm_edit_attachments");
  if(wrap) {
    wrap.innerHTML = "";
  }

}

function close_bottom_sheet() {

  if(vault_sheet_locked) {
    return;
  }

  if(active_bottom_sheet) {
    active_bottom_sheet.classList.remove("open");
    active_bottom_sheet = null;
  }
  bottom_sheet_overlay.classList.remove("active");
  current_vault_id = null;

  if(is_edit_mode) {
    reset_edit_vault();
  }
  if(is_rm_mode) {
    reset_edit_reminder();
  }  

  const copySelectPanel = dgi("vm_copy_select_panel");
  if(copySelectPanel && !copySelectPanel.classList.contains("hide")) {
    cancel_copy_selected();
  }
}

document.addEventListener("click", async e => {
  dgi("vaultMenu")?.classList.add("hide");

  const actionBtn = e.target.closest(".vm-action-btn");
  if(actionBtn) {
      const id = actionBtn.dataset.id;
      if(!actionBtn.classList.contains("vm_action_disabled")) {
          mark_rm(id);
      }
      return; 
  }
  const rm_list_item = e.target.closest(".vm_rem_list .list-item");
  if(rm_list_item) {
      const id = rm_list_item.dataset.rmId;
      await open_reminder_detail(id); 
      return;
  }

  const open_btn = e.target.closest(".vm-sheet-open");
  if(open_btn) {
      const action = open_btn.dataset.action;
      let bottom_sheet_id = null;
      if(action === "filter" || action === "sort" || action === "add") {
          const context = get_current_context(open_btn);
          if(!context) {
            return;
          }
          if(context === "vault") {
              if(action === "filter") bottom_sheet_id = "vm_vault_filterSheet";
              if(action === "sort") bottom_sheet_id = "vm_vault_sortSheet";
          } else if(context === "reminder") {
              if(action === "filter") bottom_sheet_id = "vm_remainder_filter_sheet";
              if(action === "sort") bottom_sheet_id = "vm_remainder_sort_sheet";
              if(action === "add") {
                is_rm_mode = true;
                dgi("reminder_sheet_title").innerHTML = "Add New Reminder"; 
                bottom_sheet_id = "vm_remainder_bottom_sheet";
              }
          }
      }

      if(!bottom_sheet_id) {
          let target = open_btn.dataset.target;
          if(!target) {
            return;
          }

          if(target === "vm_bottomSheet") {
            const sheet = dgi("vm_bottomSheet");
            clear_upgrade_mdl();
            reset_add_bs(); 

            if(!is_edit_mode) {
              const isPremium = await isPremiumUser();
              if(!isPremium) {
                const total = await count_vaults_db();
                vm_log("vault total", total, "isPremium", isPremium);
                if(total >= VAULT_LIMIT) {
                  sheet.classList.add("vm-upgrade-mode");
                  show_upgrade_inside_sheet("vault");
                  open_bottom_sheet("vm_bottomSheet");

                  setTimeout(() => {
                    is_internet(() => {
                      updatePriceUI(true);
                    });
                  }, 300);
                  return;
                }
              }
            }
          } 
          else if(target == "vm_remainder_bottom_sheet" && !editing_rm_id) {
            const isPremium = await isPremiumUser();
            if(!isPremium) {
              const total = await count_reminders_db();
              vm_log("reminder total", total, "isPremium", isPremium);
              
              if(total >= REMINDER_LIMIT) {
                const sheet = dgi("vm_bottomSheet");
                clear_upgrade_mdl();
                reset_add_bs(); 

                sheet.classList.add("vm-upgrade-mode");
                show_upgrade_inside_sheet("reminder");
                open_bottom_sheet("vm_bottomSheet");
                setTimeout(() => {
                  is_internet(() => {
                    updatePriceUI(true);
                  });
                }, 300);
                return;
              }
            }
          }
          bottom_sheet_id = target;
      }
      if(bottom_sheet_id) {
        open_bottom_sheet(bottom_sheet_id);
      }
      return;
  }
  if (e.target.closest(".vm-sheet-close") || e.target === bottom_sheet_overlay) {
      close_bottom_sheet();
  }
});

function clear_upgrade_mdl() {
  const sheet = dgi("vm_bottomSheet");
  if(sheet) {
    sheet.classList.remove("vm-upgrade-mode");
  }
  const actions = dgi("vm_sheetActions");
  if(actions) {
    actions.style.display = "flex";
  }
}

function show_upgrade_inside_sheet(type) {
    if (typeof initBilling === "function") {
        initBilling();
    }

    const container = dgi("vm_formContainer");
    const actions = dgi("vm_sheetActions");
    const priceData = updatePriceUI(true);

    const priceMain     = priceData.price || "...";
    const priceOld      = priceData.oldPrice || "";
    const saveAmount    = priceData.saved ? `Save ${priceData.saved}` : "";
    const oldPriceHtml  = priceOld   ? `<span class="vm_upgrade_price_old">${priceOld}</span>`  : "";
    const saveBadgeHtml = saveAmount ? `<div class="vm_upgrade_save_badge">${saveAmount}</div>`  : "";
    const btnPrice      = priceData.price ? ` · ${priceData.price}` : "";

    if (actions) {
        actions.style.display = "none";
    }

    let type_str     = "vaults";
    let type_limit   = VAULT_LIMIT;
    let type_str_sin = "Vault";

    if (type == "reminder") {
        type_str     = "reminders";
        type_limit   = REMINDER_LIMIT;
        type_str_sin = "Reminder";
    }

    container.innerHTML = `<div class="vm_upgrade_state"> 
      <div class="vm_upgrade_hero">
        <div class="vm_upgrade_urgency">
            <i class="fas fa-bolt"></i>
            Limited time — price increases soon
        </div>
        <h3 class="vm_upgrade_hero_title">
            <i class="fas fa-crown"></i>
            ${type_str_sin} limit reached
        </h3>
        <p class="vm_upgrade_hero_desc">
            You've used all <strong>${type_limit} free ${type_str}</strong>.<br>
            Upgrade to continue securely.
        </p>
        <div class="vm_upgrade_price_card">
            <div>
                <span class="vm_upgrade_price_label">One-time · Lifetime access</span>
                <div class="vm_upgrade_price_row">
                    <span class="vm_upgrade_price_main">${priceMain}</span>
                    ${oldPriceHtml}
                </div>
                <span class="vm_upgrade_lifetime_note">No subscriptions · No hidden fees</span>
            </div>
            ${saveBadgeHtml}
        </div>
      </div>
      <div class="vm_upgrade_body">
          <p class="vm_upgrade_section_label">What you unlock</p>
          <div class="vm_upgrade_features">
              <div class="vm_upgrade_feat_row f1">
                  <div class="vm_upgrade_feat_icon"><i class="fas fa-shield-alt"></i></div>
                  <span class="vm_upgrade_feat_text">Unlimited vaults</span>
                  <span class="vm_upgrade_feat_tag">Unlimited</span>
              </div>
              <div class="vm_upgrade_feat_row f2">
                  <div class="vm_upgrade_feat_icon"><i class="fas fa-bell"></i></div>
                  <span class="vm_upgrade_feat_text">Unlimited reminders</span>
                  <span class="vm_upgrade_feat_tag">Unlimited</span>
              </div>                    
          </div>
          <div class="vm_upgrade_progress_text">
              <span>${type_limit} of ${type_limit} ${type_str} used</span>
              <span class="vm_upgrade_left">0 left</span>
          </div>
          <div class="vm_upgrade_bar">
              <div class="vm_upgrade_fill" style="width:100%"></div>
          </div>
          <div class="vm_upgrade_actions">
              <button class="btn btn-secondary" onclick="open_upgrade()">
                  Learn more
              </button>
              <button class="btn btn-primary" onclick="buy_premium('1')">
                  <i class="fas fa-crown" style="color:#FFD84D;font-size:12px;margin-right:5px;"></i>
                  Unlock Premium${btnPrice}
              </button>
          </div>
          <p class="vm_upgrade_fine_print">
              <i class="fas fa-lock"></i>
              Secure Google Play checkout · One-time payment
          </p>
      </div>
    </div>`;
}
