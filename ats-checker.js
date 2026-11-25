
function checkATS(resume) {
    let score = 0;
    const suggestions = [];

    function check(condition, points, suggestion) {
        if (condition) {
            score += points;
        } else {
            suggestions.push(suggestion);
        }
    }

    check(Boolean(resume.fullName), 10, "أضف الاسم الكامل.");
    check(Boolean(resume.email), 10, "أضف البريد الإلكتروني.");
    check(Boolean(resume.phone), 10, "أضف رقم الجوال.");
    check(Boolean(resume.jobTitle), 10, "أضف المسمى الوظيفي.");

    check(
        (resume.summary || "").trim().length >= 50,
        15,
        "اكتب نبذة مهنية واضحة لا تقل عن 50 حرفًا."
    );

    check(
        (resume.education || []).some(item => item.degree || item.major),
        15,
        "أضف مؤهلك التعليمي."
    );

    check(
        (resume.experience || []).some(item => item.position),
        15,
        "أضف خبرة عملية أو تدريبًا إن وجد."
    );

    check(
        (resume.skills || []).filter(item => item.name).length >= 3,
        15,
        "أضف ثلاث مهارات مناسبة على الأقل."
    );

    let message = "السيرة تحتاج إلى تحسين.";

    if (score >= 80) {
        message = "السيرة مكتملة بشكل جيد وفق معايير الفحص التجريبي.";
    } else if (score >= 50) {
        message = "السيرة جيدة مبدئيًا، ويمكن تحسين بعض الأقسام.";
    }

    if (suggestions.length === 0) {
        suggestions.push("جميع عناصر الفحص التجريبي مكتملة.");
    }

    return { score, message, suggestions };
}

function compareJobDescription(resume, jobDescription) {
    const text = [
        resume.jobTitle,
        resume.summary,
        ...(resume.skills || []).map(item => item.name),
        ...(resume.experience || []).flatMap(item => [
            item.position,
            item.description
        ]),
        ...(resume.education || []).flatMap(item => [
            item.degree,
            item.major
        ]),
        ...(resume.projects || []).flatMap(item => [
            item.title,
            item.description
        ])
    ].filter(Boolean).join(" ");

    const normalize = value => String(value || "")
        .toLowerCase()
        .replace(/[أإآ]/g, "ا")
        .replace(/ى/g, "ي")
        .replace(/ة/g, "ه")
        .replace(/[^\p{L}\p{N}+#.]+/gu, " ")
        .trim();

    const stopWords = new Set([
        "في", "من", "على", "الى", "عن", "مع",
        "او", "و", "لدي", "لديه", "مطلوب",
        "مطلوبه", "خبره", "يجيد", "اتقان",
        "العمل", "وظيفه", "مهارات", "مهاره",
        "the", "and", "for", "with", "required",
        "experience", "skills", "knowledge"
    ]);

    const words = normalize(jobDescription)
        .split(/\s+/)
        .filter(word => word.length >= 2 && !stopWords.has(word));

    const keywords = [...new Set(words)];

    if (keywords.length === 0) {
        return {
            score: 0,
            matched: [],
            missing: [],
            error: "اكتب وصفًا وظيفيًا يحتوي على متطلبات واضحة."
        };
    }

    const resumeWords = new Set(normalize(text).split(/\s+/));

    const matched = keywords.filter(word => resumeWords.has(word));
    const missing = keywords.filter(word => !resumeWords.has(word));

    return {
        score: Math.round(matched.length / keywords.length * 100),
        matched,
        missing
    };
}
