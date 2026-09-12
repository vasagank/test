const DSECTIONS = {
  OVERVIEW: "dashboard.overview",
  PIN_FAV: "dashboard.pin_fav",
  RECENT_USED: "dashboard.recent_used",
  TOP_USED: "dashboard.top_used",
  CATEGORIES: "dashboard.categories",
  ATTENTION: "dashboard.attention",
  REMINDER_STATS: "dashboard.reminder_stats",
  UPCOMING_REMINDERS: "dashboard.upcoming_reminders",
  BACKUP: "dashboard.backup",
  PASSWORD_HEALTH: "dashboard.password_health",
  SECURITY_INSIGHTS: "dashboard.security_insights",
  BLAST_RADIUS: "dashboard.blast_radius",
};

document.addEventListener("click", function(e) {
  if(!e.target.classList.contains("vm_vault_tab")) return;

  const tab = e.target.dataset.tab;

  // remove active
  document.querySelectorAll(".vm_vault_tab").forEach(t => t.classList.remove("active"));
  document.querySelectorAll(".vm_tab_content").forEach(c => c.classList.remove("active"));

  // activate
  e.target.classList.add("active");
  document.getElementById("vm_tab_" + tab).classList.add("active");
});

async function dashboard_section({key, dbLoader, renderer, force = false, silent = false}) {

  if(!silent) {
    show_section_loader(key);
  }  

  try {
    if(!force) {
      const cached = DashboardCache.get(key);
      //console.log("cached", cached, "dbLoader", dbLoader);

      if(cached) {
        renderer(cached);
        if(!silent) {
          hide_section_loader(key);
        }
        return cached;
      }
    }

    const freshData = await dbLoader();
    DashboardCache.set(key, freshData);
    renderer(freshData);

    if(!silent) {
      hide_section_loader(key);
    }
    return freshData;

  } catch (e) {
    if(!silent) {
      hide_section_loader(key);
    }       
    console.error("Dashboard load failed:", key, e);
    alert("Error: " + e.message + "\n\nStack Trace:\n" + e.stack);
  }
}

const DashboardCache = {
  get(key) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw).data : null;
    } catch {
      return null;
    }
  },
  set(key, data) {
    localStorage.setItem(key, JSON.stringify({
      data,
      ts: Date.now()
    }));
  },
  clear(key) {
    localStorage.removeItem(key);
  }
};

function do_backup() {
    const overlayNav = document.getElementById("overlayNavigator");
    overlayNav.classList.remove("hide");
    overlayNav.pushPage("settings_wrapper.html", {
        animation: "slide",
        animationOptions: {
            duration: 0.2
        }
    }).then(() => {
        overlayNav.pushPage("export.html", {
            animation: "slide",
            animationOptions: {
                duration: 0.2
            }
        });
    });
}

function renderEmptyState({icon, title, desc, buttonText, onClick = "", className = "", dataTarget = ""}) {

  let btnAttrs = "";
  if(onClick) {
    btnAttrs += ` onclick="${onClick}"`;
  }
  if(dataTarget) {
    btnAttrs += ` data-target="${dataTarget}"`;
  }

  return `
    <div class="vm_empty_state">
      <i class="${icon} vm_empty_icon"></i>
      <div class="vm_empty_title">${title}</div>
      <div class="vm_empty_sub">${desc}</div>
      ${buttonText ? `<button class="vm_vault_dash_view_all_btn ${className}" ${btnAttrs}>${buttonText}</button>` : ``}
    </div>
  `;
}

function refresh_dashboard_action(action, force = true, silent = true) {
  const sections = DASHBOARD_ACTION_MAP[action];
  if(!sections) {
    return;
  }
  sections.forEach(sectionKey => {
    const cfg = DASHBOARD_SECTION_REGISTRY[sectionKey];
    if(!cfg) {
      return;
    }
    dashboard_section({force, silent, key:sectionKey, dbLoader:cfg.dbLoader, renderer:cfg.renderer});
  });
}

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function refresh_dashboard(page, force = false) {
  //alert("page " + page);
  //console.log("page", page, "force", force);

  try {   

    const tasks = [];
    tasks.push(dashboard_section({force:force, key:DSECTIONS.OVERVIEW, dbLoader:DashboardService.overview, renderer:d_overview}));

    tasks.push(dashboard_section({force:force, key:DSECTIONS.PIN_FAV, dbLoader:DashboardService.pinfavVaults, renderer:d_pin_fav}));
    tasks.push(dashboard_section({force:force, key:DSECTIONS.RECENT_USED, dbLoader:DashboardService.recentVaults, renderer:d_recentused}));
    tasks.push(dashboard_section({force:force, key:DSECTIONS.TOP_USED, dbLoader:DashboardService.topVaults, renderer:d_mostused}));

    tasks.push(dashboard_section({force:force, key:DSECTIONS.CATEGORIES, dbLoader:DashboardService.topCategories, renderer:d_categories}));
    tasks.push(dashboard_section({force:force, key:DSECTIONS.ATTENTION, dbLoader:DashboardService.attention, renderer:d_attention}));

    tasks.push(dashboard_section({force:force, key:DSECTIONS.REMINDER_STATS, dbLoader:DashboardService.reminderStats, renderer:d_reminder_stats}));
    tasks.push(dashboard_section({force:force, key:DSECTIONS.UPCOMING_REMINDERS, dbLoader:DashboardService.nextReminders, renderer:d_up_reminder}));

    tasks.push(dashboard_section({force, key:DSECTIONS.BACKUP, dbLoader: DashboardService.backupStatus, renderer: d_backup_status}));
    tasks.push(dashboard_section({force:force, key:DSECTIONS.PASSWORD_HEALTH, dbLoader:DashboardService.passwordHealth, renderer:d_password_health}));

    tasks.push(dashboard_section({
      force: force,
      key: DSECTIONS.SECURITY_INSIGHTS,
      dbLoader: DashboardService.securityInsights,
      renderer: d_security_insights
    }));

    tasks.push(dashboard_section({
      force: force,
      key: DSECTIONS.BLAST_RADIUS,
      dbLoader: DashboardService.blastRadius,
      renderer: d_blast_radius
    }));

    if(force) {
      await sleep(500); 
    }
    await Promise.all(tasks);

  } catch (e) {
    alert("Error: " + e.message + "\n\nStack Trace:\n" + e.stack);
  }
}

function d_overview(d) {
  const vaults = d.total_vaults || 0;
  const reminders = d.total_reminders || 0;

  dgi("vm_total_vaults").textContent = vaults;
  dgi("vm_total_reminders").textContent = reminders;
  handle_promo_cards(vaults, reminders);
}

function makeProgressBar(used, total) {
  const pct = Math.min(100, Math.round((used / total) * 100));
  const left = Math.max(0, total - used);
  const isFull = used >= total;

  const wrap = document.createElement('div');
  wrap.className = 'vm_promo_progress_wrap';
  wrap.innerHTML = `<div class="vm_promo_progress_meta">
      <span>${used} of ${total} used</span>
      <span class="vm_promo_count ${isFull ? 'danger' : ''}">
        ${isFull ? 'None left' : left + ' left'}
      </span>
    </div>
    <div class="vm_promo_progress_track">
      <div class="vm_promo_progress_fill ${isFull ? 'vm_full' : ''}" style="width:${pct}%"></div>
    </div>`;
  return wrap;
}

async function handle_promo_cards(vaultCount, reminderCount) {
  const slider = dgi("vm_promo_slider");
  const track = dgi("vm_promo_track");
  const importCard = dgi("vm_import_card");
  const upgradeCard = dgi("vm_upgrade_card");
  const reminderCard = dgi("vm_reminder_card");
  if(!slider || !track || !importCard || !upgradeCard || !reminderCard) {
    return;
  }
  const isPremium = await isPremiumUser();     
  const showImport = vaultCount <= 2;

  let showUpgrade = false;
  if(!isPremium) {
    showUpgrade = true;
    const titleEl  = upgradeCard.querySelector(".vm_promo_title");
    const descEl   = upgradeCard.querySelector(".vm_promo_desc");
    const actionEl = upgradeCard.querySelector(".vm_promo_action");
    const vaultsLeft = Math.max(0, VAULT_LIMIT - vaultCount);

    actionEl.textContent = "Upgrade";
    if (vaultCount === 0) {
      titleEl.textContent = `Start with ${VAULT_LIMIT} secure vaults`;
      descEl.textContent = "Upgrade anytime for unlimited vaults";      
    } else if (vaultsLeft > 1) {
      titleEl.textContent = `${vaultsLeft} of ${VAULT_LIMIT} vaults left`;
      descEl.textContent = "Upgrade to keep adding without limits";
    } else if (vaultsLeft === 1) {
      titleEl.textContent = "Only 1 vault left";
      descEl.textContent = "Upgrade now to avoid interruptions";
    } else {
      titleEl.textContent = "Vault limit reached";
      descEl.textContent = "Upgrade to unlock unlimited vaults";
      actionEl.textContent = "Upgrade Now";
    }
  }

  let showReminder = false;
  if(!isPremium) {
    showReminder = true;
    const titleEl  = reminderCard.querySelector(".vm_promo_title");
    const descEl   = reminderCard.querySelector(".vm_promo_desc");
    const actionEl = reminderCard.querySelector(".vm_promo_action");
    const remindersLeft = Math.max(0, REMINDER_LIMIT - reminderCount);

    actionEl.textContent = "Upgrade";
    if(reminderCount === 0) {
      titleEl.textContent = `Get ${REMINDER_LIMIT} free reminders`;
      descEl.textContent = "Upgrade for unlimited reminders";      
    } else if (remindersLeft > 1) {
      titleEl.textContent = `${remindersLeft} of ${REMINDER_LIMIT} reminders left`;
      descEl.textContent = "Never miss a bill or renewal";
    } else if (remindersLeft === 1) {
      titleEl.textContent = "Only 1 reminder left";
      descEl.textContent = "Upgrade to keep alerts active";
    } else {
      titleEl.textContent = "Reminder limit reached";
      descEl.textContent = "Upgrade to unlock unlimited reminders";
      actionEl.textContent = "Upgrade Now";
    }
  }

  upgradeCard.querySelectorAll(".vm_promo_progress_wrap").forEach(el => el.remove());
  reminderCard.querySelectorAll(".vm_promo_progress_wrap").forEach(el => el.remove());

  upgradeCard.querySelector(".vm_promo_card_top").after(makeProgressBar(vaultCount, VAULT_LIMIT));
  reminderCard.querySelector(".vm_promo_card_top").after(makeProgressBar(reminderCount, REMINDER_LIMIT));

  importCard.classList.toggle("hide", !showImport);
  upgradeCard.classList.toggle("hide", !showUpgrade);
  reminderCard.classList.toggle("hide", !showReminder);

  const visibleCount = [showImport, showUpgrade, showReminder].filter(Boolean).length;
  slider.classList.toggle("hide", visibleCount === 0);
  track.classList.toggle("single", visibleCount === 1);

  const dotsContainer = dgi("vm_promo_dots");
  if(dotsContainer) {
    const visibleCards = [
      { show: showImport, el: importCard },
      { show: showUpgrade, el: upgradeCard },
      { show: showReminder, el: reminderCard },
    ].filter(c => c.show);

    dotsContainer.innerHTML = visibleCards.map((_, i) => `<div class="vm_promo_dot ${i === 0 ? 'active' : ''}" data-i="${i}"></div>`).join('');
    
    /*dotsContainer.querySelectorAll('.vm_promo_dot').forEach((dot, i) => {
      dot.addEventListener('click', () => {
        visibleCards[i].el.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
      });
    });*/

    if(dotsContainer) {
      dotsContainer.style.display = visibleCount <= 1 ? "none" : "flex";
    }

    track.addEventListener('scroll', () => {
      const scrollLeft = track.scrollLeft;
      
      //const cardWidth  = track.offsetWidth * 0.80 + 12; // 80% + gap
      const firstCard = track.querySelector(".vm_promo_slide");
      const cardWidth = firstCard.offsetWidth + 12;


      const idx = Math.min(Math.round(scrollLeft / cardWidth), visibleCards.length - 1);
      dotsContainer.querySelectorAll('.vm_promo_dot').forEach((d, i) => {
        d.classList.toggle('active', i === idx);
      });
    }, { passive: true });



    
  }
}


function d_pin_fav(list) {
  const container_vault = dgi("vm_pin_fav_vaults"); 
  const container_empty = dgi("vm_pin_fav_empty"); 
  const container_footer = dgi("vm_pin_fav_footer");

  if(!Array.isArray(list) || list.length === 0) {
    container_empty.innerHTML = renderEmptyState({
      icon: "fas fa-thumbtack",
      title: "No pinned or favorite vaults",
      desc: "Pin or favorite important vaults for quick access.",
      buttonText: "Create first vault",      
      className: "vm-sheet-open",
      dataTarget:"vm_bottomSheet"
    });
    container_empty.classList.remove("hide");
    container_vault.classList.add("hide");    
    container_footer.classList.add("hide");
    return;
  }  

  container_vault.innerHTML = "";  
  list.forEach(v => {
    container_vault.insertAdjacentHTML("beforeend", vault_item_html(v));
  });

  container_empty.classList.add("hide"); 
  container_vault.classList.remove("hide"); 
  list.length >= VaultMateConfig.default.limit_pinfav && container_footer.classList.remove("hide");
}

function d_recentused(list) {
  const container_vault = dgi("vm_recently_used_vaults");  
  const container_empty = dgi("vm_recently_used_empty");
  const container_footer = dgi("vm_recently_used_footer");

  if(!Array.isArray(list) || list.length === 0) {
    container_empty.innerHTML = renderEmptyState({
      icon: "fas fa-clock",
      title: "No recent used vaults",
      desc: "Vaults you interact with will appear here.",
      buttonText: "Create first vault",
      className: "vm-sheet-open",
      dataTarget:"vm_bottomSheet"
    });
    container_empty.classList.remove("hide");
    container_vault.classList.add("hide");    
    container_footer.classList.add("hide");
    return;
  }  
  container_vault.innerHTML = "";
  list.forEach(v => {
    container_vault.insertAdjacentHTML("beforeend", vault_item_html(v));
  });

  container_empty.classList.add("hide"); 
  container_vault.classList.remove("hide");   
  list.length >= VaultMateConfig.default.limit_recent && container_footer.classList.remove("hide");
}

function d_mostused(list) {
  const container_vault = dgi("vm_top_vaults");  
  const container_empty = dgi("vm_top_empty"); 
  const container_footer = dgi("top_vault_btn_wrapper");   

  if(!Array.isArray(list) || list.length === 0) {
    container_empty.innerHTML = renderEmptyState({
      icon: "fas fa-chart-line",
      title: "No frequently used vaults yet",
      desc: "Start using your vaults regularly to see them here",
      buttonText: "Start using vaults",
      className: "vm-sheet-open",
      dataTarget:"vm_bottomSheet"
    });
    container_empty.classList.remove("hide");
    container_vault.classList.add("hide");    
    container_footer.classList.add("hide");
    return;
  }  
  container_vault.innerHTML = "";
  list.forEach(v => {
    container_vault.insertAdjacentHTML("beforeend", vault_item_html(v));
  });
  container_empty.classList.add("hide"); 
  container_vault.classList.remove("hide");   
  list.length >= VaultMateConfig.default.limit_top && container_footer.classList.remove("hide");
}

function d_categories(list) {
  const container_vault = dgi("vm_dash_categories");  
  const container_empty = dgi("vm_dash_categories_emp"); 

  if(!Array.isArray(list) || list.length === 0) { 
     let empty_html = renderEmptyState({
      icon: "fas fa-shield-alt",
      title: "No vaults yet",
      desc: "Create vaults to organize passwords and documents by category",
      buttonText: "Create first vault",
      className: "vm-sheet-open",
      dataTarget:"vm_bottomSheet"
    });  
    container_empty.innerHTML = `${empty_html}`;
    container_empty.classList.remove("hide");
    container_vault.classList.add("hide"); 
    return;
  }

  container_vault.innerHTML = ""; 
  list.forEach(c => {
    const cfg = vault_category_map[c.slug];
    if(!cfg) {
      return;
    }
    container_vault.insertAdjacentHTML("beforeend", `
      <div class="card" onclick="redirect_to(1, 'categories|${c.slug}')">
        <div class="card-icon"><i class="fa ${cfg.icon}"></i></div>
        <div class="card-title">${c.c}</span></div>
        <div class="card-desc">${cfg.name}</div>
      </div>
    `);    
  });
  container_empty.classList.add("hide");
  container_vault.classList.remove("hide");    
}

function toggleRowState(id, count) {
  const el = dgi(id).closest(".vm_attention_item");
  el.classList.toggle("active_issue", count > 0);
}
function d_attention(a) {

  const weak = 0; //a.weak_passwords || 0;
  const missing = a.vaults_missing_reminders || 0;
  const due = a.due_reminders || 0;
  const critical = 0; //a.critical_passwords || 0;
  const expired = a.expired_items || 0;

  const total = weak + missing + due + critical + expired;

  //dgi("att_weak_pwd").textContent = weak;
  dgi("att_missing_rem").textContent = missing;
  dgi("att_due_rem").textContent = due;
  //dgi("att_critical_pwd").textContent = critical;
  dgi("att_expired").textContent = expired;

  //dgi("att_weak_pwd_desc").textContent = weak > 0 ? "Passwords need attention" : "All passwords are safe";
  dgi("att_missing_rem_desc").textContent = missing > 0 ? "Reminders not set" : "All reminders configured";
  dgi("att_due_rem_desc").textContent = due > 0 ? "Coming up soon" : "Nothing scheduled";
  //dgi("att_critical_pwd_desc").textContent = critical > 0 ? "Can be cracked instantly" : "No critical risks";
  dgi("att_expired_desc").textContent = expired > 0 ? "Some documents need renewal" : "All documents up to date";

  //toggleRowState("att_critical_pwd", critical);
  //toggleRowState("att_weak_pwd", weak);
  toggleRowState("att_missing_rem", missing);
  toggleRowState("att_due_rem", due);
  toggleRowState("att_expired", expired);

  const box = dgi("vm_attention");
  const icon = dgi("vm_attention_icon");
  const title = dgi("vm_attention_title");

  box.classList.toggle("success", total === 0);
  box.classList.toggle("warning", total > 0);

  if(total === 0) {
    icon.className = "fa fa-check-circle";
    title.textContent = "All clear";
  } else {
    icon.className = "fa fa-exclamation-triangle";
    title.innerHTML = `<span id="vm_attention_count">${total}</span> Items to Review`;
  }
}

function d_reminder_stats(r) {
  if (!r) return;

  const completed = r.completed || 0;
  const upcoming = r.upcoming || 0;
  //const overdue = r.overdue || 0;
  const total = completed + upcoming; // + overdue;
  const container = dgi("vm_reminders_container");
  const container_empty = dgi("vm_reminders_empty");

  if(total === 0) {
    container_empty.innerHTML = renderEmptyState({
      icon: "far fa-bell",
      title: "No reminders yet",
      desc: "Create reminders to never miss important dates",
      buttonText: "Add reminder",
      className: "vm-sheet-open",
      dataTarget:"vm_remainder_bottom_sheet"
    });
    container_empty.classList.remove("hide");
    container.classList.add("hide"); 
    return;
  }

  dgi("rs_completed").textContent = completed;
  dgi("rs_upcoming").textContent = upcoming;
  //dgi("rs_overdue").textContent = overdue;

  dgi("rs_today").textContent = r.today || 0;
  dgi("rs_week").textContent = r.this_week || 0;
  dgi("rs_month").textContent = r.this_month || 0;

  const percent = total > 0 ? Math.round((completed / total) * 100) : 0;
  const bar = dgi("vm_vault_dash_reminder_progress_bar");
  const label = dgi("vm_reminder_progress_percent");

  bar.style.width = percent + "%";
  bar.classList.remove("progress-good", "progress-warn", "progress-bad");

  if(label) {
    label.textContent = percent + "%";
  }

  if(percent < 40) {
    bar.classList.add("progress-bad");
  } else if(percent < 70) {
    bar.classList.add("progress-warn");
  } else {
    bar.classList.add("progress-good");
  }

  container_empty.classList.add("hide");
  container.classList.remove("hide");     
}

function d_up_reminder(list) {
  const container = dgi("next_reminder_item");
  const container_empty = dgi("next_reminder_empty");
  const container_footer = dgi("next_reminder_footer");

  container.innerHTML = "";

  if(!list || !list.length) {
    container_empty.innerHTML = renderEmptyState({
      icon: "far fa-calendar-check",
      title: "No upcoming reminders",
      desc: "You're all caught up"
    });
    container_empty.classList.remove("hide");
    container.classList.add("hide"); 
    container_footer.classList.add("hide"); 
    return;
  }
  
  list.forEach(r => {
    container.insertAdjacentHTML("beforeend", rm_item_html(r));
  });

  container_empty.classList.add("hide");
  container.classList.remove("hide"); 
  list.length >= VaultMateConfig.default.limit_upcoming && container_footer.classList.remove("hide");
}



function d_password_health(p) {

  const weak = p.weak || 0;
  const medium = p.medium || 0;
  const strong = p.strong || 0;
  const overall = p.overall || 0;

  const total = weak + medium + strong;

  const container = dgi("vm_ph_new");
  const empty = dgi("vm_ph_new_empty");

  if (total === 0) {
    empty.innerHTML = renderEmptyState({
      icon: "fas fa-lock",
      title: "No passwords yet",
      desc: "Add passwords to analyze their strength",
      buttonText: "Add password",
      className: "vm-sheet-open",
      dataTarget: "vm_bottomSheet"
    });

    empty.classList.remove("hide");
    container.classList.add("hide");
    return;
  }

  empty.classList.add("hide");
  container.classList.remove("hide");

  // Counts
  dgi("ph_weak").textContent = weak;
  dgi("ph_medium").textContent = medium;
  dgi("ph_strong").textContent = strong;

  // Score
  //dgi("ph_score").textContent = overall + "%";

  //const status = get_password_status(overall);
  //dgi("ph_score").textContent = status.label;

  const scoreEl = dgi("ph_score");
  const status = get_password_status(overall);

  scoreEl.textContent = status.label;
  scoreEl.style.color = status.color;
  
  // Bar
  const bar = dgi("ph_bar");
  bar.style.width = overall + "%";

  if (overall >= 70) {
    bar.style.background = "linear-gradient(90deg,#22c55e,#4ade80)";
  } else if (overall >= 40) {
    bar.style.background = "linear-gradient(90deg,#f59e0b,#facc15)";
  } else {
    bar.style.background = "linear-gradient(90deg,#ef4444,#f97316)";
  }

  // Insight
  let insight = "";
  if (weak > 0) {
    insight = `${weak} passwords need attention`;
  } else if (medium > 0) {
    insight = "Some passwords can be improved";
  } else {
    insight = "All passwords are secure";
  }

  dgi("ph_insight").textContent = insight;

  // Action
  const actionWrap = dgi("ph_action");

  let actionText = "";
  let actionFilter = "";

  if (weak > 0) {
    actionText = "Fix Weak Passwords";
    actionFilter = "weakPassword|1";
  } else if (medium > 0) {
    actionText = "Improve Passwords";
    actionFilter = "weakPassword|2";
  }

  if (actionText) {
    actionWrap.innerHTML = `
      <button class="vm_vault_dash_view_all_btn"
        onclick="redirect_to(1, '${actionFilter}')">
        ${actionText}
      </button>
    `;
  } else {
    actionWrap.innerHTML = "";
  }
}

function calculate_backup_coverage(total, delta) {
  const notBackedUp = delta.newVaults + delta.updatedVaults + delta.newReminders + delta.updatedReminders;
  if(total === 0) {
    return 100;
  }
  const covered = total - notBackedUp;
  return Math.max(0, Math.round((covered / total) * 100));
}

async function get_backup_delta_counts(lastBackupTime) {

  if(_MODE_ === "dev") {
    const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
    return {
      newVaults: rand(0, 2),
      updatedVaults: rand(0, 2),
      newReminders: rand(0, 2),
      updatedReminders: rand(0, 2)
    };
  }

  const result = {
    newVaults: 0,
    updatedVaults: 0,
    newReminders: 0,
    updatedReminders: 0
  };

  // Vaults
  const v = await VaultDB.dbExecute(`SELECT 
    SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) AS newVaults,
    SUM(CASE WHEN updated_at > ? AND created_at <= ? THEN 1 ELSE 0 END) AS updatedVaults
  FROM ${VaultMateConfig.tables.vaults}`, [lastBackupTime, lastBackupTime, lastBackupTime]);

  const vr = v.rows.item(0);
  result.newVaults = vr.newVaults || 0;
  result.updatedVaults = vr.updatedVaults || 0;

  // Reminders
  const r = await VaultDB.dbExecute(`SELECT 
    SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) AS newReminders,
    SUM(CASE WHEN updated_at > ? AND created_at <= ? THEN 1 ELSE 0 END) AS updatedReminders
  FROM ${VaultMateConfig.tables.reminders}`, [lastBackupTime, lastBackupTime, lastBackupTime]);

  const rr = r.rows.item(0);
  result.newReminders = rr.newReminders || 0;
  result.updatedReminders = rr.updatedReminders || 0;

  return result;
}


function build_backup_sentences(vNew, vUpdated, rNew, rUpdated) {
  let html = "";

  function formatText(count, label) {
    return `${count} ${label}${count > 1 ? "s need" : " needs"} backup`;
  }

  function row(icon, text) {
    return `
      <div class="list-item">
        <div class="list-icon">
          <i class="${icon}"></i>
        </div>
        <div class="list-content">
          <div class="vm-list-title">
            ${text}
          </div>
        </div>
      </div>
    `;
  }

  if(vNew > 0) {
    html += row("fas fa-shield-alt", formatText(vNew, "new vault"));
  }

  if(vUpdated > 0) {
    html += row("fas fa-pen", formatText(vUpdated, "updated vault"));
  }

  if(rNew > 0) {
    html += row("fas fa-bell", formatText(rNew, "new reminder"));
  }

  if(rUpdated > 0) {
    html += row("fas fa-pen", formatText(rUpdated, "updated reminder"));
  }

  return html;
}

/*async function d_backup_status(data) {
  const container = dgi("vm_backup_container");
  const empty = dgi("vm_backup_empty");
 
  if(!data || !data.backup_time) {
    empty.innerHTML = renderEmptyState({
      icon:"fas fa-cloud-upload-alt",
      title:"No backup found",
      desc:"Take a backup to keep your vault data safe",
      buttonText:"Backup now",
      onClick:"do_backup()"
    });
    empty.classList.remove("hide");
    container.classList.add("hide");
    return;
  }

  const ageMs = Date.now() - data.backup_time;
  const ageDays = Math.floor(ageMs / 86400000);
  const ageHrs = Math.floor(ageMs / 3600000); 
  let state, statusText, message, actionHtml;
 
  if(ageDays <= 3) {
    state = "success";
    statusText = ageDays === 0 ? (ageHrs === 0 ? "Backed up just now" : `Backed up ${ageHrs}h ago`) : `Backed up ${ageDays} day${ageDays > 1 ? "s" : ""} ago`;
    message = "Recent backup. Back up again after changes.";
    actionHtml = `<button class="vm_vault_dash_view_all_btn" onclick="do_backup()">Backup again</button>`; 
  } else if (ageDays <= 14) {
    state = "warning";
    statusText = `Last backup was ${ageDays} days ago`;
    message = "Your backup is getting old. Consider taking a fresh backup.";
    actionHtml = `<button class="vm_backup_btn" onclick="do_backup()">Backup now</button>`; 
  } else {
    state = "error";
    statusText = ageDays < 365 ? `Last backup was ${ageDays} days ago` : `Last backup was over a year ago`;
    message = "Backup is outdated. Take a backup to avoid data loss.";
    actionHtml = `<button class="vm_backup_btn" onclick="do_backup()">Backup now</button>`;
  }

  const iconMap = {
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-circle",
    error:   "fas fa-times-circle"
  };
 
  const statusEl = dgi("vm_backup_status");
  statusEl.className = `vm_backup_status ${state}`;
  dgi("vm_backup_status_icon").className = iconMap[state];
  dgi("vm_backup_status_text").textContent = statusText;

  const delta = await get_backup_delta_counts(data.backup_time);
  //vm_log("delta", delta)

  const newVaults = delta.newVaults || 0;
  const updatedVaults = delta.updatedVaults || 0;
  const newReminders = delta.newReminders || 0;
  const updatedReminders = delta.updatedReminders || 0;

  // dgi("vm_new_vaults").textContent = newVaults;
  // dgi("vm_updated_vaults").textContent = updatedVaults;
  // dgi("vm_new_reminders").textContent = newReminders;
  // dgi("vm_updated_reminders").textContent = updatedReminders;

  const total_changes = newVaults + updatedVaults + newReminders + updatedReminders;

  const summaryEl = dgi("vm_backup_summary");
  summaryEl.innerHTML = build_backup_sentences(newVaults, updatedVaults, newReminders, updatedReminders);
  
  const sections = document.querySelectorAll(".backup_new_stats_section");
  if(total_changes === 0) {
    sections.forEach(el => el.classList.add("hide"));
    actionHtml = "";
  } else {
    sections.forEach(el => el.classList.remove("hide"));
  }

  let totalVaults = parseInt(dgi("vm_total_vaults").textContent) || 0;
  let totalReminders = parseInt(dgi("vm_total_reminders").textContent) || 0;

  const totalItems = totalVaults + totalReminders;
  let coverage = calculate_backup_coverage(totalItems, delta);
  //vm_log(coverage, totalItems)

  if(totalItems === 0) {
    coverage = 100;
  }
  dgi("vm_backup_percent").textContent = coverage + "%";

  const bar = dgi("vm_backup_progress_bar");
  bar.style.width = coverage + "%";
  bar.classList.remove("progress-good", "progress-warn", "progress-bad");

  if(coverage >= 90) {
    bar.classList.add("progress-good");
  } else if(coverage >= 60) {
    bar.classList.add("progress-warn");
  } else {
    bar.classList.add("progress-bad");
  }

  if(totalItems === 0) {
    message = "No data yet. Add vaults and reminders to protect them.";
  } else if(total_changes === 0) {
    message = "All changes are included in your last backup.";
  } else if(coverage < 70) {
    message = "Some data is not backed up. Take a backup now.";
  }
  dgi("vm_backup_message").textContent = message;

  //const shareHtml = data.backup_url ? `<button class="vm_vault_dash_view_all_btn" onclick="share_backup('${data.backup_url}')">Share</button>` : "";
  //dgi("vm_backup_action").innerHTML = actionHtml + shareHtml; 

  let backupActions = "";
  if(data.backup_url) {
      backupActions = `<button class="vm_vault_dash_view_all_btn" onclick="save_backup_to_device('${data.backup_url}')">Save</button>
      <button class="vm_vault_dash_view_all_btn" onclick="share_backup('${data.backup_url}')">Share</button>`;
  }
  dgi("vm_backup_action").innerHTML = actionHtml + backupActions;
  empty.classList.add("hide");
  container.classList.remove("hide");
}*/

async function d_backup_status(data) {
  const container = dgi("vm_backup_container");
  const empty = dgi("vm_backup_empty");

  let bgStatus;
  if(_MODE_ == "production") {
    bgStatus = await new Promise((resolve) => {
      MahaBackgroundBackup.getStatus(resolve, () => resolve(null));
    });
  }

  const manualTime = (data && data.backup_time) ? data.backup_time : 0;
  const bgTime = (bgStatus && bgStatus.lastBackupTime) ? bgStatus.lastBackupTime : 0;
  const usingBackground = bgTime > manualTime;
  const effectiveTime = usingBackground ? bgTime : manualTime;
  const bgFailed = usingBackground && bgStatus.lastBackupStatus === "failed";

  if(!effectiveTime || bgFailed) {
    empty.innerHTML = renderEmptyState({
      icon: bgFailed ? "fas fa-times-circle" : "fas fa-cloud-upload-alt",
      title: bgFailed ? "Last background backup failed" : "No backup found",
      desc: bgFailed ? "Open the app and try creating a backup manually." : "Take a backup to keep your vault data safe",
      buttonText: "Backup now",
      onClick:"do_backup()"
    });
    empty.classList.remove("hide");
    container.classList.add("hide");
    return;
  }

  const ageMs = Date.now() - effectiveTime;
  const ageDays = Math.floor(ageMs / 86400000);
  const ageHrs = Math.floor(ageMs / 3600000); 
  let state, statusText, message, actionHtml;
 
  if(ageDays <= 3) {
    state = "success";
    statusText = ageDays === 0 ? (ageHrs === 0 ? "Backed up just now" : `Backed up ${ageHrs}h ago`) : `Backed up ${ageDays} day${ageDays > 1 ? "s" : ""} ago`;
    message = usingBackground ? "Your vault was backed up automatically." : "Recent backup. Back up again after changes.";
    actionHtml = `<button class="vm_vault_dash_view_all_btn" onclick="do_backup()">Backup again</button>`; 
  } else if (ageDays <= 14) {
    state = "warning";
    statusText = `Last backup was ${ageDays} days ago`;
    message = "Your backup is getting old. Consider taking a fresh backup.";
    actionHtml = `<button class="vm_backup_btn" onclick="do_backup()">Backup now</button>`; 
  } else {
    state = "error";
    statusText = ageDays < 365 ? `Last backup was ${ageDays} days ago` : `Last backup was over a year ago`;
    message = "Backup is outdated. Take a backup to avoid data loss.";
    actionHtml = `<button class="vm_backup_btn" onclick="do_backup()">Backup now</button>`;
  }

  const iconMap = {
    success: "fas fa-check-circle",
    warning: "fas fa-exclamation-circle",
    error:   "fas fa-times-circle"
  };
 
  const statusEl = dgi("vm_backup_status");
  statusEl.className = `vm_backup_status ${state}`;
  dgi("vm_backup_status_icon").className = iconMap[state];
  dgi("vm_backup_status_text").textContent = statusText;

  const delta = await get_backup_delta_counts(effectiveTime);

  const newVaults = delta.newVaults || 0;
  const updatedVaults = delta.updatedVaults || 0;
  const newReminders = delta.newReminders || 0;
  const updatedReminders = delta.updatedReminders || 0;

  const total_changes = newVaults + updatedVaults + newReminders + updatedReminders;

  const summaryEl = dgi("vm_backup_summary");
  summaryEl.innerHTML = build_backup_sentences(newVaults, updatedVaults, newReminders, updatedReminders);
  
  const sections = document.querySelectorAll(".backup_new_stats_section");
  if(total_changes === 0) {
    sections.forEach(el => el.classList.add("hide"));
    actionHtml = "";
  } else {
    sections.forEach(el => el.classList.remove("hide"));
  }

  let totalVaults = parseInt(dgi("vm_total_vaults").textContent) || 0;
  let totalReminders = parseInt(dgi("vm_total_reminders").textContent) || 0;

  const totalItems = totalVaults + totalReminders;
  let coverage = calculate_backup_coverage(totalItems, delta);

  if(totalItems === 0) {
    coverage = 100;
  }
  dgi("vm_backup_percent").textContent = coverage + "%";

  const bar = dgi("vm_backup_progress_bar");
  bar.style.width = coverage + "%";
  bar.classList.remove("progress-good", "progress-warn", "progress-bad");

  if(coverage >= 90) {
    bar.classList.add("progress-good");
  } else if(coverage >= 60) {
    bar.classList.add("progress-warn");
  } else {
    bar.classList.add("progress-bad");
  }

  if(totalItems === 0) {
    message = "No data yet. Add vaults and reminders to protect them.";
  } else if(total_changes === 0) {
    message = usingBackground ? "Your vault was backed up automatically." : "All changes are included in your last backup.";
  } else if(coverage < 70) {
    message = "Some data is not backed up. Take a backup now.";
  }
  dgi("vm_backup_message").textContent = message;

  // Save/Share only act on the manual JS-created local file
  // (data.backup_url). If the most recent backup was a background
  // one, or a folder is already configured (meaning the next manual
  // backup will auto-save there too), skip the redundant Save
  // button — Share still makes sense independently, as long as a
  // local file actually exists to share.
  let backupActions = "";
  if(data && data.backup_url) {
    backupActions = `<button class="vm_vault_dash_view_all_btn" onclick="save_backup_to_device('${data.backup_url}')">Save</button>
    <button class="vm_vault_dash_view_all_btn" onclick="share_backup('${data.backup_url}')">Share</button>`;
  }
  dgi("vm_backup_action").innerHTML = actionHtml + backupActions;
  empty.classList.add("hide");
  container.classList.remove("hide");
}


function d_blast_radius(list) {
  const container = dgi("vm_blast_container");
  const empty = dgi("vm_blast_empty");

  if(!list || list.length === 0) {
    empty.innerHTML = renderEmptyState({
      icon: "fas fa-shield-alt",
      title: "No reused passwords",
      desc: "Your passwords are unique"
    });
    empty.classList.remove("hide");
    container.classList.add("hide");
    return;
  }

  empty.classList.add("hide");
  container.classList.remove("hide");

  const top = list[0];
  const others = list.slice(1);
  const lockIcon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ba7517" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>`;

  const getBlastText = (count) => {
    if(count <= 2) return "Reused password";
    if(count <= 5) return "Frequently reused";
    return "Used in the most accounts";
  };

  const otherRows = others.map(i => `
    <div class="vm_blast_list_item" onclick="redirect_to(1, 'hashPassword|${i.hash}')">
      <div class="vm_blast_item_icon">${lockIcon}</div>
      <div class="vm_blast_item_body">
        <div class="vm_blast_item_pwd">${i.masked}</div>
        <div class="vm_blast_item_sub">Reused password</div>
      </div>
      <div class="vm_blast_item_count">${i.total} accounts</div>
    </div>
  `).join("");

  container.innerHTML = `
    <div class="vm_blast_card">
      <div class="vm_blast_hero">
        <div class="vm_blast_risk_badge">
          <div class="vm_blast_risk_dot"></div>
          ${getExposure(top.total)}
        </div>
        <div class="vm_blast_hero_pwd">${top.masked}</div>
        <div class="vm_blast_hero_meta">
          <div class="vm_blast_hero_desc">${getBlastText(top.total)}</div>
          <div class="vm_blast_account_pill">${top.total} accounts</div>
        </div>
        <button class="vm_blast_fix_btn" onclick="redirect_to(1, 'hashPassword|${top.hash}')">
          Fix this password
        </button>
      </div>
      <div>${otherRows}</div>
    </div>
  `;
}

function getExposure(count) {
    if (count >= 15) return "Critical Exposure";
    if (count >= 10) return "High Exposure";
    if (count >= 5) return "Medium Exposure";
    return "Low Exposure";
}

function d_security_insights(data) {
  const container = dgi("vm_si_list");
  const headerScore = dgi("vm_si_score");
  const headerStatus = dgi("vm_si_status");
  
  //const summary = dgi("vm_si_summary");

  const empty = dgi("vm_si_empty");
  const wrapper = document.querySelector('[data-item="dashboard.security_insights"]');

  if(!data) {
    return;
  }

  //console.log("d_security_insights data", data);

  const total = data.total || 0;
  if (total === 0) {
   
    dgi("vm_si_score").closest(".vm_si_header")?.classList.add("hide");
    //dgi("vm_si_summary")?.classList.add("hide");

    container.innerHTML = `
      <div class="vm_empty_state">
        <i class="fas fa-lock vm_empty_icon"></i>
        <div class="vm_empty_title">No passwords to analyze</div>
        <div class="vm_empty_sub">Add your first vault to see security insights</div>
      </div>
    `;
    return;
  } else {
    dgi("vm_si_score").closest(".vm_si_header")?.classList.remove("hide");
    //dgi("vm_si_summary")?.classList.remove("hide");
  }

  const score = Math.round(data.avg_score || 0);
  const status = get_password_status(score);
  headerScore.textContent = score + "%";
  headerStatus.textContent = status.label;

  headerScore.style.color = status.color;
  headerStatus.style.color = status.color;

  /*summary.innerHTML = `
    <div onclick="redirect_to(1, 'security|reused')"><i class="fas fa-sync"></i> ${data.reused} Reused</div>
    <div onclick="redirect_to(1, 'security|crackable')"><i class="fas fa-bolt"></i> ${data.crackable} High Risk</div>
    <div onclick="redirect_to(1, 'security|predictable')"><i class="fas fa-brain"></i> ${data.predictable} Predictable</div>
    <div onclick="redirect_to(1, 'security|old')"><i class="fas fa-clock"></i> ${data.old} Old</div>
  `;*/

  const insights = [];

  if (data.crackable > 0) {
    insights.push({
      icon: "fas fa-bolt",
      title: "Can be cracked quickly",
      desc: `${data.crackable} accounts need stronger passwords`,
      action: "redirect_to(1, 'security|crackable')",
      actionText: "Fix",
      iconWrap:"si-red",
    });
  }

  if (data.reused > 0) {
    insights.push({
      icon: "fas fa-sync",
      title: "Reused Passwords",
      desc: `${data.reused} accounts reuse passwords`,
      action: "redirect_to(1, 'security|reused')",
      actionText: "Review",
      iconWrap:"si-orange",
    });
  }

  if (data.predictable > 0) {
    insights.push({
      icon: "far fa-lightbulb",
      title: "Predictable Passwords",
      desc: `${data.predictable} accounts use common password patterns`,
      action: "redirect_to(1, 'security|predictable')",
      actionText: "Improve",
      iconWrap:"si-blue",
    });
  }

  if(data.old > 0) {
    insights.push({
      icon: "far fa-clock",
      title: "Outdated Passwords",
      desc: `${data.old} passwords are over 90 days old`,
      action: "redirect_to(1, 'security|old')",
      actionText: "Update",
      iconWrap:"si-gray",
    });
  }

  if(insights.length === 0) {
    container.innerHTML = `
      <div class="vm_empty_state">
        <i class="fas fa-check-circle vm_empty_icon"></i>
        <div class="vm_empty_title">Your passwords look secure</div>
        <div class="vm_empty_sub">No major risks detected</div>
      </div>
    `;
    return;
  }

  container.innerHTML = insights.map(i => `
    <div class="list-item" onclick="${i.action}">
      <div class="list-icon">
        <div class="vm_si_icon_wrap ${i.iconWrap}">
          <i class="${i.icon}"></i>
        </div>
      </div>
      <div class="list-content">
        <div class="vm-list-title">${i.title}</div>
        <div class="list-subtitle">${i.desc}</div>
      </div>      
      <div class="vm_si_action_btn">${i.actionText}</div>
    </div>
  `).join("");
}

const DashboardService = (function () {
  const now = () => Date.now();

  const todayRange = () => {
    const d = new Date();
    d.setHours(0,0,0,0);
    const start = d.getTime();
    return { start, end: start + (86400 * 1000) };
  };

  const weekRange = () => {
    const d = new Date();
    const day = d.getDay() || 7;
    d.setDate(d.getDate() - day + 1);
    d.setHours(0,0,0,0);    
    const start = d.getTime();
    return { start, end: start + (7 * 86400 * 1000) };
  };

  const monthRange = () => {
    const d = new Date();
    const startDate = new Date(d.getFullYear(), d.getMonth(), 1, 0, 0, 0, 0);
    const endDate = new Date(d.getFullYear(), d.getMonth() + 1, 1, 0, 0, 0, 0);
    return {
      start: startDate.getTime(),
      end: endDate.getTime()
    };
  };

  let tbl_va = VaultMateConfig.tables.vaults;
  let tbl_rm = VaultMateConfig.tables.reminders;

  async function overview() {
    return {
      total_vaults: await scalar(
        VaultDB.buildSelect({
          table: tbl_va,
          columns: ["count(*) c"],
          where: { status: 1 }
        })
      ),
      total_reminders: await scalar(
        VaultDB.buildSelect({
          table: tbl_rm,
          columns: ["count(*) c"]
        })
      )
    };
  }

  async function topVaults() {
    return rows(
      VaultDB.buildSelect({
        table: tbl_va,
        columns: ["*"],
        where: "status = 1 and usage_score > "+VaultMateConfig.default.min_usage_score,
        orderBy: [{col:"usage_score", dir: "DESC"}],
        limit: VaultMateConfig.default.limit_top
      })
    );
  }

  async function pinfavVaults() {
    return rows(VaultDB.buildSelect({
        table: tbl_va,
        columns: ["*"],
        where: "(is_pinned = 1 OR is_favorite = 1) and status = 1",
        orderBy: [
          {col: `CASE WHEN is_pinned = 1 THEN 1 WHEN is_favorite = 1 THEN 2 ELSE 3 END`, dir: "ASC", raw: true},
          {col: "COALESCE(usage_score, 0)", dir: "DESC", raw: true}
        ],
        limit: VaultMateConfig.default.limit_pinfav
      })
    );
  }

  async function recentVaults() {
    return rows(VaultDB.buildSelect({
        table: tbl_va,
        columns: ["*"],
        where: "status = 1 AND last_used_at IS NOT NULL",
        orderBy: [{col: "last_used_at", dir: "DESC"}, {col: "updated_at", dir: "DESC"}],
        limit:VaultMateConfig.default.limit_recent
      })
    );
  }

  async function topCategories() {
    return rows(
      VaultDB.buildSelect({
        table: tbl_va,
        columns: ["slug", "count(*) c"],
        where: { status: 1 },
        groupBy: ["slug"],
        orderBy: [{ col: "c", dir: "desc", raw: true }],
        limit: VaultMateConfig.default.limit_categories
      })
    );
  }

  async function reminderStats() {

    if(_MODE_ == "dev") {
      const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;
      return {
        all: rand(0, 1),
        completed: rand(0, 1),
        upcoming: rand(0, 1),
        today: rand(0, 1),
        this_week: rand(0, 1),
        this_month: rand(0, 1)
      };
    }

    const t = now();
    const today = todayRange();
    const week = weekRange();
    const month = monthRange();

    const sql = `SELECT
      COUNT(*) AS all_count,
      SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS completed,
      SUM(CASE WHEN status = 1 AND next_trigger_at >= ? THEN 1 ELSE 0 END) AS upcoming,
      SUM(CASE WHEN status = 1 AND next_trigger_at >= ? AND next_trigger_at < ? THEN 1 ELSE 0 END) AS today,
      SUM(CASE WHEN status = 1 AND next_trigger_at >= ? AND next_trigger_at < ? THEN 1 ELSE 0 END) AS this_week,
      SUM(CASE WHEN status = 1 AND next_trigger_at >= ? AND next_trigger_at < ? THEN 1 ELSE 0 END) AS this_month
      FROM ${tbl_rm}`;

    const values = [
        t,
        today.start, today.end,
        week.start, week.end,
        month.start, month.end
    ];
    const res = await VaultDB.dbExecute(sql, values);
    const row = res.rows.item(0);

    return {
        all: row.all_count || 0,
        completed: row.completed || 0,
        upcoming: row.upcoming || 0,
        today: row.today || 0,
        this_week: row.this_week || 0,
        this_month: row.this_month || 0
    };
  }

  async function nextReminders() {
    const t = now();
    return rows(
      VaultDB.buildSelect({
        table: tbl_rm,
        columns: ["*"],
        where: "status = 1 AND next_trigger_at >= " + t,
        orderBy: [{ col: "next_trigger_at", dir: "ASC" }],
        limit: VaultMateConfig.default.limit_upcoming
      })
    );
  }

  async function passwordHealth() {
    const q = VaultDB.buildSelect({
      table: tbl_va,
      columns: [
        "SUM(password_score < 40) weak",
        "SUM(password_score BETWEEN 40 AND 70) medium",
        "SUM(password_score > 70) strong",
        "ROUND(AVG(password_score)) overall"
      ],
      where: "status = 1 AND password_score > 0"
    });

    
    if(_MODE_ === "dev") {
      return {
        weak: 20,
        medium: 15,
        strong: 30,
        overall: 68
      };
    }   

    const res = await VaultDB.dbExecute(q.sql, q.values);
    let row = res.rows.length ? res.rows.item(0) : {};

    return {
      weak: row.weak || 0,
      medium: row.medium || 0,
      strong: row.strong || 0,
      overall: row.overall || 0
    };
  }

  async function attention() {

    if(_MODE_ === "dev") {
      return {
        //weak_passwords: 0,
        due_reminders: 2,
        vaults_missing_reminders: 2,
        //critical_passwords:10,
        expired_items:3,
      };
    }

    const now = Date.now();
    const next30Days = now + (30 * 24 * 60 * 60 * 1000);
    const reminderSlugs = reminderRequiredSlugs();
    const placeholders = reminderSlugs.map(() => "?").join(",");

    /*const sqlVault = `SELECT
      SUM(CASE WHEN v.status = 1 AND v.crack_seconds IS NOT NULL AND v.crack_seconds < 300 THEN 1 ELSE 0 END) AS critical_passwords,
      SUM(CASE 
        WHEN v.status = 1 
          AND v.rem_expiry IS NOT NULL 
          AND v.rem_expiry > 0
          AND v.rem_expiry < ?
        THEN 1 
        ELSE 0 
      END) AS expired_items,
      SUM(CASE WHEN v.status = 1 AND v.password_score > 0 AND v.password_score < ? THEN 1 ELSE 0 END) AS weak_passwords,
      SUM(CASE WHEN v.status = 1 AND v.slug IN (${placeholders}) AND v.rem_expiry IS NOT NULL AND v.rem_enabled = 0 THEN 1 ELSE 0 END) AS vaults_missing_reminders
    FROM ${tbl_va} v`;*/

    const sqlVault = `SELECT
      SUM(CASE
        WHEN v.status = 1
          AND v.rem_expiry IS NOT NULL
          AND v.rem_expiry > 0
          AND v.rem_expiry < ?
        THEN 1
        ELSE 0
      END) AS expired_items,

      SUM(CASE
        WHEN v.status = 1
          AND v.slug IN (${placeholders})
          AND v.rem_expiry IS NOT NULL
          AND v.rem_enabled = 0
        THEN 1
        ELSE 0
      END) AS vaults_missing_reminders

    FROM ${tbl_va} v`;

    //const valuesVault = [now, weak_pwd_score, ...reminderSlugs];
    const valuesVault = [now, ...reminderSlugs];

    const resVault = await VaultDB.dbExecute(sqlVault, valuesVault);
    const rowVault = resVault.rows.item(0);

    const sqlReminder = `SELECT SUM(CASE WHEN status = 1 AND next_trigger_at BETWEEN ? AND ?
      AND (repeat_interval IS NULL OR repeat_interval NOT IN ('daily','hourly', 'weekly')) THEN 1 ELSE 0 END) AS due_reminders
      FROM ${tbl_rm}`;

    const resReminder = await VaultDB.dbExecute(sqlReminder, [now, next30Days]);
    const rowReminder = resReminder.rows.item(0);

    return {
        //weak_passwords: rowVault.weak_passwords || 0,
        //critical_passwords: rowVault.critical_passwords || 0,
        expired_items: rowVault.expired_items || 0,
        due_reminders: rowReminder.due_reminders || 0,
        vaults_missing_reminders: rowVault.vaults_missing_reminders || 0
    };
  }

  async function backupStatus() {
    if (_MODE_ === "dev") {
      //return null;
      return {
        backup_time: Date.now() - (2 * 86400000), // 2 days ago
        backup_url:  "file://mock/mahavault_backup_20250316_1042mahavault_backup_20250316_1042.bin"
      };
    }

    const backup_time_raw = await secure_storage(VaultMateConfig.storageKeys.last_backup_time);
    const backup_url = await secure_storage(VaultMateConfig.storageKeys.last_backup_path);
   
    if (!backup_time_raw) {
      return null;
    }
   
    return {
      backup_time: Number(backup_time_raw),
      backup_url:  backup_url || ""
    };
  } 

  async function blastRadius() {
    if(_MODE_ === "dev") {
      return [
        { hash: "1", total: 15, masked: "te****23" },
        { hash: "2", total: 12, masked: "qw****ty" },
        { hash: "3", total: 10, masked: "28****78" },
        { hash: "5", total: 8, masked: "kj****ik" },
        { hash: "6", total: 7, masked: "g5****9q" },
      ];
    }

    const res = await VaultDB.dbExecute(`
      SELECT password_hash, COUNT(*) as total, MAX(addl_json) as addl_json
      FROM vaults
      WHERE status = 1 
        AND password_score > 0 
        AND password_hash IS NOT NULL
      GROUP BY password_hash
      HAVING COUNT(*) > 1
      ORDER BY total DESC
      LIMIT 5
    `);

    const out = [];

    for(let i = 0; i < res.rows.length; i++) {
      const row = res.rows.item(i);
      let masked = "****";
      try {
        const addl = JSON.parse(row.addl_json || "{}");
        masked = addl.masked_password || "****";
      } catch(e) {}

      out.push({
        hash: row.password_hash,
        total: row.total,
        masked
      });
    }

    return out;
  }
  
  async function securityInsights() {

    if (_MODE_ === "dev") {
      return {
        reused: 2,
        crackable: 3,
        predictable: 7,
        old: 9,
        total: 20
      };
    }

    const now = Date.now();
    const oldThreshold = now - (90 * 86400 * 1000);

    const res = await VaultDB.dbExecute(`SELECT COUNT(*) AS total,  AVG(password_score) AS avg_score,
        SUM(CASE WHEN crack_seconds IS NOT NULL AND crack_seconds < 300 THEN 1 ELSE 0 END) AS crackable,
        SUM(CASE WHEN predictability_score >= 70 THEN 1 ELSE 0 END) AS predictable,
        SUM(CASE WHEN password_updated_at < ? THEN 1 ELSE 0 END) AS old
    FROM ${tbl_va}
    WHERE status = 1 AND password_score > 0`, [oldThreshold]);

    const row = res.rows.item(0) || {};


    vm_log("rows", row);


    const reusedRes = await VaultDB.dbExecute(`SELECT COUNT(*) AS c
      FROM vaults
      WHERE status = 1
        AND password_score > 0
        AND password_hash IN (
            SELECT password_hash
            FROM vaults
            WHERE status = 1 
              AND password_score > 0
              AND password_hash IS NOT NULL
            GROUP BY password_hash
            HAVING COUNT(*) > 1
        )
    `);
    const reused = reusedRes.rows.item(0)?.c || 0; 

    vm_log("reused", reused);

    return {
      total: row.total || 0,
      crackable: row.crackable || 0,
      predictable: row.predictable || 0,
      avg_score:row.avg_score || 0,
      old: row.old || 0,
      reused
    };
  }

  return {
    overview,
    pinfavVaults,
    recentVaults,
    topVaults,
    topCategories,
    reminderStats,
    nextReminders,
    passwordHealth,
    attention,
    backupStatus,
    now,
    todayRange,
    weekRange,
    monthRange,
    securityInsights,
    blastRadius,
  };

})();


function show_section_loader(key) {
  document.querySelector(`[data-loader="${key}"]`)?.classList.remove("hide");
  document.querySelector(`[data-empty="${key}"]`)?.classList.add("hide");
  document.querySelector(`[data-item="${key}"]`)?.classList.add("hide");
  document.querySelector(`[data-footer="${key}"]`)?.classList.add("hide");
}

function hide_section_loader(key) {
  document.querySelector(`[data-loader="${key}"]`)?.classList.add("hide");
}

async function scalar(obj) {
  obj.values = Array.isArray(obj.values) ? obj.values : [];    
  const res = _MODE_ != "dev" ? await VaultDB.dbExecute(obj.sql, obj.values) : dev_db_execute(obj.sql, obj.values);
  const row = res.rows.item(0);
  //return row && row.c ? row.c : 0;
  return row && row.c != null ? row.c : 0;
}

async function rows(obj) {
  const res = _MODE_ != "dev" ? await VaultDB.dbExecute(obj.sql, obj.values) : dev_db_execute(obj.sql, obj.values);
  let out = [];
  for(let i = 0; i < res.rows.length; i++) {
    out.push(res.rows.item(i));
  }
  return out;
}

function dev_db_execute(sql) {

  const n = Math.floor(Math.random() * 2);
  if(n == 8) {
    return {
      rows: {
        length: 0,
        item: () => null
      }
    };
  } 

  const s = sql.toLowerCase();

  if((s.includes("count(") || s.includes("avg(")) && !s.includes("group by")) {
    return {
      rows: {
        length: 1,
        item: () => ({ c: Math.floor(Math.random() * 10) + 1 })
      }
    };
  }

  if(s.includes("group by slug")) {
    const data = [
      { slug: "general", c: Math.floor(Math.random() * 20) + 1 },
      { slug: "bank_accounts", c: Math.floor(Math.random() * 20) + 1 },
      { slug: "cards", c: Math.floor(Math.random() * 20) + 1 },
      { slug: "wifi", c: Math.floor(Math.random() * 20) + 1 },
      { slug: "certificates", c: Math.floor(Math.random() * 20) + 1 },
      { slug: "secure_notes", c: Math.floor(Math.random() * 20) + 1}
    ];

    return {
      rows: {
        length: data.length,
        item: i => data[i]
      }
    };
  }

  if(s.includes("from vaults")) {
    const data = getMockVaultData().slice(0, VaultMateConfig.default.limit_pinfav);
    return {
      rows: {
        length: data.length,
        item: i => data[i]
      }
    };
  }

  if(s.includes("from reminders")) {
    const data = get_test_data().slice(0, VaultMateConfig.default.limit_upcoming);
    return {
      rows: {
        length: data.length,
        item: i => data[i]
      }
    };
  }

  return {
    rows: {
      length: 0,
      item: () => null
    }
  };
}

const DASHBOARD_SECTION_REGISTRY = {
  [DSECTIONS.OVERVIEW]: {
    dbLoader: DashboardService.overview,
    renderer: d_overview
  },
  [DSECTIONS.PIN_FAV]: {
    dbLoader: DashboardService.pinfavVaults,
    renderer: d_pin_fav
  },
  [DSECTIONS.RECENT_USED]: {
    dbLoader: DashboardService.recentVaults,
    renderer: d_recentused
  },
  [DSECTIONS.TOP_USED]: {
    dbLoader: DashboardService.topVaults,
    renderer: d_mostused
  },
  [DSECTIONS.CATEGORIES]: {
    dbLoader: DashboardService.topCategories,
    renderer: d_categories
  },
  [DSECTIONS.ATTENTION]: {
    dbLoader: DashboardService.attention,
    renderer: d_attention
  },
  [DSECTIONS.REMINDER_STATS]: {
    dbLoader: DashboardService.reminderStats,
    renderer: d_reminder_stats
  },
  [DSECTIONS.UPCOMING_REMINDERS]: {
    dbLoader: DashboardService.nextReminders,
    renderer: d_up_reminder
  },
  [DSECTIONS.BACKUP]: {
    dbLoader: DashboardService.backupStatus,
    renderer: d_backup_status
  },
  [DSECTIONS.PASSWORD_HEALTH]: {
    dbLoader: DashboardService.passwordHealth,
    renderer: d_password_health
  },
  [DSECTIONS.SECURITY_INSIGHTS]: {
    dbLoader: DashboardService.securityInsights,
    renderer: d_security_insights
  },
  [DSECTIONS.BLAST_RADIUS]: {
    dbLoader: DashboardService.blastRadius,
    renderer: d_blast_radius
  },
};

const DASHBOARD_ACTION_MAP = {
  pin: [
    DSECTIONS.PIN_FAV,
    DSECTIONS.TOP_USED,
    DSECTIONS.RECENT_USED
  ],
  favorite: [
    DSECTIONS.PIN_FAV,
    DSECTIONS.TOP_USED,
    DSECTIONS.RECENT_USED
  ],
  open: [
    DSECTIONS.RECENT_USED,
    DSECTIONS.TOP_USED
  ],
  copy: [
    DSECTIONS.RECENT_USED,
    DSECTIONS.TOP_USED
  ],
  share: [
    DSECTIONS.RECENT_USED,
    DSECTIONS.TOP_USED
  ],
  add_vault: [
    DSECTIONS.OVERVIEW,
    DSECTIONS.CATEGORIES,
    DSECTIONS.ATTENTION,
    DSECTIONS.PASSWORD_HEALTH,
    DSECTIONS.SECURITY_INSIGHTS,
    DSECTIONS.BLAST_RADIUS,
    DSECTIONS.BACKUP,
  ],
  edit_vault: [
    DSECTIONS.PIN_FAV,
    DSECTIONS.RECENT_USED,
    DSECTIONS.TOP_USED,
    DSECTIONS.ATTENTION,
    DSECTIONS.PASSWORD_HEALTH,
    DSECTIONS.SECURITY_INSIGHTS,
    DSECTIONS.BLAST_RADIUS,
    DSECTIONS.BACKUP
  ],
  delete_vault: [
    DSECTIONS.OVERVIEW,
    DSECTIONS.CATEGORIES,
    DSECTIONS.PIN_FAV,
    DSECTIONS.RECENT_USED,
    DSECTIONS.TOP_USED,
    DSECTIONS.ATTENTION,
    DSECTIONS.PASSWORD_HEALTH,
    DSECTIONS.SECURITY_INSIGHTS,
    DSECTIONS.BLAST_RADIUS,
    DSECTIONS.BACKUP
  ],
  reminder: [
    DSECTIONS.OVERVIEW,
    DSECTIONS.ATTENTION,
    DSECTIONS.UPCOMING_REMINDERS,
    DSECTIONS.REMINDER_STATS,
    DSECTIONS.BACKUP
  ],
  backup: [
    DSECTIONS.BACKUP
  ],
};
