const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export interface ServiceSpend {
  service: string;
  total: number;
  share_percent: number;
  row_count: number;
}

export interface MonthlyReport {
  month: string;
  total: number;
  currency: string;
  by_service: ServiceSpend[];
  top_services: { service: string; total: number }[];
}

export interface CompareReport {
  from: string;
  to: string;
  from_total: number;
  to_total: number;
  delta: number;
  delta_percent: number | null;
  per_service: {
    service: string;
    from_total: number;
    to_total: number;
    delta: number;
    delta_percent: number | null;
  }[];
}

export interface IncreasingService {
  service: string;
  reason: string;
  latest_month: string;
  latest_total: number;
  previous_total: number;
  delta_percent: number | null;
  consecutive_increases: number;
}

export interface TrendsReport {
  months: string[];
  series: Record<string, Record<string, number>>;
  increasing_cost_services: IncreasingService[];
}

export interface UploadSummary {
  upload: BillingUpload;
  rows_imported: number;
  rows_skipped: number;
  skipped_rows: { line: number; reason: string }[];
  total_cost: number;
  months: string[];
  alerts_created: number;
}

export interface BillingUpload {
  id: number;
  original_filename: string;
  format: string;
  status: string;
  rows_imported: number;
  rows_skipped: number;
  total_cost: number;
  currency: string;
  months_covered: string[];
  created_at: string;
}

export interface Budget {
  id: number;
  period: string;
  service: string | null;
  amount: number;
  currency: string;
  actual?: number;
  usage_percent?: number;
  status?: 'ok' | 'warning' | 'exceeded';
}

export interface BudgetAlert {
  id: number;
  budget_id: number;
  period: string;
  service: string | null;
  level: 'warning' | 'exceeded';
  threshold_percent: number;
  actual: number;
  budget_amount: number;
  usage_percent: number;
  message: string;
  acknowledged_at: string | null;
  created_at: string;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: { 'Content-Type': 'application/json', ...(init?.headers ?? {}) },
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(body.message ?? `API request failed: ${res.status}`);
  }
  return res.json() as Promise<T>;
}

export const api = {
  monthly: (month?: string) =>
    request<MonthlyReport>(`/reports/monthly${month ? `?month=${month}` : ''}`),
  compare: (from: string, to: string) =>
    request<CompareReport>(`/reports/compare?from=${from}&to=${to}`),
  trends: (months = 3, threshold = 20) =>
    request<TrendsReport>(`/reports/trends?months=${months}&threshold=${threshold}`),
  uploads: () =>
    request<{ data: BillingUpload[] }>(`/uploads`).then((r) => r.data),
  uploadCsv: async (file: File): Promise<UploadSummary> => {
    const form = new FormData();
    form.append('csv_file', file);
    const res = await fetch(`${API_BASE}/uploads`, { method: 'POST', body: form });
    if (!res.ok) {
      const body = await res.json().catch(() => ({}));
      throw new Error(body.message ?? `Upload failed: ${res.status}`);
    }
    return res.json() as Promise<UploadSummary>;
  },
  budgets: () => request<Budget[]>(`/budgets`),
  createBudget: (body: { period: string; service: string | null; amount: number }) =>
    request<Budget>(`/budgets`, { method: 'POST', body: JSON.stringify(body) }),
  updateBudget: (id: number, body: { amount: number }) =>
    request<Budget>(`/budgets/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  deleteBudget: (id: number) =>
    request<{ message: string }>(`/budgets/${id}`, { method: 'DELETE' }),
  alerts: (unacknowledgedOnly = false) =>
    request<{ data: BudgetAlert[] }>(
      `/alerts${unacknowledgedOnly ? '?acknowledged=false' : ''}`
    ).then((r) => r.data),
  checkAlerts: () => request<{ alerts_created: number }>(`/alerts/check`, { method: 'POST' }),
  acknowledgeAlert: (id: number) =>
    request<BudgetAlert>(`/alerts/${id}/acknowledge`, { method: 'PATCH' }),
};
