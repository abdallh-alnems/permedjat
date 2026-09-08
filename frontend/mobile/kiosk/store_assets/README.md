# Store Assets — كشك الحضور

كل أصول رفع التطبيق على المتجرين في مكان واحد. نفس تقسيم بقية تطبيقات
Permedjat (`manager`, `employee`).

```
store_assets/
├── google_play/   ← أصول Google Play (Android · حزمة com.khawarizmie.medjat.kiosk)
│   ├── icon/                  أيقونة 512×512 + المصدر 1024
│   ├── feature_graphic/       الرسم المميز 1024×500 (ar / en)
│   ├── screenshots/           raw/ الخام ← phone/ النهائية 1080×1920
│   ├── data_safety/           نموذج Data Safety (CSV)
│   └── README.md              نصوص بطاقة المتجر + بيانات المراجعة
│
└── app_store/     ← هيكل جاهز — التطبيق لا يستهدف iOS بعد (لا مجلد ios/)
    ├── icon/                  أيقونة 1024×1024 (بلا شفافية)
    ├── screenshots/           raw/ الخام ← iphone_6_5/ النهائية
    └── README.md
```

الأيقونات تُولَّد من `assets/icons/kiosk.svg` — راجع `assets/icons/README.md`.
لا تحرّر أي PNG هنا يدوياً.

لكل متجر مجلده وتفاصيله في `README.md` بداخله.
