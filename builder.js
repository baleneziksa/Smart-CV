
const STORAGE_KEY = "smartCvResume";

const form = document.getElementById("cvForm");
const saveMessage = document.getElementById("saveMessage");

const templates = {
    education: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">المؤهل العلمي</label>
                <input class="form-control" data-field="degree"
                       placeholder="دبلوم، بكالوريوس...">
            </div>
            <div class="col-md-6">
                <label class="form-label">التخصص</label>
                <input class="form-control" data-field="major">
            </div>
            <div class="col-md-6">
                <label class="form-label">الجهة التعليمية</label>
                <input class="form-control" data-field="school">
            </div>
            <div class="col-md-6">
                <label class="form-label">سنة التخرج</label>
                <input class="form-control" data-field="year">
            </div>
        </div>`,

    experience: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">المسمى الوظيفي</label>
                <input class="form-control" data-field="position">
            </div>
            <div class="col-md-6">
                <label class="form-label">اسم الشركة</label>
                <input class="form-control" data-field="company">
            </div>
            <div class="col-md-6">
                <label class="form-label">من سنة</label>
                <input class="form-control" data-field="start">
            </div>
            <div class="col-md-6">
                <label class="form-label">إلى سنة</label>
                <input class="form-control" data-field="end"
                       placeholder="حتى الآن">
            </div>
            <div class="col-12">
                <label class="form-label">المهام الوظيفية</label>
                <textarea class="form-control" data-field="description"
                          rows="3"></textarea>
            </div>
        </div>`,

    skill: `
        <label class="form-label">المهارة</label>
        <input class="form-control" data-field="name"
               placeholder="مثال: العمل الجماعي">`,

    languages: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">اللغة</label>
                <input class="form-control" data-field="name">
            </div>
            <div class="col-md-6">
                <label class="form-label">مستوى الإتقان</label>
                <select class="form-control" data-field="level">
                    <option value="">اختر المستوى</option>
                    <option>مبتدئ</option>
                    <option>متوسط</option>
                    <option>متقدم</option>
                    <option>طليق</option>
                    <option>اللغة الأم</option>
                </select>
            </div>
        </div>`,

    certifications: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">اسم الدورة أو الشهادة</label>
                <input class="form-control" data-field="title">
            </div>
            <div class="col-md-6">
                <label class="form-label">الجهة المانحة</label>
                <input class="form-control" data-field="issuer">
            </div>
            <div class="col-md-6">
                <label class="form-label">تاريخ الحصول عليها</label>
                <input type="month" class="form-control" data-field="date">
            </div>
            <div class="col-md-6">
                <label class="form-label">رابط الشهادة</label>
                <input type="url" class="form-control" data-field="url">
            </div>
        </div>`,

    projects: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">اسم المشروع</label>
                <input class="form-control" data-field="title">
            </div>
            <div class="col-md-6">
                <label class="form-label">رابط المشروع</label>
                <input type="url" class="form-control" data-field="url">
            </div>
            <div class="col-12">
                <label class="form-label">وصف المشروع</label>
                <textarea class="form-control" data-field="description"
                          rows="3"></textarea>
            </div>
        </div>`,

    volunteering: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">الدور التطوعي</label>
                <input class="form-control" data-field="role">
            </div>
            <div class="col-md-6">
                <label class="form-label">اسم الجهة</label>
                <input class="form-control" data-field="organization">
            </div>
            <div class="col-md-6">
                <label class="form-label">من سنة</label>
                <input class="form-control" data-field="start">
            </div>
            <div class="col-md-6">
                <label class="form-label">إلى سنة</label>
                <input class="form-control" data-field="end">
            </div>
            <div class="col-12">
                <label class="form-label">وصف العمل التطوعي</label>
                <textarea class="form-control" data-field="description"
                          rows="3"></textarea>
            </div>
        </div>`,

    references: `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">اسم المرجع</label>
                <input class="form-control" data-field="name">
            </div>
            <div class="col-md-6">
                <label class="form-label">المسمى الوظيفي</label>
                <input class="form-control" data-field="position">
            </div>
            <div class="col-md-6">
                <label class="form-label">اسم الجهة</label>
                <input class="form-control" data-field="organization">
            </div>
            <div class="col-md-6">
                <label class="form-label">بيانات التواصل</label>
                <input class="form-control" data-field="contact">
            </div>
        </div>`
};

const sections = [
    { type: "education", container: "educationList", button: "addEducation" },
    { type: "experience", container: "experienceList", button: "addExperience" },
    { type: "skill", container: "skillsList", button: "addSkill", key: "skills" },
    { type: "languages", container: "languagesList", button: "addLanguage" },
    { type: "certifications", container: "certificationsList", button: "addCertification" },
    { type: "projects", container: "projectsList", button: "addProject" },
    { type: "volunteering", container: "volunteeringList", button: "addVolunteering" },
    { type: "references", container: "referencesList", button: "addReference" }
];

function addItem(type, values = {}) {
    const section = sections.find(s => s.type === type);
    const container = document.getElementById(section.container);

    const item = document.createElement("div");
    item.className = "dynamic-item";

    item.innerHTML = templates[type] + `
        <button type="button" class="remove-btn">حذف</button>
    `;

    item.querySelectorAll("[data-field]").forEach(input => {
        input.value = values[input.dataset.field] ?? "";
    });

    item.querySelector(".remove-btn").addEventListener("click", () => {
        item.remove();
    });

    container.appendChild(item);
}

sections.forEach(section => {
    document.getElementById(section.button).addEventListener(
        "click",
        () => addItem(section.type)
    );
});

function readItems(containerId) {
    return Array.from(
        document.querySelectorAll(`#${containerId} .dynamic-item`)
    ).map(item => {
        const result = {};

        item.querySelectorAll("[data-field]").forEach(input => {
            result[input.dataset.field] = input.value.trim();
        });

        return result;
    }).filter(item => Object.values(item).some(Boolean));
}

const personalFields = [
    "fullName",
    "jobTitle",
    "email",
    "phone",
    "city",
    "linkedin",
    "summary"
];


// ========= الأقسام الإضافية التي يحددها المستخدم =========
// تُحفظ ضمن customSections (اسم القسم + عناصر العنوان والوصف).
const customContainer = document.getElementById("customSectionsList");
const addCustomButton = document.getElementById("addCustomSection");
const CUSTOM_MAX_SECTIONS = 20;
const CUSTOM_MAX_ITEMS = 20;

function createCustomInput(labelText, placeholder, maxLength, tag = "input") {
    const wrapper = document.createElement("div");
    wrapper.className = "mb-3";
    const label = document.createElement("label");
    label.className = "form-label";
    label.textContent = labelText;
    const input = document.createElement(tag);
    input.className = "form-control";
    input.maxLength = maxLength;
    input.placeholder = placeholder;
    if (tag === "input") input.type = "text";
    else input.rows = 3;
    wrapper.append(label, input);
    return { wrapper, input };
}

function addCustomEntry(entriesHolder, values = {}) {
    if (entriesHolder.children.length >= CUSTOM_MAX_ITEMS) {
        alert("الحد الأقصى 20 عنصرًا لكل قسم.");
        return;
    }
    const entry = document.createElement("div");
    entry.className = "border rounded p-3 mb-3";
    entry.dataset.customEntry = "1";
    const heading = createCustomInput("عنوان العنصر (اختياري)", "مثال: مؤتمر التقنية 2026", 150);
    const description = createCustomInput("الوصف / التفاصيل", "اكتب التفاصيل التي تظهر في السيرة", 2000, "textarea");
    heading.input.dataset.customHeading = "1";
    description.input.dataset.customDescription = "1";
    heading.input.value = typeof values.heading === "string" ? values.heading : "";
    description.input.value = typeof values.description === "string" ? values.description : "";
    const remove = document.createElement("button");
    remove.type = "button";
    remove.className = "btn btn-outline-danger btn-sm";
    remove.textContent = "حذف العنصر";
    remove.addEventListener("click", () => entry.remove());
    entry.append(heading.wrapper, description.wrapper, remove);
    entriesHolder.append(entry);
}

function addCustomSection(values = {}) {
    if (!customContainer) return;
    if (customContainer.children.length >= CUSTOM_MAX_SECTIONS) {
        alert("الحد الأقصى 20 قسمًا إضافيًا.");
        return;
    }
    const card = document.createElement("div");
    card.className = "dynamic-item";
    card.dataset.customSection = "1";
    const title = createCustomInput("اسم القسم", "مثال: الإسهامات الحضورية", 100);
    title.input.dataset.customTitle = "1";
    title.input.value = typeof values.title === "string" ? values.title : "";
    const list = document.createElement("div");
    list.dataset.customEntries = "1";
    const controls = document.createElement("div");
    controls.className = "d-flex flex-wrap gap-2 mt-2";
    const addEntryBtn = document.createElement("button");
    addEntryBtn.type = "button";
    addEntryBtn.className = "btn btn-outline-primary btn-sm";
    addEntryBtn.textContent = "+ إضافة عنصر داخل القسم";
    addEntryBtn.addEventListener("click", () => addCustomEntry(list));
    const removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "btn btn-outline-danger btn-sm";
    removeBtn.textContent = "حذف القسم بالكامل";
    removeBtn.addEventListener("click", () => {
        if (window.confirm("هل تريد حذف هذا القسم بكل محتوياته؟")) card.remove();
    });
    controls.append(addEntryBtn, removeBtn);
    card.append(title.wrapper, list, controls);
    customContainer.append(card);
    if (Array.isArray(values.items)) {
        values.items.slice(0, CUSTOM_MAX_ITEMS).forEach(item =>
            addCustomEntry(list, item && typeof item === "object" ? item : {})
        );
    }
    if (!list.children.length) addCustomEntry(list);
    if (!title.input.value) title.input.focus();
}

function readCustomSections() {
    if (!customContainer) return [];
    const result = [];
    for (const card of customContainer.querySelectorAll("[data-custom-section]")) {
        const title = card.querySelector("[data-custom-title]").value.trim();
        const items = Array.from(card.querySelectorAll("[data-custom-entry]")).map(entry => ({
            heading: entry.querySelector("[data-custom-heading]").value.trim(),
            description: entry.querySelector("[data-custom-description]").value.trim()
        })).filter(item => item.heading || item.description);
        if (!title && !items.length) continue;
        if (!title) {
            card.querySelector("[data-custom-title]").focus();
            throw new Error("اكتب اسم القسم الإضافي قبل الحفظ.");
        }
        result.push({ title, items });
    }
    return result;
}

function renderCustomSections(customSections) {
    if (!customContainer) return;
    customContainer.replaceChildren();
    if (!Array.isArray(customSections)) return;
    customSections.slice(0, CUSTOM_MAX_SECTIONS).forEach(section => {
        if (section && typeof section === "object" && !Array.isArray(section)) addCustomSection(section);
    });
}

if (addCustomButton) addCustomButton.addEventListener("click", () => addCustomSection());
// ================================================

function getResumeData() {
    const data = {};

    personalFields.forEach(field => {
        data[field] = document.getElementById(field).value.trim();
    });

    sections.forEach(section => {
        data[section.key || section.type] = readItems(section.container);
    });

    data.customSections = readCustomSections();
    return data;
}

// رقم السيرة الحالية في قاعدة البيانات
let currentResumeId = null;
const urlParams = new URLSearchParams(window.location.search);
const requestedResumeId = urlParams.get("id");
const requestedUploadId = urlParams.get("upload_id");
let saving = false;

// عرض البيانات داخل المحرر
function renderResume(saved) {
    personalFields.forEach(field => {
        document.getElementById(field).value = saved[field] ?? "";
    });

    sections.forEach(section => {
        const container = document.getElementById(section.container);
        container.innerHTML = "";

        const key = section.key || section.type;
        const items = Array.isArray(saved[key]) ? saved[key] : [];

        items.forEach(item => addItem(section.type, item));
    });
    renderCustomSections(saved.customSections);
}

// حفظ السيرة في MySQL
async function saveResume() {
    if (!form.reportValidity() || saving) return false;

    saving = true;
    saveMessage.textContent = "جاري الحفظ في قاعدة البيانات...";

    try {
        const data = getResumeData();

        if (currentResumeId) {
            data.resume_id = currentResumeId;
        } else if (requestedUploadId) {
            // ربط السيرة التي سيعدلها المستخدم بالملف المرفوع الأصلي.
            data.origin_upload_id = requestedUploadId;
        }

        const response = await fetch("../Back/save_resume.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify(data)
        });

        if (response.status === 401) {
            window.location.replace("index.html");
            return false;
        }

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(
                result.message || "تعذر حفظ السيرة الذاتية."
            );
        }

        currentResumeId = result.resume_id;

        // نسخة محلية لاستخدامها في صفحة المعاينة
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ ...data, resume_id: currentResumeId })
        );

        saveMessage.textContent =
            result.message || "تم حفظ السيرة الذاتية بنجاح!";

        return true;

    } catch (error) {
        saveMessage.textContent =
            error.message || "تعذر الاتصال بالخادم.";

        return false;

    } finally {
        saving = false;
    }
}

// زر الحفظ
form.addEventListener("submit", async event => {
    event.preventDefault();
    await saveResume();
});

// زر المعاينة
document.getElementById("previewBtn").addEventListener(
    "click",
    async () => {
        if (await saveResume()) {
            window.location.href = `preview.html?id=${encodeURIComponent(currentResumeId)}`;
        }
    }
);

// تحميل السيرة من MySQL
async function loadResume() {
    try {
        // الملف المرفوع يُحلّل مرة واحدة بزر لوحة التحكم. هنا نقرأ المحفوظ فقط.
        if (requestedUploadId && !requestedResumeId) {
            if (!/^[1-9]\d*$/.test(requestedUploadId)) {
                throw new Error("رقم الملف المرفوع غير صالح.");
            }
            const response = await fetch(`../Back/get_uploaded_ai.php?id=${encodeURIComponent(requestedUploadId)}`, {
                credentials: "same-origin", cache: "no-store"
            });
            if (response.status === 401) { window.location.replace("index.html"); return; }
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || "تعذر قراءة الملف المرفوع.");
            }
            if (result.linked_resume_id) {
                window.location.replace(`builder.html?id=${encodeURIComponent(result.linked_resume_id)}`);
                return;
            }
            if (result.extracted && result.resume) {
                const extracted = result.resume;
                if (Array.isArray(extracted.skills)) {
                    extracted.skills = extracted.skills.map(skill =>
                        typeof skill === "string" ? { name: skill } : skill
                    );
                }
                renderResume(extracted);
                saveMessage.textContent = "تم تحميل البيانات المستخرجة المحفوظة. راجعها ثم اضغط حفظ؛ لم يُعَد تشغيل الذكاء الاصطناعي.";
            } else {
                addItem("education");
                addItem("experience");
                addItem("skill");
                saveMessage.textContent = "لم تستخرج بيانات هذه السيرة بعد. يمكنك تعبئتها يدويًا أو العودة للوحة التحكم لاستخدام زر الذكاء الاصطناعي.";
            }
            return;
        }
        // لا نحمّل آخر سيرة تلقائياً عند إنشاء سيرة جديدة.
        if (!requestedResumeId) {
            addItem("education");
            addItem("experience");
            addItem("skill");
            return;
        }
        if (!/^[1-9]\d*$/.test(requestedResumeId)) {
            throw new Error("رقم السيرة غير صالح.");
        }
        const response = await fetch(`../Back/get_resume.php?id=${encodeURIComponent(requestedResumeId)}`, {
            credentials: "same-origin",
            cache: "no-store"
        });

        if (response.status === 401) {
            window.location.replace("index.html");
            return;
        }

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error("تعذر تحميل السيرة الذاتية.");
        }

        if (!result.resume) {
            throw new Error("السيرة غير موجودة أو لا تملك صلاحية تعديلها.");
        }
        if (result.resume) {
            // اسم القسم في API هو experiences، وفي المحرر experience.
            if (!result.resume.experience && Array.isArray(result.resume.experiences)) {
                result.resume.experience = result.resume.experiences;
            }
            currentResumeId = result.resume.resume_id;

            renderResume(result.resume);

            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(result.resume)
            );

        } else {
            addItem("education");
            addItem("experience");
            addItem("skill");
        }

    } catch (error) {
        saveMessage.textContent = error.message ||
            "تعذر تحميل البيانات من الخادم. تحقق من اتصال PHP.";
    }
}

// تحميل البيانات عند فتح الصفحة
loadResume();
