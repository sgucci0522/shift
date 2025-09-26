document.addEventListener('DOMContentLoaded', function() {
    // --- DOM Elements ---
    const scheduleContainer = document.getElementById('schedule-container');
    const monthYearDisplay = document.getElementById('current-month-year');
    const prevMonthBtn = document.getElementById('prev-month-btn');
    const nextMonthBtn = document.getElementById('next-month-btn');
    const firstHalfBtn = document.getElementById('first-half-btn');
    const secondHalfBtn = document.getElementById('second-half-btn');
    
    const modal = document.getElementById('add-shift-modal');
    const addShiftBtn = document.getElementById('add-shift-btn');
    const deleteShiftBtn = document.getElementById('delete-shift-btn');
    const closeBtn = document.querySelector('.close-btn');
    const addShiftForm = document.getElementById('add-shift-form');
    const employeeSelect = document.getElementById('employee');
    const errorDiv = document.getElementById('form-error-message');

    // Hide add shift button for employees
    if (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'employee') {
        addShiftBtn.style.display = 'none';
    }

    // --- State ---
    let currentDate = new Date();
    // Determine initial period based on today's date
    let currentPeriod = (new Date().getDate() <= 16) ? 'first' : 'second';

    // --- Helper Functions ---
    function updatePeriodButtons() {
        if (currentPeriod === 'first') {
            firstHalfBtn.classList.add('active');
            secondHalfBtn.classList.remove('active');
        } else {
            secondHalfBtn.classList.add('active');
            firstHalfBtn.classList.remove('active');
        }
    }

    // --- Data Fetching ---
    async function fetchApiData(action) {
        try {
            const response = await fetch(`api.php?action=${action}`);
            if (!response.ok) throw new Error(`Network response was not ok for ${action}`);
            const result = await response.json();
            if (result.status === 'success') return result.data;
            throw new Error(result.message);
        } catch (error) {
            console.error(`Failed to fetch ${action}:`, error);
            return [];
        }
    }

    // --- Rendering ---
    async function renderScheduleTable() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth(); // 0-indexed

        monthYearDisplay.textContent = `${year}年 ${month + 1}月`;
        scheduleContainer.innerHTML = '<table></table>'; // Clear and set up table
        const table = scheduleContainer.querySelector('table');

        // Fetch data in parallel
        const [shifts, employees] = await Promise.all([
            fetchApiData('get_shifts'),
            fetchApiData('get_employees')
        ]);

        // Process shifts into a more usable structure: { empId: { 'YYYY-MM-DD': {shiftObject} } }
        const shiftsByEmployee = {};
        shifts.forEach(shift => {
            if (!shiftsByEmployee[shift.employee_id]) {
                shiftsByEmployee[shift.employee_id] = {};
            }
            shiftsByEmployee[shift.employee_id][shift.shift_date] = shift; // Store the whole object
        });

        // Determine date range for the table header
        const startDate = (currentPeriod === 'first') ? 1 : 17;
        const lastDayOfMonth = new Date(year, month + 1, 0).getDate();
        const endDate = (currentPeriod === 'first') ? 16 : lastDayOfMonth;

        // --- Build Table Header ---
        let headerHtml = '<thead><tr><th>従業員</th>';
        for (let day = startDate; day <= endDate; day++) {
            headerHtml += `<th>${day}日</th>`;
        }
        headerHtml += '</tr></thead>';

        // --- Build Table Body ---
        let bodyHtml = '<tbody>';
        employees.forEach(emp => {
            bodyHtml += `<tr><td>${emp.name}</td>`;
            const employeeShifts = shiftsByEmployee[emp.id] || {};
            for (let day = startDate; day <= endDate; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const shift = employeeShifts[dateStr]; // Get the whole shift object
                if (shift) {
                    bodyHtml += `<td><div class="shift-entry" 
                                        data-shift-id="${shift.id}" 
                                        data-employee-id="${shift.employee_id}"
                                        data-date="${shift.shift_date}"
                                        data-start-time="${shift.start_time}"
                                        data-end-time="${shift.end_time}">
                                        ${shift.start_time.substring(0, 5)}-${shift.end_time.substring(0, 5)}
                                     </div></td>`;
                } else {
                    bodyHtml += '<td></td>'; // Empty cell
                }
            }
            bodyHtml += '</tr>';
        });
        bodyHtml += '</tbody>';

        table.innerHTML = headerHtml + bodyHtml;
    }

    // --- Event Listeners ---
    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderScheduleTable();
    });

    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderScheduleTable();
    });

    firstHalfBtn.addEventListener('click', () => {
        currentPeriod = 'first';
        updatePeriodButtons();
        renderScheduleTable();
    });

    secondHalfBtn.addEventListener('click', () => {
        currentPeriod = 'second';
        updatePeriodButtons();
        renderScheduleTable();
    });

    scheduleContainer.addEventListener('click', (event) => {
        const shiftElement = event.target.closest('.shift-entry');
        if (shiftElement && (typeof USER_ROLE !== 'undefined' && USER_ROLE === 'admin')) { // Only admin can edit
            const shiftData = {
                id: shiftElement.dataset.shiftId,
                employee_id: shiftElement.dataset.employeeId,
                date: shiftElement.dataset.date,
                start_time: shiftElement.dataset.startTime,
                end_time: shiftElement.dataset.endTime,
            };
            openModal(shiftData);
        }
    });

    // --- Modal Logic ---
    const openModal = async (shift = null) => {
        // Clear previous error messages
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';

        // Always fetch employees to ensure the list is up-to-date
        try {
            const employees = await fetchApiData('get_employees');
            employeeSelect.innerHTML = '';
            employees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = emp.name;
                employeeSelect.appendChild(option);
            });
        } catch (error) {
            console.error('Could not fetch employees for modal', error);
        }

        const modalTitle = modal.querySelector('h2');
        addShiftForm.reset(); // Reset form first

        if (shift) { // Edit mode
            modalTitle.textContent = 'シフトを編集';
            addShiftForm.elements.shift_id.value = shift.id;
            addShiftForm.elements.employee.value = shift.employee_id;
            addShiftForm.elements['shift-date'].value = shift.date;
            
            const [startHour, startMinute] = shift.start_time.split(':');
            addShiftForm.elements['start-hour'].value = startHour;
            addShiftForm.elements['start-minute'].value = startMinute;

            const [endHour, endMinute] = shift.end_time.split(':');
            addShiftForm.elements['end-hour'].value = endHour;
            addShiftForm.elements['end-minute'].value = endMinute;

            deleteShiftBtn.style.display = 'block'; // Show delete button
        } else { // Add mode
            modalTitle.textContent = '新しいシフト';
            addShiftForm.elements.shift_id.value = '';
            deleteShiftBtn.style.display = 'none'; // Hide delete button
        }

        modal.style.display = 'block';
    };

    const closeModal = () => {
        modal.style.display = 'none';
        addShiftForm.reset();
    };

    addShiftBtn.addEventListener('click', () => openModal()); // Open in "add" mode
    closeBtn.addEventListener('click', closeModal);
    window.addEventListener('click', (event) => {
        if (event.target == modal) closeModal();
    });

    addShiftForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Clear previous error messages on new submission
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';

        const shiftId = addShiftForm.elements.shift_id.value;
        const shiftData = {
            employee_id: addShiftForm.elements.employee.value,
            shift_date: addShiftForm.elements['shift-date'].value,
            start_time: `${addShiftForm.elements['start-hour'].value}:${addShiftForm.elements['start-minute'].value}:00`,
            end_time: `${addShiftForm.elements['end-hour'].value}:${addShiftForm.elements['end-minute'].value}:00`,
        };

        let url = 'api.php';
        let method = 'POST';

        if (shiftId) {
            shiftData.shift_id = shiftId;
            method = 'PUT';
        }

        try {
            const response = await fetch(url, { 
                method: method, 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(shiftData)
            });
            
            const result = await response.json();

            if (response.ok) { // Check if response status is 200-299
                closeModal();
                renderScheduleTable(); // Refresh table
            } else {
                // Display error message from server inside the modal
                errorDiv.textContent = result.message || 'An unknown error occurred.';
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            // Display network or other unexpected errors
            errorDiv.textContent = '保存中にエラーが発生しました。ネットワーク接続を確認してください。';
            errorDiv.style.display = 'block';
        }
    });

    deleteShiftBtn.addEventListener('click', async () => {
        const shiftId = addShiftForm.elements.shift_id.value;
        if (!shiftId) return;

        if (confirm('このシフトを本当に削除しますか？')) {
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';

            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ shift_id: shiftId })
                });

                const result = await response.json();

                if (response.ok) {
                    closeModal();
                    renderScheduleTable();
                } else {
                    errorDiv.textContent = result.message || '削除中にエラーが発生しました。';
                    errorDiv.style.display = 'block';
                }
            } catch (error) {
                errorDiv.textContent = '削除中にエラーが発生しました。ネットワーク接続を確認してください。';
                errorDiv.style.display = 'block';
            }
        }
    });

    // --- Initial Render ---
    updatePeriodButtons(); // Set initial active button state
    renderScheduleTable();
});
