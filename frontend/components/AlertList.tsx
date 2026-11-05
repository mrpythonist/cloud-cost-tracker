'use client';

import { useState } from 'react';
import { api, type BudgetAlert } from '@/lib/api';
import { money } from '@/lib/format';

export default function AlertList({
  alerts,
  onAcknowledge,
}: {
  alerts: BudgetAlert[];
  onAcknowledge?: (id: number) => void;
}) {
  const [busy, setBusy] = useState<number | null>(null);

  if (alerts.length === 0) {
    return (
      <p className="text-slate-500">
        No budget alerts. Alerts appear here when spend crosses 80% (warning) or 100%
        (exceeded) of a budget.
      </p>
    );
  }

  async function acknowledge(id: number) {
    setBusy(id);
    try {
      await api.acknowledgeAlert(id);
      onAcknowledge?.(id);
    } catch (e) {
      alert(e instanceof Error ? e.message : 'Could not acknowledge alert.');
    } finally {
      setBusy(null);
    }
  }

  return (
    <ul className="space-y-3">
      {alerts.map((a) => (
        <li
          key={a.id}
          className={`rounded-lg border p-4 ${
            a.level === 'exceeded' ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50'
          } ${a.acknowledged_at ? 'opacity-60' : ''}`}
        >
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-semibold text-slate-900">
                {a.level === 'exceeded' ? 'Budget exceeded' : 'Budget warning'} ·{' '}
                {a.service ?? 'Overall'} · {a.period}
              </p>
              <p className="mt-1 text-sm text-slate-700">{a.message}</p>
              <p className="mt-1 text-xs text-slate-500">
                {money(a.actual)} of {money(a.budget_amount)} ({a.usage_percent}% used)
                {a.acknowledged_at ? ` · acknowledged ${new Date(a.acknowledged_at).toLocaleString()}` : ''}
              </p>
            </div>
            {!a.acknowledged_at && (
              <button
                onClick={() => acknowledge(a.id)}
                disabled={busy === a.id}
                className="shrink-0 rounded bg-white px-3 py-1 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
              >
                {busy === a.id ? 'Saving…' : 'Acknowledge'}
              </button>
            )}
          </div>
        </li>
      ))}
    </ul>
  );
}
