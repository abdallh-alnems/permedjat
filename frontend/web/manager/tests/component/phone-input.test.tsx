/**
 * The phone input used by the add-employee and employee-profile forms, driven
 * through the real base-ui combobox rather than a mock of it.
 */
import { useState } from "react";
import { act, fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { PhoneInput } from "@/components/ui/phone-input";
import { parsePhone, toE164, type PhoneParts } from "@/lib/phone";

function Harness({ stored, onValue }: { stored: string | null; onValue?: (p: PhoneParts) => void }) {
  const [value, setValue] = useState(() => parsePhone(stored));
  return (
    <>
      <PhoneInput
        value={value}
        onChange={(p) => {
          setValue(p);
          onValue?.(p);
        }}
      />
      <output data-testid="e164">{toE164(value)}</output>
    </>
  );
}

const trigger = () => screen.getAllByLabelText("اختر الدولة/المنطقة")[0];
const numberField = () => screen.getByPlaceholderText("الهاتف");

async function openPicker() {
  await act(async () => {
    fireEvent.click(trigger());
  });
  return screen.getByPlaceholderText("بحث");
}

describe("PhoneInput", () => {
  it("shows a stored number split into code and the rest", () => {
    render(<Harness stored="+201023809407" />);
    expect(trigger()).toHaveTextContent("+20");
    expect(numberField()).toHaveValue("1023809407");
  });

  it("an old number without a code shows no country and its digits", () => {
    render(<Harness stored="01023809407" />);
    expect(trigger()).toHaveTextContent("اختر الدولة/المنطقة");
    expect(numberField()).toHaveValue("01023809407");
  });

  it("picks a country by searching, then builds the number to send", async () => {
    render(<Harness stored={null} />);
    const search = await openPicker();
    await act(async () => {
      fireEvent.change(search, { target: { value: "saudi" } });
    });
    const option = await screen.findByRole("option", { name: /السعودية/ });
    await act(async () => {
      fireEvent.click(option);
    });
    expect(trigger()).toHaveTextContent("+966");

    fireEvent.change(numberField(), { target: { value: "0501234567" } });
    expect(screen.getByTestId("e164")).toHaveTextContent("+966501234567");
  });

  it("finds a country by its dial code", async () => {
    render(<Harness stored={null} />);
    const search = await openPicker();
    await act(async () => {
      fireEvent.change(search, { target: { value: "+20" } });
    });
    const listbox = await screen.findByRole("listbox");
    expect(within(listbox).getByRole("option", { name: /مصر/ })).toBeInTheDocument();
  });

  it("keeps digits only, converting Arabic-Indic ones", () => {
    render(<Harness stored="+201023809407" />);
    fireEvent.change(numberField(), { target: { value: "٠١٠-٢٣٨ ٠٩٤٠٧" } });
    expect(numberField()).toHaveValue("01023809407");
    expect(screen.getByTestId("e164")).toHaveTextContent("+201023809407");
  });
});
