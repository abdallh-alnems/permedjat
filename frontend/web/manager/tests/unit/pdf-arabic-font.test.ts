/**
 * Guards the Arabic font that PDF export depends on.
 *
 * `src/lib/export/pdf.ts` fetches its font at runtime, in the browser, and
 * swallows the failure by design: a missing file means Arabic reports quietly
 * render in Helvetica as mojibake instead of throwing. Production ran that way
 * because `public/fonts/` was empty — nothing in the build, the type-check or
 * the test suite had any opinion about a binary asset that only a runtime
 * `fetch` ever named.
 *
 * So these tests assert the asset, not the rendering: every /fonts/*.ttf URL
 * the module fetches must exist on disk, be a real TrueType file, and be
 * plausibly whole. Reading the URLs out of the source rather than hard-coding
 * them is the point — add a weight to pdf.ts without shipping the file and this
 * suite fails, which is exactly the step that was missed the first time.
 */

import { describe, it, expect } from "vitest";
import { readFileSync, statSync } from "node:fs";
import path from "node:path";

const ROOT = path.resolve(__dirname, "../..");
const PDF_SOURCE = path.join(ROOT, "src/lib/export/pdf.ts");
const PUBLIC_DIR = path.join(ROOT, "public");

/** Every "/fonts/....ttf" literal the export module fetches. */
function fontUrlsReferencedBySource(): string[] {
  const src = readFileSync(PDF_SOURCE, "utf8");
  const urls = [...src.matchAll(/["'](\/fonts\/[^"']+\.ttf)["']/g)].map(
    (m) => m[1],
  );
  return [...new Set(urls)];
}

describe("Arabic PDF font assets", () => {
  const urls = fontUrlsReferencedBySource();

  it("pdf.ts still fetches at least one Arabic font", () => {
    // If this fails the export stopped loading a font at all — either the
    // fallback silently came back, or the loader moved and this guard needs
    // to follow it.
    expect(urls.length).toBeGreaterThan(0);
  });

  it.each(urls)("%s is present in public/ and non-empty", (url) => {
    const file = path.join(PUBLIC_DIR, url.replace(/^\//, ""));
    const stats = statSync(file); // throws with the path if the file is missing
    expect(stats.isFile()).toBe(true);
    // A real IBM Plex Sans Arabic face is ~240 KB; anything tiny is a truncated
    // download or a Git LFS pointer committed by mistake.
    expect(stats.size).toBeGreaterThan(50_000);
  });

  it.each(urls)("%s is a valid TrueType file", (url) => {
    const file = path.join(PUBLIC_DIR, url.replace(/^\//, ""));
    const magic = readFileSync(file).subarray(0, 4);
    // TrueType outlines: 0x00010000. OpenType/CFF would be "OTTO", which jsPDF
    // cannot embed — so accept only the former.
    expect([...magic]).toEqual([0x00, 0x01, 0x00, 0x00]);
  });

  it("registers a bold face for every regular face it loads", () => {
    // autotable renders header rows and `fontStyle: "bold"` columns in bold;
    // an unregistered style falls back to Helvetica and garbles the Arabic.
    const src = readFileSync(PDF_SOURCE, "utf8");
    const hasRegular = /addFont\([^)]*"IBMPlexSansArabic"[^)]*"normal"\)/.test(
      src,
    );
    const hasBold = /addFont\([^)]*"IBMPlexSansArabic"[^)]*"bold"\)/.test(src);
    expect(hasRegular).toBe(true);
    expect(hasBold).toBe(true);
  });
});
