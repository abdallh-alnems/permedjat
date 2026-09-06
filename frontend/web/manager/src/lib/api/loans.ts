import { apiGet, apiPost, unwrapList, asObject } from "./client";
import type { Loan } from "@/lib/types";

export async function listLoans(): Promise<Loan[]> {
  // Backend returns `{ items }`.
  const raw = await apiGet<unknown>("v1/loans");
  return unwrapList<Loan>(raw, ["items", "data"]);
}

export function createLoan(data: Partial<Loan>) {
  return apiPost<Loan>("v1/loans", data);
}

export function approveLoan(id: number) {
  return apiPost<Loan>("v1/loans/approve", { id });
}

export function cancelLoan(id: number) {
  return apiPost<Loan>("v1/loans/cancel", { id });
}
