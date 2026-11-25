
const STORAGE_KEY = "smartCvResume";

const preview = document.getElementById("resumePreview");
const content = document.getElementById("resumeContent");
const atsResult = document.getElementById("atsResult");

let resume = null;

try {
    resume = JSON.parse(localStorage.getItem(STORAGE_KEY));
} catch (error) {
    resume = null;
}

function esc(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[char]);
}

function section(title, body) {
    if (!body) return "";
    return `<section class="cv-section">
        <h3>${esc(title)}</h3>${body}
    </section>`;
}

function entries(items, fields) {
    if (!Array.isArray(items)) return "";

    return items.map(item => {
        if (!item || typeof item !== "object") return "";

        const values = fields.map(f => item[f]).filter(Boolean);
        if (!values.length) return "";

        return `<div class="resume-entry">
            <strong>${esc(values[0])}</strong>
            ${values.slice(1).map(v => `<p>${esc(v)}</p>`).join("")}
        </div>`;
    }).join("");
}

function renderResume() {
    if (!resume || typeof resume !== "object" || Array.isArray(resume)) {
        content.innerHTML = `
            <p>لا توجد سيرة ذاتية محفوظة.</p>
            <a href="builder.html">إنشاء سيرة جديدة</a>`;
        return;
    }

    const contact = [
        resume.email, resume.phone,
        resume.city, resume.linkedin
    ].filter(Boolean).map(esc).join(" | ");

    const education = (resume.education || []).map(item => ({
        title: [item.degree, item.major].filter(Boolean).join(" - "),
        school: item.school,
        year: item.year
    }));

    const experience = (resume.experience || []).map(item => ({
        title: item.position,
        company: item.company,
        period: [item.start, item.end].filter(Boolean).join(" - "),
        description: item.description
    }));

    const skills = (resume.skills || [])
        .map(item => item.name)
        .filter(Boolean)
        .map(esc)
        .join(" • ");

    content.innerHTML = `
        <header class="resume-header">
            <h2>${esc(resume.fullName || "الاسم الكامل")}</h2>
            <p>${esc(resume.jobTitle)}</p>
            <div class="resume-contact">${contact}</div>
        </header>

        ${section("النبذة المهنية",
            resume.summary ? `<p>${esc(resume.summary)}</p>` : "")}

        ${section("المؤهلات التعليمية",
            entries(education, ["title", "school", "year"]))}

        ${section("الخبرات العملية",
            entries(experience, ["title", "company", "period", "description"]))}

        ${section("المهارات",
            skills ? `<p class="skills-text">${skills}</p>` : "")}

        ${section("اللغات",
            entries(resume.languages, ["name", "level"]))}

        ${section("الدورات والشهادات",
            entries(resume.certifications,
                ["title", "issuer", "date", "url"]))}

        ${section("المشاريع",
            entries(resume.projects,
                ["title", "description", "url"]))}

        ${section("الأعمال التطوعية",
            entries(resume.volunteering,
                ["role", "organization", "start", "end", "description"]))}

        ${section("المراجع المهنية",
            entries(resume.references,
                ["name", "position", "organization", "contact"]))}

        ${Array.isArray(resume.customSections) ? resume.customSections.map(custom => {
            if (!custom || typeof custom !== "object" || Array.isArray(custom)) return "";
            const title = typeof custom.title === "string" ? custom.title.trim() : "";
            if (!title) return "";
            const items = Array.isArray(custom.items) ? custom.items : [];
            const body = items.map(item => {
                if (!item || typeof item !== "object") return "";
                const heading = typeof item.heading === "string" ? item.heading : "";
                const desc = typeof item.description === "string" ? item.description : "";
                if (!heading && !desc) return "";
                return `<div class="resume-entry">${heading ? `<strong>${esc(heading)}</strong>` : ""}${desc ? `<p>${esc(desc)}</p>` : ""}</div>`;
            }).join("");
            return section(title, body);
        }).join("") : ""}
    `;
}

document.querySelectorAll(".template-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        preview.className = "resume-paper " + btn.dataset.template;

        document.querySelectorAll(".template-btn").forEach(b => {
            b.classList.remove("btn-primary");
            b.classList.add("btn-outline-light");
        });

        btn.classList.remove("btn-outline-light");
        btn.classList.add("btn-primary");
    });
});

document.getElementById("printBtn").addEventListener("click", () => {
    if (!resume) {
        alert("أنشئ سيرة ذاتية أولاً.");
        return;
    }
    window.print();
});

   
document.getElementById("atsBtn").addEventListener("click", () => {
    if (!resume) {
        alert("أنشئ سيرة ذاتية أولًا.");
        return;
    }

    const result = checkATS(resume);
    const description = document
        .getElementById("jobDescription")?.value.trim() || "";

    atsResult.classList.remove("d-none");

    let html = `
        <h3>اكتمال السيرة الذاتية: ${result.score}%</h3>
        <p>${esc(result.message)}</p>
        <ul>
            ${result.suggestions.map(s =>
                `<li>${esc(s)}</li>`
            ).join("")}
        </ul>
    `;

    if (description) {
        const comparison = compareJobDescription(resume, description);

        if (comparison.error) {
            html += `<p>${esc(comparison.error)}</p>`;
        } else {
            html += `
                <hr>
                <h3>التطابق التقديري مع الوصف الوظيفي:
                    ${comparison.score}%
                </h3>
                <p><strong>الكلمات الموجودة:</strong>
                    ${comparison.matched.map(esc).join("، ") || "لا يوجد"}
                </p>
                <p><strong>الكلمات الناقصة:</strong>
                    ${comparison.missing.map(esc).join("، ") || "لا يوجد"}
                </p>
                <p class="small">
                    هذا فحص تجريبي للكلمات المفتاحية،
                    وليس تقييم ATS رسميًا.
                </p>
            `;
        }
    } else {
        html += `
            <p>أدخل وصفًا وظيفيًا للحصول على مقارنة الكلمات المفتاحية.</p>
        `;
    }

    atsResult.innerHTML = html;
});

async function loadSelectedResume() {
 const params=new URLSearchParams(location.search);
 const id=params.get('id');
 if(!id){renderResume();return;}
 if(!/^[1-9]\d*$/.test(id)){resume=null;renderResume();return;}
 try{
  const response=await fetch('../Back/get_resume.php?id='+encodeURIComponent(id),{credentials:'same-origin',cache:'no-store'});
  if(response.status===401){location.replace('index.html');return;}
  const result=await response.json();
  if(!response.ok||!result.success||!result.resume)throw new Error('تعذر فتح السيرة.');
  resume=result.resume;
  renderResume();
  if(params.get('download')==='1'){
   const note=document.createElement('p');note.textContent='للتنزيل: اختر «حفظ كملف PDF» من نافذة الطباعة.';
   note.className='text-center no-print';document.querySelector('main')?.prepend(note);
   setTimeout(()=>window.print(),500);
  }
 }catch(error){resume=null;renderResume();alert('تعذر فتح السيرة المطلوبة.');}
}
loadSelectedResume();
