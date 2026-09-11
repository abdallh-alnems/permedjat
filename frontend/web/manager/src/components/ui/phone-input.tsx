"use client";

import * as React from "react";
import { Combobox } from "@base-ui/react/combobox";
import { CheckIcon, ChevronDownIcon } from "lucide-react";

import { Input } from "@/components/ui/input";
import { useT } from "@/lib/i18n/use-t";
import { COUNTRY_DIAL_CODES } from "@/lib/phone-countries";
import type { PhoneParts } from "@/lib/phone";

type Country = { iso: string; dial: string; name: string; enName: string };

function flag(iso: string) {
  return String.fromCodePoint(
    ...[...iso].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65),
  );
}

/** Every country with its name in the UI language, sorted by that name. */
function useCountries(locale: string): Country[] {
  return React.useMemo(() => {
    const names = new Intl.DisplayNames([locale], { type: "region" });
    const en = new Intl.DisplayNames(["en"], { type: "region" });
    const collator = new Intl.Collator(locale);
    return COUNTRY_DIAL_CODES.map(([iso, dial]) => ({
      iso,
      dial,
      name: names.of(iso) ?? iso,
      enName: en.of(iso) ?? iso,
    })).sort((a, b) => collator.compare(a.name, b.name));
  }, [locale]);
}

/** Matches the UI-language name, the English name, the ISO code or the dial code. */
function matches(c: Country, query: string) {
  const q = query.trim().toLowerCase().replace(/^\+/, "");
  if (q === "") return true;
  return (
    c.name.toLowerCase().includes(q) ||
    c.enName.toLowerCase().includes(q) ||
    c.dial.startsWith(q) ||
    c.iso.toLowerCase() === q
  );
}

/**
 * Country selector + phone number — the web counterpart of the manager app's
 * `PhoneField`. Controlled: turn the value into what the API expects with
 * `toE164` from `@/lib/phone`.
 */
function PhoneInput({
  value,
  onChange,
  disabled,
  invalid,
}: {
  value: PhoneParts;
  onChange: (value: PhoneParts) => void;
  disabled?: boolean;
  invalid?: boolean;
}) {
  const { t, locale } = useT();
  const countries = useCountries(locale);
  const selected = countries.find((c) => c.iso === value.iso) ?? null;

  return (
    // Left-to-right in both languages, like the app: code first, then number.
    <div dir="ltr" className="flex gap-2">
      <Combobox.Root
        items={countries}
        value={selected}
        onValueChange={(c) => c && onChange({ ...value, iso: c.iso })}
        itemToStringLabel={(c) => c.name}
        isItemEqualToValue={(a, b) => a.iso === b.iso}
        filter={matches}
        disabled={disabled}
      >
        <Combobox.Trigger
          aria-label={t("select_country")}
          aria-invalid={(invalid && !selected) || undefined}
          className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-input bg-transparent px-2.5 text-sm whitespace-nowrap transition-colors outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:bg-input/30 dark:hover:bg-input/50"
        >
          {selected ? (
            <span className="font-medium tabular-nums">
              {flag(selected.iso)} +{selected.dial}
            </span>
          ) : (
            <span className="text-muted-foreground">{t("select_country")}</span>
          )}
          <ChevronDownIcon className="size-4 text-muted-foreground" />
        </Combobox.Trigger>
        <Combobox.Portal>
          <Combobox.Positioner align="start" sideOffset={4} className="isolate z-50">
            <Combobox.Popup
              aria-label={t("select_country")}
              dir={locale === "ar" ? "rtl" : "ltr"}
              className="w-72 max-w-(--available-width) origin-(--transform-origin) overflow-hidden rounded-lg bg-popover text-popover-foreground shadow-md ring-1 ring-foreground/10 duration-100 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-closed:animate-out data-closed:fade-out-0 data-closed:zoom-out-95"
            >
              <div className="border-b border-border p-1.5">
                <Combobox.Input
                  placeholder={t("search")}
                  className="h-8 w-full rounded-md bg-transparent px-2 text-sm outline-none placeholder:text-muted-foreground"
                />
              </div>
              <Combobox.Empty>
                <p className="px-3 py-4 text-sm text-muted-foreground">
                  {t("no_results")}
                </p>
              </Combobox.Empty>
              <Combobox.List className="max-h-[min(18rem,var(--available-height))] overflow-y-auto overscroll-contain p-1 empty:p-0">
                {(c: Country) => (
                  <Combobox.Item
                    key={c.iso}
                    value={c}
                    className="flex cursor-default items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                  >
                    <span>{flag(c.iso)}</span>
                    <span className="flex-1 truncate">{c.name}</span>
                    <span dir="ltr" className="text-muted-foreground tabular-nums">
                      +{c.dial}
                    </span>
                    <Combobox.ItemIndicator className="flex size-4 items-center justify-center">
                      <CheckIcon className="size-4" />
                    </Combobox.ItemIndicator>
                  </Combobox.Item>
                )}
              </Combobox.List>
            </Combobox.Popup>
          </Combobox.Positioner>
        </Combobox.Portal>
      </Combobox.Root>
      <Input
        type="tel"
        inputMode="numeric"
        autoComplete="tel-national"
        value={value.national}
        onChange={(e) =>
          onChange({
            ...value,
            // Arabic-Indic digits come from the keyboards in this market.
            national: e.target.value
              .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660))
              .replace(/\D/g, "")
              .slice(0, 14),
          })
        }
        placeholder={t("phone")}
        disabled={disabled}
        aria-invalid={invalid || undefined}
        className="flex-1 tabular-nums"
      />
    </div>
  );
}

export { PhoneInput };
