function rm_categories() {
    return [
        { id: "personal", label: "Personal", icon: "fas fa-user" },
        { id: "bills", label: "Bills & Payments", icon: "fas fa-file-invoice-dollar" },
        { id: "subscriptions",label: "Subscriptions", icon: "fas fa-sync-alt" },
        { id: "health", label: "Health", icon: "fas fa-heartbeat" },
        { id: "work", label: "Work", icon: "fas fa-briefcase" },
        { id: "family", label: "Family", icon: "fas fa-users" },
        { id: "documents", label: "Documents", icon: "fas fa-file-alt" },
        { id: "travel", label: "Travel", icon: "fas fa-plane" },
        { id: "shopping", label: "Shopping", icon: "fas fa-shopping-cart" },
        { id: "insurance", label: "Insurance", icon: "fas fa-shield-alt" },
        { id: "loans", label: "Loans", icon: "fas fa-money-bill-wave" },
        { id: "creditcards", label: "Credit Cards", icon: "fas fa-credit-card" },
        { id: "vehicle", label: "Vehicle", icon: "fas fa-car" },
        { id: "events", label: "Events", icon: "fas fa-calendar-alt" },
        { id: "other", label: "Other", icon: "fas fa-folder" }
    ];
}

function rm_countdown_text(ts) {
    const diffMs = ts - Date.now();
    if (diffMs <= 0) return "";

    const mins = Math.floor(diffMs / 60000);
    if (mins < 60) return `${mins}m`;

    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ${mins % 60}m`;

    const days = Math.floor(hrs / 24);
    if (days < 14) return `${days}d ${hrs % 24}h`;
    if (days < 60) return `${days}d`;

    if (days < 365) {
        const months = Math.floor(days / 30);
        const remDays = days % 30;
        return remDays ? `${months}mo ${remDays}d` : `${months}mo`;
    }

    const years = Math.floor(days / 365);
    const remDays = days % 365;
    const remMonths = Math.floor(remDays / 30);
    return remMonths ? `${years}y ${remMonths}mo` : `${years}y`;
}

function get_rm_category_by_slug(id) {
    const list = rm_categories();
    return list.find(c => c.id === id) || list.find(c => c.id === "other");
}

function populate_rm_category(selectId, selectedValue = null) {
    const selectEl = dgi(selectId);
    if(!selectEl) {
        return;
    }
    selectEl.innerHTML = '<option value="">Select category</option>';
    rm_categories().forEach(cat => {
        const option = document.createElement("option");
        option.value = cat.id;
        option.textContent = cat.label;

        if(selectedValue && selectedValue === cat.id) {
            option.selected = true;
        }
        selectEl.appendChild(option);
    });
}

function rm_item_html(reminder) {
    if (!reminder) {
        return "";
    }

    const ts = Number(reminder.next_trigger_at || reminder.reminder_at);
    if (!ts) {
        return "";
    }

    const nowTs = Date.now();
    const date = new Date(ts);
    const now = new Date();

    const timeStr = date.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: true
    });

    const isCompleted = reminder.status == 0;
    const isVault = reminder.vault_id != null;
    
    //const isExpired = reminder.status === 1 && ts < nowTs;
    const isExpired = reminder.status === 1 && ts < nowTs && !(reminder.reminder_type === "repeat" && !reminder.repeat_end_date);
    const disableMark = isCompleted || isVault || isExpired;
    const cat = get_rm_category_by_slug(reminder.category);

    let timeText = "";
    if(reminder.reminder_type === "repeat" && reminder.repeat_interval) {

        if (reminder.repeat_interval === "hourly") {
            timeText = `Every hour at :${String(date.getMinutes()).padStart(2, "0")}`;
        } else if (reminder.repeat_interval === "daily") {
            timeText = `Every day at ${timeStr}`;
        } else if (reminder.repeat_interval === "weekly") {
            const day = date.toLocaleDateString("en-US", { weekday: "long" });
            timeText = `Every ${day} at ${timeStr}`;
        } else if(reminder.repeat_interval === "weekdays") {
            timeText = `Every weekday at ${timeStr}`;
        } else if(reminder.repeat_interval === "weekends") {
            timeText = `Every weekend at ${timeStr}`;
        } else if (reminder.repeat_interval === "monthly") {
            const dayNum = date.getDate();
            timeText = `Every month on ${dayNum} at ${timeStr}`;
        } else if (reminder.repeat_interval === "yearly") {
            const md = date.toLocaleDateString("en-US", {month: "short", day: "numeric"});
            timeText = `Every year on ${md} at ${timeStr}`;
        } else {
            timeText = `Repeats at ${timeStr}`;
        }

        //const countdown = (!isCompleted && !isExpired) ? rm_countdown_text(ts) : "";
        //timeText += countdown ? `<br><span class="vm-next-fire">Next: ${timeStr} · ${countdown}</span>` : "";


    } else {
        const diffMs = ts - nowTs;
        const diffDays = Math.floor(diffMs / (24 * 60 * 60 * 1000));
        const isToday = date.toDateString() === now.toDateString();

        const tomorrow = new Date(now);
        tomorrow.setDate(now.getDate() + 1);
        const isTomorrow = date.toDateString() === tomorrow.toDateString();

        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        const isYesterday = date.toDateString() === yesterday.toDateString();

        if(isToday) {
            timeText = `Today at ${timeStr}`;
        } else if (isTomorrow) {
            timeText = `Tomorrow at ${timeStr}`;
        } else if (isYesterday) {
            timeText = `Yesterday at ${timeStr}`;
        } else if (diffDays > 1 && diffDays <= 7) {
            const weekday = date.toLocaleDateString("en-US", { weekday: "long" });
            timeText = `This ${weekday} at ${timeStr}`;
        } else if (diffDays < -1 && diffDays >= -7) {
            const weekday = date.toLocaleDateString("en-US", { weekday: "long" });
            timeText = `Last ${weekday} at ${timeStr}`;
        } else {
            const shortDate = date.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric"
            });
            timeText = `${shortDate} at ${timeStr}`;
        }
    }

    const isActive = !isCompleted && !isExpired;
    const countdown = isActive ? rm_countdown_text(ts) : "";

    return `<div class="list-item" data-rm-id="${reminder.id}">
        <div class="list-icon">
            <i class="${cat.icon}"></i>
        </div>
        <div class="list-content">
            <div class="vm-list-title">
                ${reminder.title}

                ${countdown ? `<span class="vm-next-pill ${rm_urgency_class(ts)}"><i class="far fa-clock"></i> ${countdown}</span>` : ``}

            </div>
            ${reminder.description ? `<div class="vm-list-notes">${reminder.description}</div>` : ``}
            <div class="vm-list-notes">
                ${timeText}                
            </div>
        </div>

        <div class="list-action">
            <i class="fas fa-check-circle ${disableMark ? "vm_action_disabled" : ""} vm-action-btn"
               data-id="${reminder.id}">
            </i>
        </div>
    </div>`;
}

function mins_urgent(ts) {
    return (ts - Date.now()) < 60 * 60 * 1000; // under 1 hour
}
function rm_urgency_class(ts) {
    const diff = ts - Date.now();
    if (diff < 60 * 60 * 1000) return "vm-next-pill-soon";      // < 1h — red
    if (diff < 24 * 60 * 60 * 1000) return "vm-next-pill-today"; // < 24h — amber
    return "";                                                    // default — indigo
}

async function open_reminder_detail(id) {
    const reminder = await get_rm_by_id(id);
    if(!reminder) {
        show_toast("Reminder not found!");
        return false;
    }
    
    //console.log("reminder", reminder);

    let html = render_view_rm(reminder);    
    dgi("vm_rm_view_content").innerHTML = html;
    dgi("view_rm_detail").textContent = reminder.title || "Reminder";
    open_bottom_sheet("vm_rm_view_sheet");
    check_device_rm_status(reminder);
}

function render_view_rm(reminder) {
    const ts = Number(reminder.next_trigger_at || reminder.reminder_at);
    const nowTs = Date.now();
    const isMarked = reminder.status == 0;

    let isPast = false;
    let isUpcoming = false;

    if(!isMarked) {
        if(reminder.reminder_type === "onetime") {
            if(nowTs > ts) {
                isPast = true;
            } else {
                isUpcoming = true;
            }
        } else if(reminder.reminder_type === "repeat") {
            if(reminder.repeat_end_date) {
                const endTs = Number(reminder.repeat_end_date);
                if(nowTs > endTs) {
                    isPast = true;
                } else {
                    isUpcoming = true;
                }
            } else {
                isUpcoming = true;
            }
        }
    }

    let statusText = "Unknown";
    if(isMarked) {
        statusText = "Completed";
    } else if (isPast) {
        statusText = reminder.reminder_type === "repeat" ? "Ended" : "Overdue";
    } else if (isUpcoming) {
        statusText = "Upcoming";
    }

    const date = new Date(ts);
    const timeStr = date.toLocaleTimeString("en-US", {hour: "2-digit", minute: "2-digit", hour12: true});
    const fullDate = date.toLocaleDateString("en-US", {weekday: "long", month: "long", day: "numeric", year: "numeric"});

    let rowsHTML = "";
    const category_obj = get_rm_category_by_slug(reminder.category);

    rowsHTML += `<div class="vm-view-row">
        <span class="vm-label">Category</span>
        <span class="vm-value">${escape_html(category_obj.label)}</span>
    </div>`;

    if(reminder.description) {
        rowsHTML += `<div class="vm-view-row">
            <span class="vm-label">Description</span>
            <div class="vm-value">${escape_html(reminder.description)}</div>
        </div>`;
    }

    rowsHTML += `<div class="vm-view-row">
        <span class="vm-label">Date</span>
        <span class="vm-value">${fullDate}</span>
    </div>`;

    rowsHTML += `<div class="vm-view-row">
        <span class="vm-label">Time</span>
        <span class="vm-value">${timeStr}</span>
    </div>`;

     if(isUpcoming) {
        const nextCountdown = rm_countdown_text(ts);
        if (nextCountdown) {
            rowsHTML += `<div class="vm-view-row">
                <span class="vm-label">Next In</span>
                <span class="vm-value"><span class="vm-next-pill ${rm_urgency_class(ts)}"><i class="far fa-clock"></i> ${nextCountdown}</span></span>
            </div>`;
        }
    }
    if(reminder.reminder_type === "repeat") {
        let repeatText = reminder.repeat_interval || "";

        /*if(reminder.repeat_interval === "daily") {
            repeatText = `Daily (Every day at ${timeStr})`;
        }
        if(reminder.repeat_interval === "weekly") {
            const day = date.toLocaleDateString("en-US", { weekday: "long" });
            repeatText = `Weekly (Every ${day} at ${timeStr})`;
        }
        if(reminder.repeat_interval === "monthly") {
            repeatText = `Monthly (Day ${date.getDate()} at ${timeStr})`;
        }
        if(reminder.repeat_interval === "yearly") {
            const md = date.toLocaleDateString("en-US", {month: "short", day: "numeric"});
            repeatText = `Yearly (${md} at ${timeStr})`;
        }*/

        if(reminder.repeat_interval === "hourly") {
            repeatText = `Hourly (Every hour at :${String(date.getMinutes()).padStart(2, "0")})`;
        } else if(reminder.repeat_interval === "daily") {
            repeatText = `Daily (Every day at ${timeStr})`;
        } else if(reminder.repeat_interval === "weekdays") {
            repeatText = `Weekdays (Mon–Fri at ${timeStr})`;
        } else if(reminder.repeat_interval === "weekends") {
            repeatText = `Weekends (Sat–Sun at ${timeStr})`;
        } else if(reminder.repeat_interval === "weekly") {
            const day = date.toLocaleDateString("en-US", { weekday: "long" });
            repeatText = `Weekly (Every ${day} at ${timeStr})`;
        } else if(reminder.repeat_interval === "monthly") {
            repeatText = `Monthly (Day ${date.getDate()} at ${timeStr})`;
        } else if(reminder.repeat_interval === "yearly") {
            const md = date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
            repeatText = `Yearly (${md} at ${timeStr})`;
        }

        rowsHTML += `<div class="vm-view-row">
            <span class="vm-label">Repeats</span>
            <span class="vm-value">${repeatText}</span>
        </div>`;

        let endText = "Never";
        if(reminder.repeat_end_date) {
            const endDate = new Date(Number(reminder.repeat_end_date));
            endText = endDate.toLocaleDateString("en-US", {month: "long", day: "numeric", year: "numeric"});
        }

        rowsHTML += `<div class="vm-view-row">
            <span class="vm-label">Ends On</span>
            <span class="vm-value">${endText}</span>
        </div>`;
    }

    rowsHTML += `<div class="vm-view-row">
        <span class="vm-label">Status</span>
        <span class="vm-value">${statusText}</span>
    </div>`;

    if(reminder.vault_id) {
        rowsHTML += `<div class="vm-view-row">
            <span class="vm-label">Linked Vault</span>
            <span class="vm-value">Attached</span>
        </div>`;
    }

    rowsHTML += `<div class="vm-view-row" id="vm_rm_device_row">
        <span class="vm-label">Notification Status</span>
        <span class="vm-value" id="vm_rm_device_value">Checking...</span>
    </div>`;

    let actionsHTML = "";
    if(reminder.vault_id) {
        actionsHTML = `<button class="vm-btn vm-btn-edit vm_remainder_edit_btn" data-id="${reminder.vault_id}" data-type="vault">Edit Vault</button>`;
    } else {
        actionsHTML = `<button class="vm-btn vm-btn-danger vm_remainder_delete_btn" data-id="${reminder.id}">Delete</button>
            <button class="vm-btn vm-btn-edit vm_remainder_edit_btn" data-id="${reminder.id}" data-type="reminder">Edit</button>`;
    }
    dgi("vm_rm_actions").innerHTML = actionsHTML;

    return `<div class="vm-view-wrapper">
        <div class="vm-view-section">
            ${rowsHTML}
        </div>
    </div>`;
}


function rm_date_range(e) {
    const tag = e.target.closest(".bottom_sheet_tags");
    if (!tag) {
        return;
    }
    document.querySelectorAll("#rm_date_range .bottom_sheet_tags").forEach(t => t.classList.remove("active"));
    tag.classList.add("active");
    filter_date_range = tag.dataset.range;
    dgi("rm_custom_date_range").style.display = filter_date_range === "custom" ? "block" : "none";
}

function rm_filter_applied(f = {}) {
    return !!(f.search || f.type || f.due_days || f.rm_stats || f.time || f.source || (Array.isArray(f.categories) && f.categories.length) || (f.date_range && Object.keys(f.date_range).length));
}

function get_rm_sort(filters = {}) {
    const sort = [];
    sort.push({
        col: "CASE WHEN status = 0 THEN 1 ELSE 0 END",
        dir: "ASC",
        raw: true
    });
    switch(filters.sort) {
        case "upcoming_first":
            sort.push({col: "next_trigger_at", dir: "ASC" });
            break;
        case "oldest_first":
            sort.push({ col: "created_at", dir: "ASC" });
            break;
        case "name_asc":
            sort.push({ col: "title", dir: "ASC" });
            break;
        case "name_desc":
            sort.push({ col: "title", dir: "DESC" });
            break;
        case "new_first":
        default:
            sort.push({ col: "created_at", dir: "DESC" });
        break;
    }
    return sort;
}

async function delete_rm(rm_id) {

    const index = await ons.notification.confirm("Are you sure you want to delete this reminder?");
    if(index !== 1) {
        return;
    }
    
    await delete_rm_with_notification(rm_id);
    vm_log("deleted ", rm_id)

    /*const el = document.querySelector(`.vm_remainder_list .list-item[data-rm-id="${rm_id}"]`);
    if(!el) return;*/
    const elements = document.querySelectorAll(`.vm_remainder_list .list-item[data-rm-id="${rm_id}"]`);
    if(!elements.length) {
        return;
    }
    elements.forEach(el => {
        el.classList.add("fade-out");
        el.style.pointerEvents = "none"; 
    });

    setTimeout(() => {
        elements.forEach(el => el.remove());
        if(document.querySelectorAll(".vm_remainder_list .list-item").length < 1) {
            set_empty_value(rm_filters);
        }
        refresh_dashboard_action("reminder");
        show_rm_visible();
    }, 200);

    close_bottom_sheet();      
}

async function save_rm_item(e) {
    const btn = e?.currentTarget;
    set_button_loading(btn, true);

    const rm_data = prepare_rm_data();
    if(!rm_data) {
        set_button_loading(btn, false);
        return;
    }

    is_rm_mode = true; 
    const is_edit = !!editing_rm_id;

    try {
        const res = await manage_reminder({id:editing_rm_id, data:rm_data});
        if(!res.success) {
            show_toast("Failed to save reminder");
            set_button_loading(btn, false);
            return;
        }    
        const scheduled = await apply_rm_schedule(res.reminder, is_edit);
        let rm_msg = editing_rm_id ? "Reminder updated" : "Reminder added";
        if(!editing_rm_id) {
            insert_reminder_card(res.reminder);
        } else {
            update_reminder_card(res.reminder);
        }
        if(!scheduled) {
            rm_msg = "Saved, but notifications appear to be disabled — check app notification settings";
        }

        set_button_loading(btn, false);
        show_toast(rm_msg);
        editing_rm_id = null;
        close_bottom_sheet();
        refresh_dashboard_action("reminder");   

    } catch (e) {
        //alert("Error: " + e.message + "\n\nStack Trace:\n" + e.stack);
        set_button_loading(btn, false);
        show_toast(e.message);
    }    
}

async function mark_rm(id) {
    const index = await ons.notification.confirm({
        message: "Mark this reminder as completed?\nYou won't receive notifications for it again.",
        buttonLabels: ["Cancel", "Mark as completed"]
    });

    if(index !== 1) {
        return;
    }

    try {
        await cancel_rm_notification(id);
        const {sql, values} = VaultDB.buildUpdate(VaultMateConfig.tables.reminders, {status:0, updated_at:Date.now()}, {id:id, status:1});
        await VaultDB.dbExecute(sql, values);

        const reminder = await get_rm_by_id(id);
        update_reminder_card(reminder);
        show_toast("Reminder marked as completed");
        refresh_dashboard_action("reminder");
    } catch(e) {
        show_toast("Reminder could not be marked as completed");
    }       

    return true;
}

function to_local_date_str(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

async function edit_rm(id) {
    const reminder = await get_rm_by_id(id);
    if(!reminder) {
        show_toast("Reminder not found!");
        return false;
    }

    const dt = new Date(reminder.reminder_at);    
    const start_date = to_local_date_str(dt);
    const time = dt.toTimeString().slice(0, 5);

    editing_rm_id = reminder.id;
    dgi("rm_title").value = reminder.title;
    dgi("rm_desc").value = reminder.description;
    dgi("rm_start_date").value = start_date;
    dgi("rm_time").value = time;
    dgi("rm_category").value = reminder.category;

    if(reminder.reminder_type === "repeat") {
        document.querySelector('[data-type="repeat"]').click();
        dgi("repeat_interval").value = reminder.repeat_interval || "";
        dgi("rm_repeat_end_date").value = reminder.repeat_end_date ? to_local_date_str(new Date(Number(reminder.repeat_end_date))) : "";
    } else {
        document.querySelector('[data-type="onetime"]').click();
    }

    is_rm_mode = true;
    dgi("reminder_sheet_title").innerHTML = "Update Reminder";       
    open_bottom_sheet("vm_remainder_bottom_sheet");
}

function prepare_rm_data() {

    const categoryEl = dgi("rm_category");
    const titleEl = dgi("rm_title");
    const descEl = dgi("rm_desc");
    const startDateEl = dgi("rm_start_date");
    const endDateEl = dgi("rm_repeat_end_date");
    const timeEl = dgi("rm_time");

    const title = titleEl.value.trim();
    const category = categoryEl.value.trim();
    const description = descEl.value.trim();
    const start_date = startDateEl.value;
    const end_date = endDateEl.value;
    const time = timeEl.value;

    const activeTypeEl = document.querySelector(".vm_rm_type_sel.active");
    const reminder_type = activeTypeEl ? activeTypeEl.dataset.type : "onetime";

    const errors = {};

    if(!category) {
        errors.rm_category = "Category is required";
    }

    if(!title) {
        errors.rm_title = "Title is required";
    }

    if(!start_date) {
        errors.rm_start_date = "Start date is required";
    }

    if(!time) {
        errors.rm_time = "Time is required";
    }

    if(reminder_type === "repeat" && end_date) {
        const startDateObj = new Date(start_date);
        const endDateObj = new Date(end_date);
        if(endDateObj < startDateObj) {
            errors.rm_repeat_end_date = "End date cannot be before start date";
        }
    }

    if(Object.keys(errors).length) {
        show_errors(errors);
        return false;
    }   

    const reminder_at = new Date(`${start_date}T${time}:00`).getTime();
    const repeat_end_ts = (reminder_type === "repeat" && end_date) ? new Date(`${end_date}T${time}:00`).getTime() : null;

    return {
        category,
        title,
        description: description || null,
        reminder_type,
        reminder_at,
        repeat_interval: reminder_type === "repeat" ? dgi("repeat_interval")?.value || null : null,
        repeat_end_date: repeat_end_ts,
        status: 1,
        addl_json: null
    };
}

function init_reminder(container) {
    container.addEventListener("scroll", e => {
        rm_list_scroll(e, container);
    });

    /*const saveBtn = dgi("rm_save");
    saveBtn.onclick = null;
    saveBtn.onclick = (e) => {save_rm_item(e)};*/

    const clear_Btn = dgi("vm_rm_clearFilter");
    clear_Btn.onclick = null;
    clear_Btn.onclick = (e) => {clear_rm_filter()};    

    document.addEventListener("click", (e) => {
        const btn = e.target.closest(".vm_remainder_edit_btn");
        if(!btn || btn.classList.contains("vm_action_disabled")) {
            return;
        }
        const type = btn.dataset.type;
        let type_id = btn.dataset.id;
        close_bottom_sheet();
        setTimeout(async () => {
            if(type === "vault") {
                await edit_vault(type_id);
            } else {
                await edit_rm(type_id);
            }
        }, 100);        
    });

    document.addEventListener("click", e => {
        const btn = e.target.closest(".vm_remainder_delete_btn");
        if(!btn) return;
        delete_rm(btn.dataset.id);
    });

    dgi("rm_date_range").addEventListener("click", rm_date_range);

    document.querySelectorAll(".rm_sort").forEach(choice => {
        choice.addEventListener("click", function () {
            if(this.classList.contains("active")) {
                return;
            }
            [...this.parentElement.children].forEach(c => c.classList.remove("active"));
            this.classList.add("active");
            rm_filters.sort = this.dataset.value;
            apply_rm_filter();
        });
    });

    document.addEventListener("click", e => {
        const tag = e.target.closest(".bottom_sheet_tags");
        if (!tag) return;
        const group = tag.parentElement;
        [...group.children].forEach(t => t.classList.remove("active"));
        tag.classList.add("active");
    });

    dgi("vm_remainder_filter_apply").addEventListener("click", () => {
        rm_filters.search = dgi("vm_rm_search").value.trim();
        rm_filters.type = dgi("vm_reminder_type").querySelector(".active")?.dataset.type || "";
        rm_filters.time = dgi("vm_reminder_time").value;
        rm_filters.source = dgi("vm_reminder_source").value;
        rm_filters.date_range = get_daterange("rm") || null;
        rm_filters.categories = get_rm_selected_categories();

        apply_rm_filter();
    });
}

function get_rm_selected_categories() {
  return Array.from(document.querySelectorAll('#rm_category_checklist input:checked')).map(el => el.value);
}

function rm_list_scroll(e, container) {
    const now = Date.now();
    if(is_loading || !has_more_data) return;        
    if(now - last_scrollLoad < 300) return;

    if(container.scrollTop + container.clientHeight >= container.scrollHeight - 200) {
        last_scrollLoad = now;
        load_more(current_page + 1, {filters: rm_filters});
    }
}

function clear_rm_filter(ingore_data = 0) {
    // reset filter state
    rm_filters.sort = "new_first";
    rm_filters.search = "";
    rm_filters.type = "";
    rm_filters.time = "";
    rm_filters.source = "";
    rm_filters.date_range = null;
    rm_filters.due_days = null;
    rm_filters.rm_stats = null;
    rm_filters.categories = [];

    // reset inputs
    dgi("vm_rm_search").value = "";
    dgi("vm_reminder_time").value = "";
    dgi("vm_reminder_source").value = "";

    // reset reminder type (set All active)
    dgi("vm_reminder_type").querySelectorAll(".bottom_sheet_tags").forEach(el => el.classList.remove("active"));
    dgi("vm_reminder_type").querySelector('[data-type=""]').classList.add("active");

    // reset date range (set Anytime active)
    dgi("rm_date_range").querySelectorAll(".bottom_sheet_tags").forEach(el => el.classList.remove("active"));
    dgi("rm_date_range").querySelector('[data-range="any"]').classList.add("active");

    dgi("rm_date_from").value = "";
    dgi("rm_date_to").value = "";
    dgi("rm_custom_date_range").style.display = "none";
    set_rm_filter("sort", "new_first");

    if(ingore_data == 1) {
        return;
    }

    apply_rm_filter();
}

function clearRMFilter() {
    clear_rm_filter();
    apply_rm_filter();
}

async function apply_rm_filter() {
    is_loading = false;
    current_page = 0;
    has_more_data = true;
    last_scrollLoad = 0;

    document.querySelector(".page__content").scrollTop = 0;
    dgi("vm_rm_clearFilter").disabled = !rm_filter_applied(rm_filters);

    let total = await count_reminders_db(rm_filters);
    render_reminder_meta(total); 
    renderUpgradeInline("reminder", total, REMINDER_LIMIT);   

    load_more(1, {reset: true, filters: rm_filters});
    close_bottom_sheet();
}

function set_empty_value(filters) {
    let ftitle = "No matching reminders";
    let fdesc = "Try changing or clearing the filters to see your reminders.";

    let etitle = "No reminders yet";
    let edesc = "Create your first reminder to stay organized and never miss anything.";

    list_container.innerHTML = rm_filter_applied(filters) ? empty_html("fa-filter",ftitle, fdesc) : empty_html("fa-bell", etitle, edesc);
}   

async function count_reminders_db(filters = {}) {

    if(_MODE_ === "dev") {
        return Math.floor(Math.random() * 5) + 2;
    }

    const {whereClause, whereValues} = build_rm_where(filters);
    const {sql, values} = VaultDB.buildSelect({
        table: VaultMateConfig.tables.reminders,
        columns: ["count(*) as ct"],
        where: whereClause
    });
    values.push(...whereValues);
    const res = await VaultDB.dbExecute(sql, values);
    return res.rows.item(0).ct;
}

function build_rm_where(filters = {}) {

    const now = Date.now();
    const whereParts = [];
    const whereValues = [];

    if(filters.search) {
        whereParts.push("(title LIKE ? OR description LIKE ?)");
        whereValues.push(`%${filters.search}%`, `%${filters.search}%`);
    }

    if(filters.type) {
        whereParts.push("reminder_type = ?");
        whereValues.push(filters.type);
    }

    if(filters.due_days) {
        const days = filters.due_days || 30;
        let next30Days = now + (days * 24 * 60 * 60 * 1000);

        whereParts.push("status = ?");
        whereValues.push(1);

        whereParts.push("next_trigger_at between ? and ?");
        whereValues.push(now, next30Days);
        whereParts.push(" (repeat_interval is null or repeat_interval not in ('daily', 'hourly', 'weekly'))");
    }
    
    if(filters.rm_stats) {
        const t = DashboardService.now();        
        if(filters.rm_stats === "today") {
            const r = DashboardService.todayRange();
            whereParts.push("status = 1 AND next_trigger_at >= ? AND next_trigger_at < ?");
            whereValues.push(r.start, r.end);
        } else if(filters.rm_stats === "week") {
            const r = DashboardService.weekRange();
            whereParts.push("status = 1 AND next_trigger_at >= ? AND next_trigger_at < ?");
            whereValues.push(t, r.end);
        } else if(filters.rm_stats === "month") {
            const r = DashboardService.monthRange();
            whereParts.push("status = 1 AND next_trigger_at >= ? AND next_trigger_at < ?");
            whereValues.push(t, r.end); 
        }
    }

    if(filters.source === "vault") {
        whereParts.push("vault_id IS NOT NULL");
    } else if (filters.source === "personal") {
        whereParts.push("vault_id IS NULL");
    }

    switch (filters.time) {
        case "marked":
            whereParts.push("status = ?");
            whereValues.push(0);
        break;

        case "past":
            whereParts.push("status = ?");
            whereValues.push(1);

            whereParts.push("next_trigger_at < ?");
            whereValues.push(now);
        break;
        case "upcoming":
            whereParts.push("status = ?");
            whereValues.push(1);

            whereParts.push("next_trigger_at >= ?");
            whereValues.push(now);
        break;     
    }

    if(filters.date_range?.from) {
        whereParts.push("created_at >= ?");
        whereValues.push(filters.date_range.from);
    }

    if(filters.date_range?.to) {
        whereParts.push("created_at <= ?");
        whereValues.push(filters.date_range.to);
    }

    if(filters.categories?.length) {
        whereParts.push(`category IN (${filters.categories.map(() => "?").join(",")})`);
        whereValues.push(...filters.categories);
    }

    return {
        whereClause: whereParts.join(" AND "),
        whereValues
    };
}

async function list_rm_db({page = 1, limit = VaultMateConfig.default.rm_per_page, filters = {}}) {
    const offset = (page - 1) * limit;
    const orderBy = get_rm_sort(filters);
    const {whereClause, whereValues} = build_rm_where(filters);

    const {sql, values} = VaultDB.buildSelect({table:VaultMateConfig.tables.reminders, columns:["*"], where:whereClause, orderBy, limit, offset});
    values.push(...whereValues);

    //console.log(sql, values);

    if(_MODE_ == "dev") {
        const all = get_test_data();
        return all.slice(offset, offset + limit);    
    }   

    const res = await VaultDB.dbExecute(sql, values);
    const rows = [];
    for(let i = 0; i < res.rows.length; i++) {
        rows.push(res.rows.item(i));
    }
    return rows;
}

async function load_more(page, {reset = false, filters = {}} = {}) {
    if(is_loading) {
        return;
    }
    is_loading = true;

    if(reset) {
        list_container.innerHTML = "";
        current_page = 0;
        has_more_data = true;
        is_page_loading(true);
    }

    const rows = await list_rm_db({page, limit: VaultMateConfig.default.rm_per_page, filters});
    if(rows.length < VaultMateConfig.default.rm_per_page) {
        has_more_data = false;
    }

    if(reset && rows.length === 0) {
        is_page_loading(false);            
        is_loading = false;
        set_empty_value(filters);
        show_rm_visible();
        return;
    }

    rows.forEach(row => {
        list_container.insertAdjacentHTML("beforeend", rm_item_html(row));
    });

    show_rm_visible();
    is_page_loading(false);
    current_page = page;
    is_loading = false;
}

function show_rm_visible() {
    let count = document.querySelectorAll("#vm_remainder_list .list-item").length;
    dgi("rmVisibleCount").textContent = count
}

function render_reminder_meta(count = 0, update_count_only = 0) {
    const wrap = dgi("rmFilterChips");
    const top_bar = dgi("rmtopbar");
    wrap.innerHTML = "";

    if(!rm_filter_applied(rm_filters) && count == 0) {
        top_bar.style.display = "none";
        return;
    } else {
        top_bar.style.display = "block";
    }

    //console.log("rm_filters", rm_filters)

    if(rm_filter_applied(rm_filters)) {
        wrap.insertAdjacentHTML("beforeend", `<div class="vault-chip clear_filter_bar" onclick="clearRMFilter()">
            <i class="fas fa-times"></i>
            <span>Clear filters</span>
        </div>`);
    }

    dgi("rmTotalCount").textContent = count;   
    show_rm_visible(); 

    Object.keys(rm_filters).forEach(key => {
        const val = rm_filters[key];
        if(!val || (Array.isArray(val) && !val.length)) {
            return;
        }

        if(key === "categories" && Array.isArray(val) && val.length) {
            const firstSlug = val[0];
            const firstCat = get_rm_category_by_slug(firstSlug);

            const firstLabel = firstCat ? firstCat.label : firstSlug;
            const extra = val.length > 1 ? ` +${val.length - 1}` : "";

            const chip = document.createElement("div");
            chip.className = "vault-chip vm-sheet-open";
            chip.setAttribute("data-action", "filter");
            chip.innerHTML = `<i class="fas fa-folder"></i><span>${firstLabel}${extra}</span>`;

            wrap.appendChild(chip);
            return;
        }

        if(key === "date_range" && typeof val === "object") {
            let label = "";
            if(val.lastDays) {
                label = `Last ${val.lastDays} days`;
            } else if(val.from || val.to) {
                const from = val.from ? new Date(val.from).toLocaleDateString() : "";
                const to = val.to ? new Date(val.to).toLocaleDateString() : "Today";
                label = `${from} to ${to}`;
            }
            if(label) {
                const chip = document.createElement("div");
                chip.className = "vault-chip vm-sheet-open";
                chip.setAttribute("data-action", "filter");

                chip.innerHTML = `<i class="fas fa-calendar-alt"></i><span>${label}</span>`;
                wrap.appendChild(chip);
            }
            return;
        }

        const values = Array.isArray(val) ? val : [val];
        values.forEach(v => {
            let label = v;
            let sheet_name = "filter";
            let iclassname = "";

            if(key === "sort") {
                const el = document.querySelector(`#rm_sort_bs .rm_sort[data-value="${val}"]`);
                label = el ? el.textContent.trim() : val.replace(/_/g, " ");
                sheet_name = "sort";
                iclassname = "fa-sort-amount-down";                
            } else if (key === "search") {
                label = `${v}`;
                iclassname = "fa-search";
            } else if (key === "type") {
                if(v == "onetime") {
                    label = "Onetime";
                    iclassname = "fa-calendar-day";
                } else if(v == "repeat") {
                    label = "Repeat";
                    iclassname = "fa-sync-alt";
                }                
            } else if (key === "source") {
                if(v == "personal") {
                    label = "Personal";
                    iclassname = "fa-user";
                } else if(v == "vault") {
                    label = "Vault";
                    iclassname = "fa-shield-alt";
                }
            } else if (key === "time") {
                if(v == "past") {
                    label = "Past";
                    iclassname = "fa-history";
                } else if(v == "upcoming") {
                    label = "Upcoming";
                    iclassname = "fa-clock";
                } else if(v == "marked") {
                    label = "Completed";
                    iclassname = "fa-check-circle";
                }
            } else if (key === "due_days") {
                label = `Due Soon (${v} days)`;
                iclassname = "fa-calendar-alt";
            } else if (key === "rm_stats") {
                label = "Today";
                iclassname = "fa-calendar-day";
                if(v == "week") {
                    label = "This Week";
                    iclassname = "fa-calendar-week";
                } else if(v == "month") {
                    label = "This Month";
                    iclassname = "fa-calendar";
                }
            } 

            if(label) {
                const chip = document.createElement("div");
                chip.className = "vault-chip vm-sheet-open";
                chip.setAttribute("data-action", sheet_name);

                chip.innerHTML = `<i class="fas ${iclassname}"></i><span>${label}</span>`;
                wrap.appendChild(chip);
            }            
        });
    });

    wrap.style.display = wrap.children.length ? "flex" : "none";
}

async function insert_reminder_card(reminder) {
    const empty = list_container.querySelector(".vm-empty-wrap");
    if(empty) {
        empty.remove();
    }
    list_container.insertAdjacentHTML("afterbegin", rm_item_html(reminder));
    const el = list_container.firstElementChild;
    el.classList.add("new");
    setTimeout(() => el.classList.remove("new"), 600);

    let total = await count_reminders_db(rm_filters);
    render_reminder_meta(total);
    renderUpgradeInline("reminder", total, REMINDER_LIMIT);
}

function update_reminder_card(reminder) {   
    const elements = document.querySelectorAll(`.vm_rem_list .list-item[data-rm-id="${reminder.id}"]`);
    if (!elements.length) {
        return;
    }
    elements.forEach(el => {
        el.outerHTML = rm_item_html(reminder);
    });
}

function cancel_rm_notification(rm_id) {
    return new Promise(resolve => {
        cordova.plugins.notification.local.cancel([rm_id, rm_id + id_snooze], resolve);
    });
}

function get_repeat_trigger(reminder) {
    const anchorTs = Number(reminder.next_trigger_at || reminder.reminder_at);
    if(!anchorTs) {
        return null;
    }
    // hourly/daily/weekly: safe on the plugin's native recurring "every"
    // pattern. TriggerReceiver.java confirms these self-reschedule natively
    // after each fire (notification.scheduleNext()), and there's no
    // day-of-month ambiguity at these granularities. We still pre-subtract
    // one interval because the native TriggerHandlerEvery unconditionally
    // adds one full interval before the very first fire (confirmed in
    // source) - without this, the first notification would land one
    // interval after the chosen start date/time instead of on it.
    switch(reminder.repeat_interval) {
        case "hourly":
            return { every: "hour", firstAt: new Date(anchorTs - 60 * 60 * 1000) };
        case "daily":
            return { every: "day",  firstAt: new Date(anchorTs - 24 * 60 * 60 * 1000) };
        case "weekly":
            return { every: "week", firstAt: new Date(anchorTs - 7 * 24 * 60 * 60 * 1000) };
    }

    // monthly/yearly: deliberately NOT using native "every". Java's
    // Calendar.add(MONTH/YEAR, n) clamps day-of-month one-directionally
    // (e.g. Feb 28 + 1 month = Mar 28, never Mar 31), so a reminder anchored
    // on day 29/30/31 (or Feb 29) can silently fire on the wrong date - and
    // there is no firstAt value that reliably fixes this, because the
    // previous month may not have enough days to "give back". Instead we
    // schedule ONLY the next occurrence as a one-time alarm. catchup_reminders()
    // (run on every app open) already recomputes next_trigger_at correctly
    // with day-clamping via get_next_display_ts(), and now also re-arms
    // this one-time alarm - so the displayed date and the real alarm can
    // never disagree.
    return { at: new Date(anchorTs) };
}

/*async function apply_rm_schedule(reminder, is_edit = false) {
    if(is_edit) {
        await cancel_rm_notification(reminder.id);
    }
    if(reminder.status === 0) {
        return;
    }

    let trigger_ts = reminder.next_trigger_at || reminder.reminder_at;
    if(!trigger_ts) {
        return;
    }

    if(reminder.reminder_type !== "repeat" && trigger_ts < Date.now()) {
        return;
    }

    const trigger = reminder.reminder_type === "repeat" ? get_repeat_trigger(reminder) : { at: new Date(trigger_ts) };

    vm_log("reminder", reminder);
    vm_log("trigger", trigger);

    cordova.plugins.notification.local.schedule({
        id: reminder.id,
        title: reminder.title,
        text: reminder.description || "",
        trigger,
        androidAllowWhileIdle: true,
        androidSmallIcon: "res://ic_notification",
        androidChannelId: reminder_channel_id,
        data: {
            rm_id: reminder.id  
        },
        actions: [
            {id: "dismiss",   title: "Dismiss"},
            {id: "snooze_30", title: "Snooze 30 min"},
            {id: "snooze_60", title: "Snooze 60 min"}
        ]
    });    
}*/

async function apply_rm_schedule(reminder, is_edit = false) {
    //console.log("apply_rm_schedule called", reminder);

    if(is_edit) {
        await cancel_rm_notification(reminder.id);
    }
    if(reminder.status === 0) {
        return true;
    }

    let trigger_ts = reminder.next_trigger_at || reminder.reminder_at;
    if(!trigger_ts) {
        return true;
    }

    if(reminder.reminder_type !== "repeat" && trigger_ts < Date.now()) {
        return true;
    }

    const trigger = reminder.reminder_type === "repeat" ? get_repeat_trigger(reminder) : { at: new Date(trigger_ts) };
    if(!trigger) {
        vm_log("apply_rm_schedule: could not build trigger", reminder);
        return false;
    }

    vm_log("reminder", reminder);
    vm_log("trigger", trigger);


    const permitted = await new Promise(resolve => {

        //console.log("Trigger:", trigger);

        cordova.plugins.notification.local.schedule({
            id: reminder.id,
            title: reminder.title,
            text: reminder.description || "",
            trigger,
            androidAllowWhileIdle: true,
            androidSmallIcon: "res://ic_notification",
            androidChannelId: reminder_channel_id,
            data: {
                rm_id: reminder.id
            },
            actions: [
                {id: "dismiss",   title: "Dismiss"},
                {id: "snooze_30", title: "Snooze 30 min"},
                {id: "snooze_60", title: "Snooze 60 min"}
            ]
        }, (result) => {
            //console.log("schedule callback:", result);
            resolve(result !== false);
        });
    });

    if(!permitted) {
        vm_log("apply_rm_schedule: notification permission not granted", reminder.id);
    }

    return permitted;
}

let RM_RELIABILITY_ISSUES = [];

async function ensure_reminder_reliability() {
    if(!window.cordova || !cordova.plugins || !cordova.plugins.notification) {
        return;
    }
    RM_RELIABILITY_ISSUES = [];

    const canExact = await new Promise(resolve => {
        cordova.plugins.notification.local.canScheduleExactAlarms(resolve);
    });

    if(!canExact) {
        RM_RELIABILITY_ISSUES.push({
            icon: "fa-clock",
            text: "Enable precise alarms to receive reminders exactly on time.",
            actionLabel: "Enable",
            action: () => cordova.plugins.notification.local.openAlarmSettings()
        });
    }

    const unusedStatus = await new Promise(resolve => {
        cordova.plugins.notification.local.getUnusedAppRestrictionsStatus(resolve);
    });

    if(unusedStatus !== 2 && unusedStatus !== 0) {
        RM_RELIABILITY_ISSUES.push({
            icon: "fa-battery-full",
            text: "Disable <span class='text-bold'>Pause app activity if unused</span> to keep reminders on time.",
            actionLabel: "Disable",
            action: () => cordova.plugins.notification.local.openManageUnusedAppRestrictions()
        });
    }
    show_next_reliability_banner();
}

function show_next_reliability_banner() {
    const banner = dgi("rm_reliability_banner");
    if(!banner) {
        return;
    }

    if(!RM_RELIABILITY_ISSUES.length) {
        banner.classList.remove("show");
        return;
    }
    const issue = RM_RELIABILITY_ISSUES[0];

    dgi("rm_reliability_text").innerHTML = issue.text;
    dgi("rm_reliability_fix").textContent = issue.actionLabel;
    banner.querySelector(".vm-reliability-icon i").className = `fas ${issue.icon}`;
    banner.classList.add("show");

    dgi("rm_reliability_fix").onclick = () => {
        issue.action();
        RM_RELIABILITY_ISSUES.shift();
        show_next_reliability_banner();
    };

    dgi("rm_reliability_dismiss").onclick = () => {
        RM_RELIABILITY_ISSUES.shift();
        show_next_reliability_banner();
    };
}

async function manage_reminder({id = null, data, opts = {}}) {
    if(!data) {
        return {success: false, error: "Invalid reminder data"};
    }
    const now = Date.now();
    let reminder_id;  
    let nextTrigger = data.reminder_at;
    await ensureReminderChannel();

    if(data.reminder_type === "repeat") {
        const computed = get_next_display_ts({
            reminder_at: data.reminder_at,
            repeat_interval: data.repeat_interval,
            repeat_end_date: data.repeat_end_date
        });

        if(computed) {
            nextTrigger = computed;
        } else {
            data.status = 0;
        }
    }

    const reminder_row = {
        category:data.category,
        title: data.title,
        description: data.description || null,
        reminder_type: data.reminder_type,  
        reminder_at: data.reminder_at,   
        repeat_interval: data.repeat_interval || null,
        repeat_end_date: data.repeat_end_date || null,
        next_trigger_at: nextTrigger,
        vault_id: data.vault_id || null,
        status: data.status ?? 1,
        addl_json: data.addl_json ? JSON.stringify(data.addl_json) : null,
        updated_at: now
    };

    if(!id) {
        reminder_row.created_at = now;
        const q = VaultDB.buildInsert(VaultMateConfig.tables.reminders, reminder_row);
        const res = await VaultDB.dbExecute(q.sql, q.values, opts);
        reminder_id = res.insertId;
    } else {
        const q = VaultDB.buildUpdate(VaultMateConfig.tables.reminders, reminder_row, {id});
        await VaultDB.dbExecute(q.sql, q.values, opts);
        reminder_id = id;
    }
    return {
        success: true,
        reminder: {
            id: reminder_id,
            ...reminder_row,
            created_at: reminder_row.created_at || undefined
        }
    };
}

async function catchup_reminders() {
    if(_MODE_ === "dev") return;

    const now = Date.now();
    let rtable = VaultMateConfig.tables.reminders;
    let hasChanges = false;

    // 1. Mark fired onetime reminders as completed
    const q1 = VaultDB.buildSelect({
        table: rtable,
        columns: ["id"],
        where: "status = 1 AND reminder_type = 'onetime' AND next_trigger_at < " + now
    });
    const res1 = await VaultDB.dbExecute(q1.sql, q1.values);
    for(let i = 0; i < res1.rows.length; i++) {
        const id = res1.rows.item(i).id;
        const {sql, values} = VaultDB.buildUpdate(rtable, {status: 0, updated_at: now}, {id});
        await VaultDB.dbExecute(sql, values);
        hasChanges = true;
    }

    // 2. Cancel repeat reminders whose end date has passed
    const q2 = VaultDB.buildSelect({
        table: rtable,
        columns: ["id"],
        where: "status = 1 AND reminder_type = 'repeat' AND repeat_end_date IS NOT NULL AND repeat_end_date < " + now
    });
    const res2 = await VaultDB.dbExecute(q2.sql, q2.values);
    for(let i = 0; i < res2.rows.length; i++) {
        const id = res2.rows.item(i).id;
        await cancel_rm_notification(id);
        const {sql, values} = VaultDB.buildUpdate(rtable, {status: 0, updated_at: now}, {id});
        await VaultDB.dbExecute(sql, values);
        hasChanges = true;
    }

    // 3. Sync next_trigger_at for all active repeat reminders. Monthly/yearly
    // reminders are one-time alarms under the hood (see get_repeat_trigger)
    // and do NOT self-reschedule natively, so they must be explicitly
    // re-armed here whenever their computed next date moves forward.
    // Hourly/daily/weekly self-chain natively and don't need this.
    const q3 = VaultDB.buildSelect({
        table: rtable,
        columns: ["*"],
        where: "status = 1 AND reminder_type = 'repeat'"
    });
    const res3 = await VaultDB.dbExecute(q3.sql, q3.values);
    for(let i = 0; i < res3.rows.length; i++) {
        const reminder = res3.rows.item(i);
        const nextTs = get_next_display_ts(reminder);
        if(nextTs && nextTs !== Number(reminder.next_trigger_at)) {
            const {sql, values} = VaultDB.buildUpdate(rtable, {next_trigger_at: nextTs, updated_at: now}, {id: reminder.id});
            await VaultDB.dbExecute(sql, values);
            hasChanges = true;

            if(reminder.repeat_interval === "monthly" || reminder.repeat_interval === "yearly") {
                await apply_rm_schedule({ ...reminder, next_trigger_at: nextTs }, true);
            }
        }
    }

    if(hasChanges) {
        refresh_dashboard_action("reminder");
    }
}

/*function get_next_display_ts(reminder) {
    const now = Date.now();
    const base = reminder.reminder_at;
    let d = new Date(Number(base));
    let guard = 0;
    let max_guard = 100;

    switch(reminder.repeat_interval) {
        case "daily":
            while(d.getTime() <= now && guard++ < max_guard)
                d.setDate(d.getDate() + 1);
            break;
        case "weekdays":
            while((d.getTime() <= now || d.getDay() === 0 || d.getDay() === 6) && guard++ < max_guard)
                d.setDate(d.getDate() + 1);
            break;
        case "weekends":
            while((d.getTime() <= now || (d.getDay() !== 0 && d.getDay() !== 6)) && guard++ < max_guard)
                d.setDate(d.getDate() + 1);
            break;
        case "weekly":
            while(d.getTime() <= now && guard++ < max_guard)
                d.setDate(d.getDate() + 7);
            break;
        case "monthly":
            while(d.getTime() <= now && guard++ < max_guard)
                d.setMonth(d.getMonth() + 1);
            break;
        case "yearly":
            while(d.getTime() <= now && guard++ < max_guard)
                d.setFullYear(d.getFullYear() + 1);
            break;
        default:
            return null;
    }

    if(guard >= max_guard) {
        console.error("get_next_display_ts: loop guard hit for reminder id", reminder.id);
        return null;
    }

    const nextTs = d.getTime();
    if(reminder.repeat_end_date && nextTs > Number(reminder.repeat_end_date)) {
        return null;
    }
    return nextTs;
}*/


function get_next_display_ts(reminder) {
    const now = Date.now();
    const base = Number(reminder.reminder_at);

    if(!base) return null;

    let d = new Date(base);
    let guard = 0;
    const max_guard = 500;

    const interval = reminder.repeat_interval;

    if(interval === "hourly") {
        const hourMs = 60 * 60 * 1000;
        let next = base > now ? base : base + Math.ceil((now - base) / hourMs) * hourMs;
        if(reminder.repeat_end_date && next > Number(reminder.repeat_end_date)) return null;
        return next;
    }

    while(d.getTime() <= now && guard++ < max_guard) {

        switch(interval) {

            case "daily":
                d.setDate(d.getDate() + 1);
                break;

            /*case "weekdays":
                d.setDate(d.getDate() + 1);
                while(d.getDay() === 0 || d.getDay() === 6) {
                    d.setDate(d.getDate() + 1);
                }
                break;

            case "weekends":
                d.setDate(d.getDate() + 1);
                while(d.getDay() !== 0 && d.getDay() !== 6) {
                    d.setDate(d.getDate() + 1);
                }
                break;*/

            case "weekly":
                d.setDate(d.getDate() + 7);
                break;

            case "monthly": {
                const day = d.getDate();
                d.setDate(1);
                d.setMonth(d.getMonth() + 1);

                const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
                d.setDate(Math.min(day, lastDay));
                break;
            }

            case "yearly":
                d.setFullYear(d.getFullYear() + 1);
                break;

            default:
                return null;
        }
    }

    if(guard >= max_guard) {
        //console.error("get_next_display_ts: loop guard hit", reminder);
        return null;
    }

    const nextTs = d.getTime();
    if(reminder.repeat_end_date && nextTs > Number(reminder.repeat_end_date)) {
        return null;
    }

    return nextTs;
}

async function get_rm_by_id(id) {
    if(!id) {
        return null;
    }

    if(_MODE_ === "dev") {
        const all = get_test_data();
        return all.find(r => r.id == id) || null;
    }

    const {sql, values} = VaultDB.buildSelect({table:VaultMateConfig.tables.reminders, columns:["*"], where:{id:id}, limit:1});
    const res = await VaultDB.dbExecute(sql, values);

    if(res.rows.length > 0) {
        const row = res.rows.item(0);
        if(row.addl_json) {
            try {
                row.addl_json = JSON.parse(row.addl_json);
            } catch (e) {
                row.addl_json = null;
            }
        }
        return row;
    }
    return null;
}

async function get_vault_reminder(vault_id, opts = {}) {
    if(_MODE_ == "dev") {
        return null;
    }
    const q = VaultDB.buildSelect({table:VaultMateConfig.tables.reminders, columns:["id"], where:{vault_id:vault_id}, limit:1});
    const res = await VaultDB.dbExecute(q.sql, q.values, opts);
    return res.rows.length ? res.rows.item(0) : null;
}

async function delete_rm_with_notification(rm_id, from_vault = 0) {
    await cancel_rm_notification(rm_id);

    //console.log("cancel rm notification");

    const {sql, values} = VaultDB.buildDelete(VaultMateConfig.tables.reminders, {id:Number(rm_id)});
    await VaultDB.dbExecute(sql, values); 

    if(from_vault == 1) {
        refresh_dashboard_action("reminder");
    }
}

async function sync_vault_reminder({vault_id, slug, category_fields, title, rem_enabled, rem_before, notes}) {
    const cfg = category_config[slug];
    if (!cfg || !cfg.expiryField) return;

    const expiry_date = category_fields[cfg.expiryField];
    const existing = await get_vault_reminder(vault_id);

    if(!rem_enabled || !expiry_date) {
        if(existing && existing.id) {      
            await delete_rm_with_notification(existing.id, 1)
        }
        return;
    }

    const reminderAt = new Date(`${expiry_date} ${VaultMateConfig.default.vault_rm_def_time}`).getTime() - rem_before * 86400 * 1000;
    const rm_data = {
        category:VaultMateConfig.default.vault_rem_category,
        title: title,
        description: notes || "",
        reminder_type: "onetime",
        reminder_at: reminderAt,
        status: 1,
        vault_id: vault_id
    };

    //console.log("rm_data", rm_data);

    const res = await manage_reminder({id: existing?.id || null, data: rm_data});
    if(res.success) {
        await apply_rm_schedule(res.reminder, !!existing);
        refresh_dashboard_action("reminder");
    }
}

function handle_dashboard_action_rm(action) {    
    if(typeof action === "string") {
        const parts = action.split("|");
        if(parts.length === 2) {
            set_rm_filter(parts[0], parts[1]);
        }
        return;
    }
    if(typeof action === "object") {
        Object.keys(action).forEach(key => {
            set_rm_filter(key, action[key]);
        });
    }
}

function set_rm_filter(key, value) {

    if(!rm_filters.hasOwnProperty(key)) {
        
        /*if(key == "due_days" && value != "") {
            const now = Date.now();
            const next30 = now + (value * 24 * 60 * 60 * 1000);

            rm_filters.time = "upcoming";
            dgi("vm_reminder_time").value = "upcoming";
            rm_filters.date_range = {from: now, to: next30};

            document.querySelectorAll("#rm_date_range .bottom_sheet_tags").forEach(tag => {
                tag.classList.toggle("active", tag.dataset.range === value);
            });
            dgi("rm_custom_date_range").style.display = "none";           
        }*/
        return;
    }
    const expectedType = RM_FILTER_SCHEMA[key];
    if(expectedType === "array") {
        if (Array.isArray(value)) {
            rm_filters[key] = value.slice();
        } else if (value !== null && value !== undefined) {
            rm_filters[key] = [ value ];
        } else {
            rm_filters[key] = [];
        }
    } else if (expectedType === "object") {
        if (typeof value === "object" && value !== null) {
            rm_filters[key] = Object.assign({}, value);
        } else {
            rm_filters[key] = null;
        }
    } else {
        rm_filters[key] = value;
    }

    if(key === "sort") {
        document.querySelectorAll(".rm_sort").forEach(choice => {
            choice.classList.toggle("active", choice.dataset.value === String(value));
        });
    }
    dgi("vm_rm_clearFilter").disabled = !rm_filter_applied(rm_filters);    
}

function populate_rm_filter_categories() {
    const html = rm_categories().map(cat => `
        <label class="vm_category_item">
            <input type="checkbox" value="${cat.id}">
            <span class="vm_category_icon">
                <i class="fa ${cat.icon}"></i>
            </span>
            <span class="vm_category_name">${cat.label}</span>
        </label>
    `).join("");
    dgi("rm_category_checklist").innerHTML = html;
}

function rm_select_all() {
  document.querySelectorAll('#rm_category_checklist input[type="checkbox"]').forEach(cb => cb.checked = true);
}

function rm_clear_all() {
  document.querySelectorAll('#rm_category_checklist input[type="checkbox"]').forEach(cb => cb.checked = false);
}


async function check_device_rm_status(reminder) {
    if(!window.cordova || !cordova.plugins || !cordova.plugins.notification) {        
        return;
    }
    const row = dgi("vm_rm_device_row");
    const valEl = dgi("vm_rm_device_value");
    if (!row || !valEl) {        
        return;
    }
    row.style.display = "flex";

    const isScheduled = await new Promise(resolve => {
        cordova.plugins.notification.local.isScheduled(reminder.id, resolve);
    });

    if (reminder.status === 0) {
        if (isScheduled) {
            valEl.innerHTML = `<span class="vm-badge-warning"><i class="fas fa-exclamation-triangle"></i> Still active on device</span>
                <button class="vm-btn-inline" id="vm_rm_device_fix">Clear</button>`;
            dgi("vm_rm_device_fix").onclick = async () => {
                await cancel_rm_notification(reminder.id);
                valEl.innerHTML = `<span class="vm-badge-ok"><i class="fas fa-check-circle"></i> Cleared</span>`;
            };
        } else {
            valEl.innerHTML = `<span class="vm-badge-ok"><i class="fas fa-check-circle"></i> Completed</span>`;
        }
        return;
    }

    if (isScheduled) {
        valEl.innerHTML = `<span class="vm-badge-ok"><i class="fas fa-check-circle"></i> Scheduled</span>`;
    } else {
        valEl.innerHTML = `<span class="vm-badge-warning">Not found on device</span>
            <button class="vm-btn-inline" id="vm_rm_device_fix">Sync Now</button>`;
        dgi("vm_rm_device_fix").onclick = async () => {
            valEl.textContent = "Syncing...";
            const ok = await apply_rm_schedule(reminder, true);
            valEl.innerHTML = ok
                ? `<span class="vm-badge-ok"><i class="fas fa-check-circle"></i> Scheduled</span>`
                : `<span class="vm-badge-danger"><i class="fas fa-exclamation-circle"></i> Failed — check notification permissions</span>`;
        };
    }
}


function get_test_data() {
    const now = Date.now();
    const day = 24 * 60 * 60 * 1000;

    return [

        /* =========================
           UPCOMING – REPEAT
        ========================== */

        {
            id: 1,
            title: "Daily Medicine",
            category: "health",
            description: "After breakfast",
            reminder_type: "repeat",
            reminder_at: now + 2 * 60 * 60 * 1000,
            repeat_interval: "daily",
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 2,
            title: "Team Meeting",
            category: "work",
            description: "Weekly sync call",
            reminder_type: "repeat",
            reminder_at: now + day,
            repeat_interval: "weekly",
            repeat_end_date: now + 90 * day,
            status: 1,
            vault_id: 1
        },

        {
            id: 3,
            title: "Netflix Subscription",
            category: "subscriptions",
            description: "Auto debit",
            reminder_type: "repeat",
            reminder_at: now + 3 * day,
            repeat_interval: "monthly",
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 4,
            title: "Mom's Birthday",
            category: "family",
            description: "Wish & gift",
            reminder_type: "repeat",
            reminder_at: now + 10 * day,
            repeat_interval: "yearly",
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        /* =========================
           UPCOMING – ONE TIME
        ========================== */

        {
            id: 5,
            title: "Pay Electricity Bill",
            category: "bills",
            description: "Before due date",
            reminder_type: "onetime",
            reminder_at: now + 5 * 60 * 60 * 1000,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 6,
            title: "Flight to Chennai",
            category: "travel",
            description: "Check-in online",
            reminder_type: "onetime",
            reminder_at: now + 7 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 7,
            title: "Car Service",
            category: "vehicle",
            description: "Oil & general service",
            reminder_type: "onetime",
            reminder_at: now + 12 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: 202
        },

        /* =========================
           EXPIRED – REPEAT (ACTIVE)
        ========================== */

        {
            id: 8,
            title: "Morning Walk",
            category: "personal",
            description: "30 mins walk",
            reminder_type: "repeat",
            reminder_at: now - 2 * 60 * 60 * 1000,
            repeat_interval: "daily",
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 9,
            title: "Credit Card Due",
            category: "creditcards",
            description: "Minimum due",
            reminder_type: "repeat",
            reminder_at: now - 3 * day,
            repeat_interval: "monthly",
            repeat_end_date: null,
            status: 1,
            vault_id: 303
        },

        /* =========================
           EXPIRED – ONE TIME
        ========================== */

        {
            id: 10,
            title: "Doctor Appointment",
            category: "health",
            description: "Dental checkup",
            reminder_type: "onetime",
            reminder_at: now - day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        {
            id: 11,
            title: "Office Presentation",
            category: "work",
            description: "Project demo",
            reminder_type: "onetime",
            reminder_at: now - 2 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: null
        },

        /* =========================
           COMPLETED – ONE TIME
        ========================== */

        {
            id: 12,
            title: "Shopping Groceries",
            category: "shopping",
            description: "Milk, rice, fruits",
            reminder_type: "onetime",
            reminder_at: now - 3 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 0,
            vault_id: null
        },

        {
            id: 13,
            title: "Insurance Document Upload",
            category: "documents",
            description: "Uploaded to vault",
            reminder_type: "onetime",
            reminder_at: now - 5 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 0,
            vault_id: 404
        },

        /* =========================
           COMPLETED – REPEAT
        ========================== */

        {
            id: 14,
            title: "Loan EMI",
            category: "loans",
            description: "Paid via bank",
            reminder_type: "repeat",
            reminder_at: now - 10 * day,
            repeat_interval: "monthly",
            repeat_end_date: null,
            status: 0,
            vault_id: 505
        },

        {
            id: 15,
            title: "Festival Event",
            category: "events",
            description: "Temple visit",
            reminder_type: "repeat",
            reminder_at: now - 365 * day,
            repeat_interval: "yearly",
            repeat_end_date: null,
            status: 0,
            vault_id: null
        },

        /* =========================
           OTHER / EDGE
        ========================== */

        {
            id: 16,
            title: "Misc Reminder",
            category: "other",
            description: "",
            reminder_type: "onetime",
            reminder_at: now + 30 * day,
            repeat_interval: null,
            repeat_end_date: null,
            status: 1,
            vault_id: null
        }
    ];
}
