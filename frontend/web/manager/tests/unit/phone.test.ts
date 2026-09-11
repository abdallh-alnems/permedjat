/**
 * The phone split/join used by the add-employee and employee-profile forms.
 *
 * Mirrors `PhoneParts` in the manager app (test/unit/core/phone_input_test.dart)
 * case for case — both clients edit the same `employees.phone` column and must
 * read an existing number the same way. What they send is checked against the
 * server's own rule (`App\Shared\Contact\PhoneValidator`), not a guess at it.
 */
import { describe, expect, it } from "vitest";
import { COUNTRY_DIAL_CODES } from "@/lib/phone-countries";
import {
  isSamePhone,
  parsePhone,
  phoneError,
  toE164,
} from "@/lib/phone";

/** PhoneValidator::normalize accepts exactly this once "+" is present. */
const SERVER_E164 = /^\+[1-9]\d{7,14}$/;

describe("parsePhone", () => {
  it("clean E.164 → country + the rest", () => {
    expect(parsePhone("+201023809407")).toEqual({ iso: "EG", national: "1023809407" });
  });

  it("three-digit Gulf codes", () => {
    expect(parsePhone("+966501234567")).toEqual({ iso: "SA", national: "501234567" });
    expect(parsePhone("+971501234567").iso).toBe("AE");
  });

  it("keeps a national zero left after the code instead of rewriting it", () => {
    expect(parsePhone("+2001023809407")).toEqual({ iso: "EG", national: "01023809407" });
  });

  it("no country code → no country, digits as stored", () => {
    expect(parsePhone("01023809407")).toEqual({ iso: null, national: "01023809407" });
  });

  it("empty or null", () => {
    expect(parsePhone(null)).toEqual({ iso: null, national: "" });
    expect(parsePhone("")).toEqual({ iso: null, national: "" });
  });

  it("00 prefix and Arabic-Indic digits", () => {
    expect(parsePhone("٠٠٢٠١٠٢٣٨٠٩٤٠٧")).toEqual({ iso: "EG", national: "1023809407" });
  });

  it("shared code → its primary country", () => {
    expect(parsePhone("+12025550123").iso).toBe("US");
    expect(parsePhone("+447911123456").iso).toBe("GB");
    expect(parsePhone("+212612345678").iso).toBe("MA");
  });

  it("regions left out of the picker are not shown as a country", () => {
    expect(parsePhone("+85291234567")).toEqual({ iso: null, national: "85291234567" });
  });

  it("split then join gives the stored number back", () => {
    for (const stored of ["+201023809407", "+966501234567", "+12025550123"]) {
      expect(toE164(parsePhone(stored))).toBe(stored);
    }
  });

  it("joining an edited old number drops the national zero", () => {
    expect(toE164(parsePhone("+2001023809407"))).toBe("+201023809407");
  });
});

describe("dial code list", () => {
  // The split is only unambiguous while no code is a prefix of another.
  it("is prefix-free", () => {
    const codes = [...new Set(COUNTRY_DIAL_CODES.map(([, dial]) => dial))];
    const clashes = codes.filter((a) =>
      codes.some((b) => a !== b && b.startsWith(a)),
    );
    expect(clashes).toEqual([]);
  });

  it("offers the same countries as the app picker", () => {
    expect(COUNTRY_DIAL_CODES).toHaveLength(243);
    const isos = COUNTRY_DIAL_CODES.map(([iso]) => iso);
    expect(isos).not.toContain("TW");
    expect(isos).not.toContain("HK");
    expect(isos).not.toContain("MO");
  });
});

describe("toE164", () => {
  it("strips the trunk zero, as the app does", () => {
    expect(toE164({ iso: "EG", national: "01023809407" })).toBe("+201023809407");
  });

  it("blank stays blank", () => {
    expect(toE164({ iso: "EG", national: "" })).toBe("");
    expect(toE164({ iso: null, national: "" })).toBe("");
  });

  it("everything it lets through, the server accepts", () => {
    for (const phone of [
      { iso: "EG", national: "01023809407" },
      { iso: "SA", national: "0501234567" },
      { iso: "AE", national: "501234567" },
      { iso: "US", national: "2025550123" },
    ]) {
      expect(phoneError(phone)).toBeNull();
      expect(toE164(phone)).toMatch(SERVER_E164);
    }
  });
});

describe("phoneError", () => {
  it("blank is valid (phone is optional)", () => {
    expect(phoneError({ iso: null, national: "" })).toBeNull();
  });

  it("a number needs a country", () => {
    expect(phoneError({ iso: null, national: "01023809407" })).toBe("country_required");
  });

  it("too short or too long", () => {
    expect(phoneError({ iso: "EG", national: "123" })).toBe("phone_invalid");
    expect(phoneError({ iso: "EG", national: "1234567890123456" })).toBe("phone_invalid");
  });
});

describe("isSamePhone", () => {
  const original = parsePhone("+201023809407");

  it("untouched → same", () => {
    expect(isSamePhone(original, { iso: "EG", national: "1023809407" })).toBe(true);
  });

  it("digits or country changed → not the same", () => {
    expect(isSamePhone(original, { iso: "EG", national: "1023809408" })).toBe(false);
    expect(isSamePhone(original, { iso: "SA", national: "1023809407" })).toBe(false);
  });

  it("another country on the same code is still the same number", () => {
    const us = parsePhone("+12025550123");
    expect(isSamePhone(us, { iso: "CA", national: "2025550123" })).toBe(true);
  });

  it("old number without a code: picking a country counts as an edit", () => {
    const old = parsePhone("01023809407");
    expect(isSamePhone(old, { iso: null, national: "01023809407" })).toBe(true);
    expect(isSamePhone(old, { iso: "EG", national: "01023809407" })).toBe(false);
  });
});
