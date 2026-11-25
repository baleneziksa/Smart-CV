let currentUserRole = null; // [تعديل]

async function checkSession() {
    try {
        const response = await fetch("../Back/session.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        if (!response.ok) {
            window.location.replace("index.html");
            return false;
        }

        const data = await response.json();

        if (!data.authenticated) {
            window.location.replace("index.html");
            return false;
        }

        currentUserRole = data.user ? data.user.role : null; // [تعديل] حفظ الصلاحية
        return true;

    } catch (error) {
        window.location.replace("index.html");
        return false;
    }
}

const resumeFileInput = document.getElementById("resumeFile");
const uploadBtn = document.getElementById("uploadBtn");
const uploadMessage = document.getElementById("uploadMessage");
const uploadFileDetails = document.getElementById("uploadFileDetails");
const clearUploadBtn = document.getElementById("clearUploadBtn");
const uploadDropZone = document.getElementById("uploadDropZone");
const uploadProgressContainer = document.getElementById("uploadProgressContainer");
const uploadProgress = document.getElementById("uploadProgress");
const uploadProgressBar = document.getElementById("uploadProgressBar");
const uploadPercent = document.getElementById("uploadPercent");
const uploadProgressLabel = document.getElementById("uploadProgressLabel");
const extractNewBtn = document.getElementById("extractNewBtn");
const extractNewStatus = document.getElementById("extractNewStatus");
const openExtractedEditor = document.getElementById("openExtractedEditor");
const PENDING_UPLOAD_SESSION_KEY = "smartcv_pending_ai_upload";
let pendingUploadId = null;
let knownUploadedIds = new Set();
let uploadedRows = [];
let aiCsrfToken = "";

function markPendingUpload(id, label = "") {
    if (!/^[1-9]\d*$/.test(String(id))) return;
    pendingUploadId = String(id);
    sessionStorage.setItem(PENDING_UPLOAD_SESSION_KEY, pendingUploadId);
    if (extractNewBtn) {
        extractNewBtn.hidden = false;
        extractNewBtn.disabled = false;
        extractNewBtn.textContent = "✨ استخراج البيانات بالذكاء الاصطناعي";
    }
    if (openExtractedEditor) openExtractedEditor.hidden = true;
    if (extractNewStatus) {
        extractNewStatus.className = "small mt-2 mb-0 text-info";
        extractNewStatus.textContent = `الملف ${label ? "«" + label + "» " : ""}جاهز للاستخراج. اضغط الزر مرة واحدة لحفظ البيانات.`;
    }
}

const MAX_RESUME_SIZE = 5 * 1024 * 1024;
const ACCEPTED_RESUME_EXTENSIONS = new Set(["pdf", "doc", "docx"]);
let isUploading = false;

function setUploadMessage(message, level = "info") {
    if (!uploadMessage) return;
    uploadMessage.textContent = message;
    uploadMessage.className = "mt-3 small " + ({
        success: "text-success",
        error: "text-danger",
        info: "text-info"
    }[level] || "text-info");
}

function selectedResume() {
    return resumeFileInput && resumeFileInput.files
        ? resumeFileInput.files[0] || null
        : null;
}

function validateResume(file) {
    if (!file) return "اختر ملفًا أولًا.";
    const ext = file.name.includes(".") ? file.name.split(".").pop().toLowerCase() : "";
    if (!ACCEPTED_RESUME_EXTENSIONS.has(ext)) {
        return "الصيغة غير مدعومة. اختر ملف PDF أو Word (DOC/DOCX).";
    }
    if (file.size <= 0) return "الملف فارغ. اختر ملفًا آخر.";
    if (file.size > MAX_RESUME_SIZE) {
        return "حجم الملف يتجاوز 5 ميجابايت. اختر ملفًا أصغر.";
    }
    return "";
}

function resetProgress() {
    if (uploadProgressContainer) uploadProgressContainer.hidden = true;
    if (uploadProgressBar) uploadProgressBar.style.width = "0%";
    if (uploadPercent) uploadPercent.textContent = "0%";
    if (uploadProgress) uploadProgress.setAttribute("aria-valuenow", "0");
    if (uploadProgressLabel) uploadProgressLabel.textContent = "جارٍ رفع الملف...";
}

function setUploadProgress(value) {
    const pct = Math.min(100, Math.max(0, Math.round(value)));
    if (uploadProgressContainer) uploadProgressContainer.hidden = false;
    if (uploadProgressBar) uploadProgressBar.style.width = `${pct}%`;
    if (uploadPercent) uploadPercent.textContent = `${pct}%`;
    if (uploadProgress) uploadProgress.setAttribute("aria-valuenow", String(pct));
}

function updateUploadSelection() {
    if (isUploading) return;
    const file = selectedResume();
    const issue = validateResume(file);
    resetProgress();
    if (clearUploadBtn) clearUploadBtn.hidden = !file;
    if (uploadBtn) uploadBtn.disabled = !!issue;

    if (!file) {
        if (uploadFileDetails) uploadFileDetails.textContent = "لم يتم اختيار ملف بعد.";
        setUploadMessage("");
        return;
    }

    const mb = (file.size / (1024 * 1024)).toFixed(2);
    if (uploadFileDetails) {
        uploadFileDetails.textContent = `الملف المختار: ${file.name} (${mb} ميجابايت)`;
    }
    setUploadMessage(issue || "الملف جاهز للرفع. اضغط «رفع السيرة الذاتية».", issue ? "error" : "info");
}

if (resumeFileInput && uploadBtn) {
    resumeFileInput.addEventListener("change", updateUploadSelection);

    if (clearUploadBtn) {
        clearUploadBtn.addEventListener("click", () => {
            if (isUploading) return;
            resumeFileInput.value = "";
            updateUploadSelection();
        });
    }

    if (uploadDropZone) {
        const openFilePicker = () => {
            if (!isUploading) resumeFileInput.click();
        };
        uploadDropZone.addEventListener("click", openFilePicker);
        uploadDropZone.addEventListener("keydown", event => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openFilePicker();
            }
        });
        uploadDropZone.addEventListener("dragover", event => {
            event.preventDefault();
            if (!isUploading) uploadDropZone.style.borderColor = "#818cf8";
        });
        uploadDropZone.addEventListener("dragleave", () => {
            uploadDropZone.style.borderColor = "#64748b";
        });
        uploadDropZone.addEventListener("drop", event => {
            event.preventDefault();
            uploadDropZone.style.borderColor = "#64748b";
            if (isUploading || !event.dataTransfer?.files.length) return;
            const transfer = new DataTransfer();
            transfer.items.add(event.dataTransfer.files[0]);
            resumeFileInput.files = transfer.files;
            updateUploadSelection();
        });
    }

    uploadBtn.addEventListener("click", () => {
        if (isUploading) return;
        const file = selectedResume();
        const issue = validateResume(file);
        if (issue) {
            setUploadMessage(issue, "error");
            return;
        }

        const uploadsBefore = new Set(knownUploadedIds);
        const selectedName = file.name;
        const selectedSize = file.size;
        const data = new FormData();
        data.append("resume", file);
        const request = new XMLHttpRequest();
        isUploading = true;
        uploadBtn.disabled = true;
        uploadBtn.textContent = "جارٍ رفع الملف...";
        resumeFileInput.disabled = true;
        if (clearUploadBtn) clearUploadBtn.disabled = true;
        setUploadProgress(0);
        if (extractNewBtn) {
            extractNewBtn.hidden = false;
            extractNewBtn.disabled = true;
            extractNewBtn.textContent = "✨ استخراج البيانات بالذكاء الاصطناعي";
        }
        if (openExtractedEditor) openExtractedEditor.hidden = true;
        setUploadMessage("جارٍ رفع الملف إلى الخادم...", "info");

        request.open("POST", "../Back/upload_resume.php", true);
        request.timeout = 120000;
        request.withCredentials = true;

        request.upload.addEventListener("progress", event => {
            if (event.lengthComputable) {
                setUploadProgress(event.loaded / event.total * 100);
            }
        });
        request.upload.addEventListener("load", () => {
            if (uploadProgressLabel) uploadProgressLabel.textContent = "تم إرسال الملف، جارٍ التحقق والحفظ...";
            setUploadProgress(100);
        });

        function finish() {
            isUploading = false;
            resumeFileInput.disabled = false;
            if (clearUploadBtn) clearUploadBtn.disabled = false;
            uploadBtn.textContent = "رفع السيرة الذاتية";
            uploadBtn.disabled = !!validateResume(selectedResume());
        }

        request.addEventListener("load", async () => {
            finish();
            if (request.status === 401) {
                window.location.replace("index.html");
                return;
            }
            let result;
            try {
                result = JSON.parse(request.responseText);
            } catch (_) {
                resetProgress();
                setUploadMessage("استجابة الخادم غير مفهومة. تأكد من تشغيل PHP وMySQL.", "error");
                return;
            }
            if (request.status < 200 || request.status >= 300 || !result.success) {
                resetProgress();
                setUploadMessage(result.message || "تعذر رفع الملف. حاول مرة أخرى.", "error");
                return;
            }

            if (uploadProgressLabel) uploadProgressLabel.textContent = "اكتمل رفع الملف وحفظه";
            setUploadProgress(100);
            resumeFileInput.value = "";
            uploadBtn.disabled = true;
            if (clearUploadBtn) clearUploadBtn.hidden = true;
            if (uploadFileDetails) uploadFileDetails.textContent = "لم يتم اختيار ملف بعد.";
            pendingUploadId = null;
            sessionStorage.removeItem(PENDING_UPLOAD_SESSION_KEY);
            const updated = await loadList();
            const newUploads = (updated && Array.isArray(updated.uploaded) ? updated.uploaded : [])
                .filter(item => !uploadsBefore.has(String(item.upload_id)))
                .filter(item => !item.ai_extracted_at && !item.linked_resume_id);
            const responseId = result.upload_id || result.id;
            let uploadedId = responseId && /^[1-9]\d*$/.test(String(responseId))
                ? String(responseId) : null;
            if (!uploadedId) {
                const matches = newUploads.filter(item =>
                    String(item.original_name || "") === selectedName &&
                    Number(item.file_size) === selectedSize
                );
                const candidates = matches.length ? matches : newUploads;
                if (candidates.length === 1) uploadedId = String(candidates[0].upload_id);
            }
            if (uploadedId) {
                markPendingUpload(uploadedId, selectedName);
                setUploadMessage("تم رفع السيرة بنجاح. اضغط زر «استخراج البيانات بالذكاء الاصطناعي» هنا بالأسفل.", "success");
            } else {
                setUploadMessage("تم رفع الملف بنجاح، لكن تعذر تحديد سجله الجديد بأمان. حدّث الصفحة وتأكد من ملفات الرفع والقائمة، أو أعد رفعه.", "error");
                if (extractNewStatus) {
                    extractNewStatus.className = "small mt-2 mb-0 text-danger";
                    extractNewStatus.textContent = "لم يُشغّل الذكاء الاصطناعي؛ لم نحدد رقم الملف المرفوع.";
                }
            }
        });

        request.addEventListener("error", () => {
            finish();
            resetProgress();
            setUploadMessage("تعذر الاتصال بالخادم. تأكد من تشغيل Apache وMySQL.", "error");
        });
        request.addEventListener("timeout", () => {
            finish();
            resetProgress();
            setUploadMessage("استغرق الرفع وقتًا طويلًا. أعد المحاولة.", "error");
        });
        request.addEventListener("abort", () => {
            finish();
            resetProgress();
            setUploadMessage("تم إلغاء رفع الملف.", "error");
        });

        request.send(data);
    });
    updateUploadSelection();
}

function itemNode(tag, className, value) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (value !== undefined) el.textContent = value;
    return el;
}

async function deleteResume(type, id, title, button, csrfToken) {
    if (!window.confirm(`هل أنت متأكد من حذف السيرة «${title}» نهائيًا؟`)) {
        return;
    }

    button.disabled = true;
    button.textContent = "جارٍ الحذف...";

    try {
        const body = new URLSearchParams({
            type: String(type),
            id: String(id),
            csrf_token: csrfToken
        });

        const response = await fetch("../Back/delete_resume.php", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
            body
        });

        if (response.status === 401) {
            window.location.replace("index.html");
            return;
        }

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || "تعذر حذف السيرة.");
        }

        await loadList();
    } catch (error) {
        alert(error.message || "حدث خطأ أثناء الحذف.");
        button.disabled = false;
        button.textContent = "حذف";
    }
}

async function analyzeUploadedOnce(uploadId, button, status, csrfToken) {
    if (!csrfToken) {
        status.textContent = "تعذر الحصول على رمز الطلب. حدّث الصفحة.";
        status.className = "small mt-2 mb-0 text-danger";
        return false;
    }
    button.disabled = true;
    button.textContent = "جارٍ التحليل...";
    status.textContent = "جارٍ استخراج البيانات. قد يستغرق النموذج المحلي بعض الوقت...";
    status.className = "small mt-2 mb-0 text-info";
    try {
        const check = await fetch(`../Back/get_uploaded_ai.php?id=${encodeURIComponent(uploadId)}`, {
            credentials: "same-origin", cache: "no-store"
        });
        if (check.status === 401) { window.location.replace("index.html"); return; }
        const old = await check.json();
        if (!check.ok || !old.success) throw new Error(old.message || "تعذر قراءة حالة الملف.");
        if (old.extracted || old.linked_resume_id) {
            status.textContent = "البيانات موجودة مسبقًا؛ لن نعيد التحليل.";
            return true;
        }

        const form = new FormData();
        form.append("upload_id", String(uploadId));
        const response = await fetch("../Back/analyze_resume.php", {
            method: "POST", credentials: "same-origin", body: form
        });
        if (response.status === 401) { window.location.replace("index.html"); return; }
        const analysis = await response.json();
        if (!response.ok || !analysis.success || !analysis.resume) {
            throw new Error(analysis.message || "تعذر استخراج بيانات السيرة.");
        }

        status.textContent = "اكتمل الاستخراج؛ جارٍ حفظ النتيجة في قاعدة البيانات...";
        const save = await fetch("../Back/save_uploaded_ai.php", {
            method: "POST", credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                upload_id: uploadId, csrf_token: csrfToken, resume: analysis.resume,
                analysis_complete: analysis.analysis_complete === true
            })
        });
        const stored = await save.json();
        if (!save.ok || !stored.success) {
            throw new Error(stored.message || "نجح التحليل لكن تعذّر حفظ نتيجته.");
        }
        status.textContent = analysis.analysis_complete === false
            ? "تم حفظ البيانات المستخرجة جزئيًا. اضغط «تعديل» لمراجعتها وتكميلها."
            : "تم حفظ نتيجة التحليل. اضغط «تعديل» لمراجعتها بدون إعادة التحليل.";
        status.className = "small mt-2 mb-0 text-success";
        return true;
    } catch (error) {
        status.textContent = error.message || "تعذر التحليل أو الحفظ. حاول مرة أخرى.";
        status.className = "small mt-2 mb-0 text-danger";
        return false;
    } finally {
        button.disabled = false;
        button.textContent = "✨ استخراج بالذكاء الاصطناعي";
    }
}

async function loadList() {
    const holder = document.getElementById("resumeList");
    if (!holder) {
        console.error("العنصر resumeList غير موجود.");
        return;
    }

    holder.replaceChildren(itemNode("p", "text-secondary", "جارٍ تحميل السير الذاتية..."));

    try {
        const response = await fetch("../Back/list_resumes.php", {
            credentials: "same-origin",
            cache: "no-store"
        });

        if (response.status === 401) {
            window.location.replace("index.html");
            return;
        }

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "تعذر تحميل القائمة.");
        }

        uploadedRows = Array.isArray(result.uploaded) ? result.uploaded : [];
        knownUploadedIds = new Set(uploadedRows.map(item => String(item.upload_id)));
        aiCsrfToken = result.ai_csrf || "";
        const savedPending = sessionStorage.getItem(PENDING_UPLOAD_SESSION_KEY);
        if (savedPending) {
            const pendingRow = uploadedRows.find(item => String(item.upload_id) === savedPending);
            if (pendingRow && !pendingRow.ai_extracted_at && !pendingRow.linked_resume_id) {
                if (!pendingUploadId || pendingUploadId === savedPending) {
                    markPendingUpload(savedPending, pendingRow.original_name || "");
                }
            } else {
                sessionStorage.removeItem(PENDING_UPLOAD_SESSION_KEY);
            }
        }

        const linkedCreatedIds = new Set((result.uploaded || [])
            .filter(item => item.linked_resume_id)
            .map(item => String(item.linked_resume_id)));
        const items = [
            ...(result.created || []).filter(item => !linkedCreatedIds.has(String(item.resume_id))).map(item => ({
                ...item,
                type: "created"
            })),
            ...(result.uploaded || []).map(item => ({
                ...item,
                type: "uploaded"
            }))
        ];

        items.sort((a, b) =>
            String(b.updated_at || b.created_at || "")
                .localeCompare(
                    String(a.updated_at || a.created_at || "")
                )
        );

        holder.replaceChildren();

        if (!items.length) {
            holder.append(itemNode("p", "text-secondary", "ما عندك سير ذاتية محفوظة حتى الآن."));
            return result;
        }

        for (const item of items) {
            const col = itemNode("div", "col-md-6 col-lg-4");
            const card = itemNode("div", "dashboard-card h-100");
            const title = item.type === "created"
                ? (item.title || item.full_name || "سيرة ذاتية")
                : (item.original_name || "سيرة ذاتية مرفوعة");

            card.append(itemNode("h5", "fw-bold text-break", title));
            card.append(itemNode("p", "text-secondary small", item.type === "created"
                ? "سيرة منشأة داخل الموقع"
                : (item.ai_extracted_at ? "سيرة مرفوعة – تم حفظ الاستخراج" : "سيرة مرفوعة – لم تُحلّل بعد")));

            const actions = itemNode("div", "d-flex flex-wrap gap-2");
            const view = itemNode("a", "btn btn-primary btn-sm", "استعراض");
            const edit = itemNode("a", "btn btn-outline-info btn-sm", "تعديل");
            const download = itemNode("a", "btn btn-outline-light btn-sm", "تنزيل");

            if (item.type === "created") {
                const id = encodeURIComponent(item.resume_id);
                view.href = `preview.html?id=${id}`;
                edit.href = `builder.html?id=${id}`;
                download.href = `preview.html?id=${id}&download=1`;
            } else {
                const id = encodeURIComponent(item.upload_id);
                view.href = `../Back/view_uploaded_resume.php?id=${id}`;
                edit.href = item.linked_resume_id
                    ? `builder.html?id=${encodeURIComponent(item.linked_resume_id)}`
                    : `builder.html?upload_id=${id}`;
                if (!item.ai_extracted_at && !item.linked_resume_id) {
                    edit.textContent = "تعديل يدوي";
                }
                download.href = `../Back/view_uploaded_resume.php?id=${id}&download=1`;
            }

            const remove = itemNode("button", "btn btn-outline-danger btn-sm", "حذف");
            remove.type = "button";
            const resumeId = item.type === "created" ? item.resume_id : item.upload_id;
            remove.addEventListener("click", () =>
                deleteResume(item.type, resumeId, title, remove, result.delete_csrf || "")
            );

            actions.append(view, edit, download);
            actions.append(remove);
            card.append(actions);
            col.append(card);
            holder.append(col);
        }
        return result;

    } catch (error) {
        console.error("خطأ تحميل السير:", error);
        holder.replaceChildren(itemNode("p", "text-danger", error.message || "تعذر تحميل السير الذاتية."));
    }
}

if (extractNewBtn) {
    extractNewBtn.addEventListener("click", async () => {
        const id = pendingUploadId;
        if (!id || !/^[1-9]\d*$/.test(id)) {
            if (extractNewStatus) extractNewStatus.textContent = "ارفع سيرة جديدة أولًا.";
            return;
        }
        if (isUploading) return;
        const current = uploadedRows.find(item => String(item.upload_id) === id);
        if (!current || current.ai_extracted_at || current.linked_resume_id) {
            if (extractNewStatus) extractNewStatus.textContent = "هذه السيرة سبق استخراجها أو حفظها؛ افتحها باستخدام تعديل.";
            extractNewBtn.disabled = true;
            sessionStorage.removeItem(PENDING_UPLOAD_SESSION_KEY);
            return;
        }
        if (uploadBtn) uploadBtn.disabled = true;
        if (resumeFileInput) resumeFileInput.disabled = true;
        if (clearUploadBtn) clearUploadBtn.disabled = true;
        const ok = await analyzeUploadedOnce(id, extractNewBtn, extractNewStatus, aiCsrfToken);
        if (resumeFileInput) resumeFileInput.disabled = false;
        if (clearUploadBtn) clearUploadBtn.disabled = false;
        if (uploadBtn) uploadBtn.disabled = !!validateResume(selectedResume());
        if (ok) {
            sessionStorage.removeItem(PENDING_UPLOAD_SESSION_KEY);
            pendingUploadId = null;
            extractNewBtn.disabled = true;
            extractNewBtn.hidden = true;
            extractNewBtn.textContent = "✓ تم استخراج البيانات وحفظها";
            if (openExtractedEditor) {
                openExtractedEditor.href = `builder.html?upload_id=${encodeURIComponent(id)}`;
                openExtractedEditor.hidden = false;
            }
            await loadList();
        }
    });
}

async function startDashboard() {
    const authenticated = await checkSession();

    if (!authenticated) return;

    // [تعديل] إظهار بطاقة الإدارة فقط إذا كان المستخدم مديراً
    if (currentUserRole === 'admin') {
        const adminCardWrapper = document.getElementById("adminCardWrapper");
        if (adminCardWrapper) {
            adminCardWrapper.classList.remove("d-none");
        }
    }

    await loadList();
}

startDashboard();