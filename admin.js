const API_URL = "../Back/admin_api.php";

// 1. التحقق من الصلاحية وجلب البيانات عند فتح الصفحة
async function initAdmin() {
    // جلب المستخدمين
    fetchUsers();
    // جلب الإعدادات
    fetchSettings();
}

// ================= إدارة المستخدمين =================
async function fetchUsers() {
    const tbody = document.getElementById("usersTableBody");
    try {
        const res = await fetch(`${API_URL}?action=get_users`, { credentials: "same-origin" });
        if (res.status === 403) { window.location.replace("dashboard.html"); return; }
        
        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        if (data.users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-secondary">لا يوجد مستخدمون.</td></tr>';
            return;
        }

        tbody.innerHTML = data.users.map(user => `
            <tr>
                <td>${user.user_id}</td>
                <td>${esc(user.full_name)}</td>
                <td>${esc(user.email)}</td>
                <td>
                    ${user.role === 'admin' 
                        ? '<span class="badge bg-danger">مدير</span>' 
                        : '<span class="badge bg-secondary">مستخدم</span>'}
                </td>
                <td>${user.created_at.split(' ')[0]}</td>
                <td>
                    ${user.role === 'admin' 
                        ? '<button class="btn btn-sm btn-outline-secondary" disabled>محمي</button>' 
                        : `<button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.user_id})">حذف</button>`}
                </td>
            </tr>
        `).join('');
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">${error.message}</td></tr>`;
    }
}

async function deleteUser(id) {
    if (!confirm("هل أنت متأكد من حذف هذا المستخدم نهائياً؟ (سيتم حذف جميع سيرته وملفاته)")) return;

    try {
        const res = await fetch(`${API_URL}?action=delete_user`, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ user_id: id })
        });
        const data = await res.json();
        alert(data.message);
        if (data.success) fetchUsers();
    } catch (err) {
        alert("خطأ في الاتصال بالخادم.");
    }
}

// ================= إعدادات الذكاء الاصطناعي =================
async function fetchSettings() {
    try {
        const res = await fetch(`${API_URL}?action=get_settings`, { credentials: "same-origin" });
        const data = await res.json();
        if (data.success && data.settings.ai_model) {
            document.getElementById("aiModelInput").value = data.settings.ai_model;
        }
    } catch (err) {
        console.error("فشل جلب الإعدادات", err);
    }
}

document.getElementById("aiSettingsForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const modelName = document.getElementById("aiModelInput").value.trim();
    const msgBox = document.getElementById("settingsMessage");
    
    msgBox.textContent = "جارٍ الحفظ...";
    msgBox.className = "mt-3 small text-info";

    try {
        const res = await fetch(`${API_URL}?action=save_settings`, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ai_model: modelName })
        });
        const data = await res.json();
        
        if (data.success) {
            msgBox.textContent = data.message;
            msgBox.className = "mt-3 small text-success";
        } else {
            throw new Error(data.message);
        }
    } catch (err) {
        msgBox.textContent = err.message || "حدث خطأ.";
        msgBox.className = "mt-3 small text-danger";
    }
});

// دالة مساعدة للحماية من XSS
function esc(str) {
    return String(str ?? "").replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

// تشغيل
initAdmin();