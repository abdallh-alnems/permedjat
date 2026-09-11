import { COUNTRY_DIAL_CODES } from "./phone-countries";

/**
 * A phone number as the phone input edits it: a country (ISO 3166 alpha-2),
 * and the digits after its dial code. Mirrors `PhoneParts` in the manager app.
 */
export type PhoneParts = { iso: string | null; national: string };

export type PhoneError = "country_required" | "phone_invalid";

export const EMPTY_PHONE: PhoneParts = { iso: null, national: "" };

const DIAL_BY_ISO = new Map(COUNTRY_DIAL_CODES);

/**
 * Dial codes shared by several countries, and the one an existing number is
 * shown under. Only the flag depends on this: the number sent back is the same
 * whichever of them is selected.
 */
const PRIMARY_FOR_SHARED_CODE: Record<string, string> = {
  "1": "US",
  "7": "RU",
  "44": "GB",
  "47": "NO",
  "61": "AU",
  "212": "MA",
  "262": "RE",
  "358": "FI",
  "500": "FK",
  "590": "GP",
  "599": "CW",
  "672": "NF",
};

export function dialCode(iso: string | null): string | null {
  return iso ? (DIAL_BY_ISO.get(iso) ?? null) : null;
}

/**
 * Strips non-digits and the national trunk prefix (leading zeros) from a
 * locally-typed number so it can be joined to a country code as E.164.
 * E.g. Egypt "01023809407" + code "20" → "+201023809407" (not "+2001023809407").
 */
export function nationalDigits(raw: string): string {
  return raw.replace(/\D/g, "").replace(/^0+/, "");
}

/**
 * The E.164 number to send, or "" when the field is blank. Call only once
 * {@link phoneError} has passed.
 */
export function toE164(phone: PhoneParts): string {
  const dial = dialCode(phone.iso);
  if (phone.national.trim() === "" || dial === null) return "";
  return `+${dial}${nationalDigits(phone.national)}`;
}

/**
 * Splits a stored number back into a country and the rest.
 *
 * `employees.phone` holds one string, mostly E.164. Dial codes are
 * prefix-free, so at most one of them can lead an international number and the
 * split is never a guess. Whatever follows the code is kept as stored, so an
 * old "+2001023809407" shows as +20 / 01023809407 rather than being silently
 * rewritten. A number with no "+" (stored before a country code was required)
 * or with an unknown code gets no country: the digits are shown as they are
 * and the admin picks the country if they edit it.
 */
export function parsePhone(stored: string | null | undefined): PhoneParts {
  let value = (stored ?? "")
    .trim()
    .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  if (value.startsWith("00")) value = `+${value.slice(2)}`;
  const digits = value.replace(/\D/g, "");
  if (!value.startsWith("+")) return { iso: null, national: digits };

  for (const [iso, dial] of COUNTRY_DIAL_CODES) {
    if (!digits.startsWith(dial)) continue;
    const primary = PRIMARY_FOR_SHARED_CODE[dial];
    return {
      iso: primary && DIAL_BY_ISO.has(primary) ? primary : iso,
      national: digits.slice(dial.length),
    };
  }
  return { iso: null, national: digits };
}

/** True while `current` still holds exactly the number `original` was parsed from. */
export function isSamePhone(original: PhoneParts, current: PhoneParts): boolean {
  return (
    dialCode(original.iso) === dialCode(current.iso) &&
    original.national === current.national.trim()
  );
}

/** Same rules as the manager app's phone field. Blank is valid: phone is optional. */
export function phoneError(phone: PhoneParts): PhoneError | null {
  const n = phone.national.trim();
  if (n === "") return null;
  const dial = dialCode(phone.iso);
  if (dial === null) return "country_required";
  // E.164: a leading country digit of 1-9, then 7 to 14 more.
  return /^[1-9]\d{7,14}$/.test(`${dial}${nationalDigits(n)}`) ? null : "phone_invalid";
}
