import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import type { ReportData } from "@/lib/types";
import { slug } from "./helpers";

/**
 * Base64 payloads of the IBM Plex Sans Arabic faces, cached for the lifetime
 * of the page.
 * Only the *download* is shared between documents — see ensureArabicFont.
 */
let fontCache: { regular: string; bold: string } | null = null;

async function fetchFontBase64(url: string): Promise<string> {
  const res = await fetch(url);
  if (!res.ok) throw new Error(`${url} -> HTTP ${res.status}`);
  const buf = await res.arrayBuffer();
  return btoa(
    new Uint8Array(buf).reduce((s, b) => s + String.fromCharCode(b), ""),
  );
}

/**
 * Register the brand Arabic font (IBM Plex Sans Arabic) on `doc` so Arabic
 * glyphs render. The files are served from /fonts/ (public/fonts/, OFL
 * licensed) and are the same files the manager mobile app embeds in its PDFs,
 * so both export the same face.
 *
 * Both faces are registered: jsPDF-autotable asks for the "bold" style for
 * header rows and for any `fontStyle: "bold"` column, and a style jsPDF cannot
 * resolve falls back to Helvetica — which renders Arabic as mojibake.
 *
 * The registration is repeated for every document on purpose. jsPDF keeps its
 * virtual file system on the instance (`this.internal.vFS`), so a font added to
 * one document is unknown to the next; caching "already loaded" in a module
 * flag left every export after the first one silently rendering in Times.
 *
 * Returns whether the font is usable, so callers can point autotable at it.
 */
async function ensureArabicFont(doc: jsPDF): Promise<boolean> {
  try {
    if (!fontCache) {
      const [regular, bold] = await Promise.all([
        fetchFontBase64("/fonts/IBMPlexSansArabic-Regular.ttf"),
        fetchFontBase64("/fonts/IBMPlexSansArabic-Bold.ttf"),
      ]);
      fontCache = { regular, bold };
    }
    doc.addFileToVFS("IBMPlexSansArabic-Regular.ttf", fontCache.regular);
    doc.addFont("IBMPlexSansArabic-Regular.ttf", "IBMPlexSansArabic", "normal");
    doc.addFileToVFS("IBMPlexSansArabic-Bold.ttf", fontCache.bold);
    doc.addFont("IBMPlexSansArabic-Bold.ttf", "IBMPlexSansArabic", "bold");
    doc.setFont("IBMPlexSansArabic");
    return true;
  } catch {
    /* font unavailable — fall back to default */
    return false;
  }
}

/** Export a titled tabular report to PDF. RTL aware (Arabic title). */
export async function exportReportToPDF(
  report: ReportData,
  opts: { locale?: "ar" | "en"; filename?: string } = {},
) {
  const { locale = "ar", filename } = opts;
  const doc = new jsPDF({ orientation: "landscape" });
  const pageWidth = doc.internal.pageSize.getWidth();
  const isRTL = locale === "ar";

  const arabicFont = isRTL ? await ensureArabicFont(doc) : false;

  const xPos = isRTL ? pageWidth - 14 : 14;
  const align = isRTL ? "right" : "left";
  doc.text(report.title, xPos, 16, { align });
  doc.setFontSize(10);
  doc.setTextColor(120);
  doc.text(report.period, xPos, 22, { align });

  autoTable(doc, {
    head: [report.columns],
    body: report.rows.map((r) => r.map((c) => String(c))),
    startY: 28,
    styles: {
      halign: isRTL ? "right" : "left",
      fontSize: 9,
      // autotable defaults to Helvetica regardless of the document font.
      ...(arabicFont ? { font: "IBMPlexSansArabic" } : {}),
    },
    headStyles: { fillColor: [37, 99, 235] },
  });

  doc.save(filename ?? `${slug(report.title)}.pdf`);
}

/** Export an arbitrary key→value list (e.g. a payslip) as PDF. */
export async function exportKeyValuePDF(
  title: string,
  rows: [string, string | number][],
  filename?: string,
) {
  const doc = new jsPDF();
  const arabicFont = await ensureArabicFont(doc);
  doc.text(title, 14, 18);
  autoTable(doc, {
    body: rows.map(([k, v]) => [k, String(v)]),
    startY: 24,
    styles: {
      fontSize: 10,
      halign: "right",
      ...(arabicFont ? { font: "IBMPlexSansArabic" } : {}),
    },
    columnStyles: { 0: { fontStyle: "bold", cellWidth: 80 } },
  });
  doc.save(filename ?? `${slug(title)}.pdf`);
}
