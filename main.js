
const loginTab = document.getElementById("loginTab");
const registerTab = document.getElementById("registerTab");

const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const forgotForm = document.getElementById("forgotForm");

const forgotLink = document.getElementById("forgotLink");
const backToLogin = document.getElementById("backToLogin");
const message = document.getElementById("message");

const API = "../Back/";

function showMessage(text, success = false) {
    message.textContent = text;
    message.style.color = success ? "#8ce9b0" : "#ff8b8b";
}

function showForm(formName) {
    loginForm.classList.add("d-none");
    registerForm.classList.add("d-none");
    forgotForm.classList.add("d-none");

    loginTab.classList.remove("active");
    registerTab.classList.remove("active");

    message.textContent = "";

    if (formName === "login") {
        loginForm.classList.remove("d-none");
        loginTab.classList.add("active");
    } else if (formName === "register") {
        registerForm.classList.remove("d-none");
        registerTab.classList.add("active");
    } else if (formName === "forgot") {
        forgotForm.classList.remove("d-none");
    }
}

loginTab.addEventListener("click", () => showForm("login"));
registerTab.addEventListener("click", () => showForm("register"));

forgotLink.addEventListener("click", event => {
    event.preventDefault();
    showForm("forgot");
});

backToLogin.addEventListener("click", () => showForm("login"));

async function sendRequest(file, data) {
    const response = await fetch(API + file, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        credentials: "same-origin",
        body: JSON.stringify(data)
    });

    const result = await response.json();

    if (!response.ok || !result.success) {
        throw new Error(result.message || "حدث خطأ في الطلب.");
    }

    return result;
}

// إنشاء حساب
registerForm.addEventListener("submit", async event => {
    event.preventDefault();

    const inputs = registerForm.querySelectorAll("input");
    const fullName = inputs[0].value.trim();
    const email = inputs[1].value.trim();
    const password = inputs[2].value;

    if (password.length < 8) {
        showMessage("كلمة المرور يجب أن تكون 8 أحرف على الأقل.");
        return;
    }

    const button = registerForm.querySelector('[type="submit"]');
    button.disabled = true;

    try {
        const result = await sendRequest("register.php", {
            full_name: fullName,
            email,
            password
        });

        showForm("login");
        showMessage(result.message + " سجل دخولك الآن.", true);
        registerForm.reset();

    } catch (error) {
        showMessage(error.message);
    } finally {
        button.disabled = false;
    }
});

// تسجيل الدخول
loginForm.addEventListener("submit", async event => {
    event.preventDefault();

    const inputs = loginForm.querySelectorAll("input");
    const email = inputs[0].value.trim();
    const password = inputs[1].value;

    const button = loginForm.querySelector('[type="submit"]');
    button.disabled = true;

    try {
        const result = await sendRequest("login.php", {
            email,
            password
        });

        showMessage(result.message, true);

        window.location.href = "dashboard.html";

    } catch (error) {
        showMessage(error.message);
    } finally {
        button.disabled = false;
    }
});

// استعادة كلمة المرور: لم تُبرمج بعد
forgotForm.addEventListener("submit", event => {
    event.preventDefault();
    showMessage("ميزة استعادة كلمة المرور ستُضاف لاحقًا.");
});

// رفع السيرة: ما زال تجريبيًا
const uploadBtn = document.getElementById("uploadBtn");

if (uploadBtn) {
    uploadBtn.addEventListener("click", () => {
        const fileInput = document.getElementById("resumeFile");
        const uploadMessage = document.getElementById("uploadMessage");
        const file = fileInput.files[0];

        if (!file) {
            uploadMessage.textContent = "الرجاء اختيار ملف أولاً.";
            return;
        }

        const allowedExtensions = [".pdf", ".doc", ".docx"];
        const extension = file.name.substring(
            file.name.lastIndexOf(".")
        ).toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            uploadMessage.textContent = "نوع الملف غير مدعوم.";
            return;
        }

        uploadMessage.textContent =
            "تم اختيار الملف: " + file.name +
            " — الرفع والفحص لم يُفعّلا بعد.";
    });
}
