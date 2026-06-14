document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById("popupOverlay");
    const openBtn = document.getElementById("openPopup");
    const closeBtn = document.getElementById("closePopup");

    function openPopup() {
        if (overlay) overlay.style.display = "flex";
    }

    function closePopup() {
        if (overlay) overlay.style.display = "none";
    }

    if (openBtn) openBtn.addEventListener("click", openPopup);
    if (closeBtn) closeBtn.addEventListener("click", closePopup);

    if (overlay) {
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) closePopup();
        });
    }

    const repeatable = document.getElementById("taskRepeatable");
    const repeatOptions = document.getElementById("repeatOptions");

    if (repeatable && repeatOptions) {
        function syncRepeatUI() {
            const on = repeatable.checked;
            repeatOptions.classList.toggle("open", on);
            repeatOptions.setAttribute("aria-hidden", String(!on));
            if (!on) {
                repeatOptions.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
            }
        }
        repeatable.addEventListener("change", syncRepeatUI);
        syncRepeatUI();
    }

    const editOverlay = document.getElementById("editPopupOverlay");
    const editCloseBtn = document.getElementById("closeEditPopup");

    function changeEditSelects(mode) {
        const select = document.getElementById("editTaskStatus");
        if (!select) return;
        if (mode !== "normal") {
            select.options[1].innerHTML = "Disabled";
            if (select.options[2]) select.options[2].style.visibility = "hidden";
            if (select.options[3]) select.options[3].style.visibility = "hidden";
        } else {
            select.options[1].innerHTML = "Inactive";
            if (select.options[2]) select.options[2].style.visibility = "visible";
            if (select.options[3]) select.options[3].style.visibility = "visible";
        }
    }

    window.openEditForm = function(task) {
        const editTaskId = document.getElementById("editTaskId");
        const editTaskName = document.getElementById("editTaskName");
        const editTaskDescription = document.getElementById("editTaskDescription");
        const taskEditUse = document.getElementById("taskEditUse");
        const editTaskDate = document.getElementById("editTaskDate");
        const editTaskendDate = document.getElementById("editTaskendDate");
        const editTaskStatus = document.getElementById("editTaskStatus");
        const editTaskPrivate = document.getElementById("editTaskPrivate");
        const recurrenceFields = document.getElementById("recurrenceFields");
        const endDateContainer = document.getElementById("endDateContainer");

        if (editTaskId) editTaskId.value = task.id ?? "";
        if (editTaskName) editTaskName.value = task.name ?? "";
        if (editTaskDescription) editTaskDescription.value = task.description ?? "";
        if (taskEditUse) taskEditUse.value = task.user;

        changeEditSelects("normal");

        let rawDate = task.start_date ?? "";
        if (rawDate.includes(" ")) {
            rawDate = rawDate.replace(" ", "T");
        } else if (rawDate.length === 10) {
            rawDate += "T00:00";
        }

        let rawendDate = task.end_date == null ? "" : task.end_date;
        if (rawendDate.includes(" ")) {
            rawendDate = rawendDate.replace(" ", "T");
        } else if (rawendDate.length === 10) {
            rawendDate += "T00:00";
        }

        if (editTaskDate) editTaskDate.value = rawDate.slice(0, 16);
        if (editTaskendDate) editTaskendDate.value = rawendDate ? rawendDate.slice(0, 16) : "";
        if (editTaskStatus) editTaskStatus.value = task.status ?? (task.active ? "A" : "I");
        if (editTaskPrivate) editTaskPrivate.checked = Number(task.private) === 1;

        const isRecurrent = task.hasOwnProperty("weekday") || task.hasOwnProperty("daily");

        if (recurrenceFields) {
            if (isRecurrent) {
                recurrenceFields.style.display = "block";
                changeEditSelects("recurrent");

                const dailyCheckbox = document.getElementById("taskDaily");
                const weekdayRadios = document.querySelectorAll('input[name="taskWeekday"]');
                weekdayRadios.forEach(radio => radio.checked = false);

                if (dailyCheckbox) dailyCheckbox.checked = Number(task.daily) === 1;

                if (!dailyCheckbox?.checked && task.weekday) {
                    weekdayRadios.forEach(radio => {
                        if (Number(radio.value) === Number(task.weekday)) radio.checked = true;
                    });
                }

                const typeInput = document.querySelector('#editPopupForm input[name="type"]');
                if (typeInput) typeInput.value = "editRecurrent";
            } else {
                recurrenceFields.style.display = "none";

                const dailyCheckbox = document.getElementById("taskDaily");
                if (dailyCheckbox) dailyCheckbox.checked = false;
                document.querySelectorAll('input[name="taskWeekday"]').forEach(radio => radio.checked = false);

                const typeInput = document.querySelector('#editPopupForm input[name="type"]');
                if (typeInput) typeInput.value = "editTask";
            }
        }

        if (editOverlay) editOverlay.style.display = "flex";
        if (endDateContainer) endDateContainer.style.display = isRecurrent ? "none" : "block";
    };

    const dailyCheckbox = document.getElementById("taskDaily");
    const weekdayRadios = document.querySelectorAll('input[name="taskWeekday"]');

    if (dailyCheckbox) {
        dailyCheckbox.addEventListener("change", function() {
            if (this.checked) weekdayRadios.forEach(r => r.checked = false);
        });
    }

    weekdayRadios.forEach(radio => {
        radio.addEventListener("change", function() {
            if (this.checked && dailyCheckbox) dailyCheckbox.checked = false;
        });
    });

    function closeEditPopup() {
        if (editOverlay) editOverlay.style.display = "none";
    }

    if (editCloseBtn) editCloseBtn.addEventListener("click", closeEditPopup);

    if (editOverlay) {
        editOverlay.addEventListener("click", (e) => {
            if (e.target === editOverlay) closeEditPopup();
        });
    }

    // Single merged keydown handler (replaces the two duplicate ones)
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
            closePopup();
            closeEditPopup();
        }
    });

    let showrec = true;
    const showRecurrentBtn = document.getElementById('showRecurrent');

    if (showRecurrentBtn) {
        showRecurrentBtn.onclick = function() {
            showrec = !showrec;
            const tab1 = document.getElementById('userNotrec');
            const tab2 = document.getElementById('otherNotrec');
            const tab3 = document.getElementById('userRec');
            const tab4 = document.getElementById('otherRec');
            if (tab1) tab1.style.display = showrec ? '' : 'none';
            if (tab2) tab2.style.display = showrec ? '' : 'none';
            if (tab3) tab3.style.display = showrec ? 'none' : '';
            if (tab4) tab4.style.display = showrec ? 'none' : '';
        };
    }

    function handleFormSubmit(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            fetch('/api/server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) window.location.href = '/tasks.php';
                else alert('Error: ' + (result.error || 'Operation failed'));
            })
            .catch(err => alert('Error: ' + err.message));
        });
    }

    const popupForm = document.getElementById('popupForm');
    const editPopupForm = document.getElementById('editPopupForm');
    if (popupForm) handleFormSubmit(popupForm);
    if (editPopupForm) handleFormSubmit(editPopupForm);

    document.addEventListener('change', function(e) {
        if (e.target.name === 'taskStatus' && e.target.closest('form.statusForm')) {
            const form = e.target.closest('form');
            const data = Object.fromEntries(new FormData(form).entries());
            fetch('/api/server.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) window.location.href = '/tasks.php';
                else alert('Error: ' + (result.error || 'Operation failed'));
            })
            .catch(err => alert('Error: ' + err.message));
        }
    });
});