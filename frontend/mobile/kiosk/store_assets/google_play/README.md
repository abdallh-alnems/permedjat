# أصول متجر Google Play — كشك الحضور

حزمة التطبيق (applicationId): `com.khawarizmie.medjat.kiosk`

## 📁 محتويات المجلد

```
google_play/
├── icon/
│   ├── app_icon_512.png         ✅ رمز التطبيق (512×512) — يُرفع في "رمز التطبيق"
│   └── icon_master_1024.png     ✅ المصدر (1024×1024)
├── feature_graphic/
│   ├── ar/ · en/                ⏳ الرسم المميز 1024×500 لكل لغة
├── screenshots/
│   ├── raw/ar · raw/en          ⏳ ضع هنا اللقطات الخام من التطبيق
│   └── phone/ar · phone/en      ⏳ اللقطات النهائية 1080×1920
└── data_safety/                 ⏳ نموذج Data Safety (CSV)
```

✅ = جاهز · ⏳ = ينتظر محتوى

## الأيقونة

مولّدة من `assets/icons/kiosk.svg` ضمن تصدير 2026-09-08. لإعادة التوليد راجع
`assets/icons/README.md` — لا تحرّر الـ PNG يدوياً.

## اللقطات

`manager` و `employee` عندهما `make_screenshots.py` يؤطّر اللقطات الخام. السكربت
مربوط بشاشات كل تطبيق وعناوينها، فلم يُنسَخ هنا — يُكتب عند تجهيز لقطات هذا
التطبيق.
