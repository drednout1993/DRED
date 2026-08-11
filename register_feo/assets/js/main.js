/**
 * Основной JavaScript системы "Реестры ФЭО"
 */

// Toast уведомления
function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    if (!container) return;

    const toastId = 'toast-' + Date.now();
    const bgClass = {
        'success': 'bg-success',
        'error': 'bg-danger',
        'warning': 'bg-warning',
        'info': 'bg-primary'
    }[type] || 'bg-primary';

    const icon = {
        'success': 'bi-check-circle',
        'error': 'bi-exclamation-circle',
        'warning': 'bi-exclamation-triangle',
        'info': 'bi-info-circle'
    }[type] || 'bi-info-circle';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icon} me-2"></i>${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();

    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Переключение темы
function toggleTheme() {
    const body = document.body;
    const isDark = body.classList.contains('dark-mode');
    
    if (isDark) {
        body.classList.remove('dark-mode');
        localStorage.setItem('theme', 'light');
    } else {
        body.classList.add('dark-mode');
        localStorage.setItem('theme', 'dark');
    }
}

// Инициализация темы при загрузке
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
});

// Drag-and-drop для загрузки файлов
function initDropzone(dropzoneElement, fileInputElement) {
    const dropzone = document.querySelector(dropzoneElement);
    const fileInput = document.querySelector(fileInputElement);

    if (!dropzone || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        handleFiles(files, fileInput);
    }, false);

    dropzone.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files, fileInput);
    });
}

function handleFiles(files, fileInput) {
    const fileList = fileInput.parentElement.querySelector('.file-list');
    if (!fileList) return;

    ([...files]).forEach(file => {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            showToast(`Файл "${file.name}" имеет недопустимый формат`, 'error');
            return;
        }

        const fileSizeMB = file.size / (1024 * 1024);
        if (fileSizeMB > 10) {
            showToast(`Файл "${file.name}" превышает 10 МБ`, 'error');
            return;
        }

        addFileToList(file, fileList);
    });
}

function addFileToList(file, fileList) {
    const itemId = 'file-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    const fileItem = document.createElement('div');
    fileItem.className = 'file-item d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded';
    fileItem.id = itemId;
    fileItem.innerHTML = `
        <div>
            <i class="bi bi-file-earmark me-2"></i>
            <span>${escapeHtml(file.name)}</span>
            <small class="text-muted">(${(file.size / 1024).toFixed(1)} КБ)</small>
        </div>
        <button type="button" class="btn btn-sm btn-danger" onclick="removeFile('${itemId}')">
            <i class="bi bi-trash"></i>
        </button>
    `;
    fileList.appendChild(fileItem);

    // Сохраняем файл в data-атрибуте для последующей отправки
    if (!fileList.dataset.files) {
        fileList.dataset.files = '[]';
    }
    const filesArray = JSON.parse(fileList.dataset.files);
    filesArray.push({
        id: itemId,
        file: file,
        name: file.name,
        size: file.size,
        type: file.type
    });
    fileList.dataset.files = JSON.stringify(filesArray);
}

function removeFile(itemId) {
    const fileList = document.querySelector('.file-list');
    if (!fileList) return;

    const item = document.getElementById(itemId);
    if (item) {
        item.remove();
    }

    let filesArray = JSON.parse(fileList.dataset.files || '[]');
    filesArray = filesArray.filter(f => f.id !== itemId);
    fileList.dataset.files = JSON.stringify(filesArray);
}

// Динамическая таблица позиций реестра
function initRegisterItemsTable() {
    const tableBody = document.querySelector('#registerItems tbody');
    const addButton = document.querySelector('#addItemBtn');

    if (!tableBody || !addButton) return;

    addButton.addEventListener('click', () => {
        addRegisterItemRow(tableBody);
    });

    // Обработчик удаления строк
    tableBody.addEventListener('click', (e) => {
        if (e.target.closest('.removeItemBtn')) {
            const row = e.target.closest('tr');
            row.remove();
            updateItemOrders(tableBody);
        }
    });
}

function addRegisterItemRow(tableBody, data = {}) {
    const rowCount = tableBody.querySelectorAll('tr').length + 1;
    const row = document.createElement('tr');
    row.innerHTML = `
        <td style="width: 50px;">
            <span class="item-order">${rowCount}</span>
            <input type="hidden" name="item_order[]" value="${rowCount}">
        </td>
        <td>
            <input type="text" class="form-control" name="doc_name[]" value="${data.doc_name || ''}" required placeholder="Наименование документа">
        </td>
        <td style="width: 150px;">
            <input type="text" class="form-control" name="doc_number[]" value="${data.doc_number || ''}" required placeholder="Номер">
        </td>
        <td style="width: 150px;">
            <input type="date" class="form-control" name="doc_date[]" value="${data.doc_date || ''}" required>
        </td>
        <td>
            <input type="text" class="form-control" name="contract_details[]" value="${data.contract_details || ''}" placeholder="Договор/контракт">
        </td>
        <td style="width: 200px;">
            <input type="text" class="form-control" name="notes[]" value="${data.notes || ''}" placeholder="Примечание">
        </td>
        <td style="width: 50px;">
            <button type="button" class="btn btn-sm btn-outline-danger removeItemBtn">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    tableBody.appendChild(row);
}

function updateItemOrders(tableBody) {
    const rows = tableBody.querySelectorAll('tr');
    rows.forEach((row, index) => {
        const orderSpan = row.querySelector('.item-order');
        const orderInput = row.querySelector('input[name="item_order[]"]');
        if (orderSpan) orderSpan.textContent = index + 1;
        if (orderInput) orderInput.value = index + 1;
    });
}

// Подтверждение действий
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// AJAX запросы
async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Ошибка запроса');
        }
        
        return result;
    } catch (error) {
        showToast(error.message, 'error');
        throw error;
    }
}

// Форматирование даты
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Форматирование даты и времени
function formatDateTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Статус реестра в текст
function getStatusText(status) {
    const statuses = {
        'draft': 'Черновик',
        'new': 'Новый',
        'in_review': 'На проверке',
        'revision': 'На доработке',
        'accepted': 'Принят',
        'deleted': 'Удалён'
    };
    return statuses[status] || status;
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Инициализация tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Инициализация popover
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Автозакрытие алертов
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 10000);
    });
});
