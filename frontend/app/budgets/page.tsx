'use client';

import { useEffect, useState } from 'react';
import { api, type Budget, type BudgetAlert } from '@/lib/api';
import { money, monthLabel } from '@/lib/format';
import AlertList from '@/components/AlertList';

export default function BudgetsPage() {
  const [budgets, setBudgets] = useState<Budget[]>([]);
  const [alerts, setAlerts] = useState<BudgetAlert[]>([]);
  const [period, setPeriod] = useState('2026-10');
  const [service, setService] = useState('');
  const [amount, setAmount] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function refresh() {
    const [b, a] = await Promise.all([api.budgets(), api.alerts()]);
    setBudgets(b);
    setAlerts(a);
  }

  useEffect(() => {
    refresh().catch((e: unknown) => setError(e instanceof Error ? e.message : 'Failed to load budgets.'));
  }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.createBudget({
        period,
        service: service.trim() === '' ? null : service.trim(),
        amount: Number(amount),
      });
      setAmount('');
      setService('');
      await refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create budget.');
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    if (!confirm('Delete this budget? Its alerts are deleted too.')) return;
    await api.deleteBudget(id);
    await refresh();
  }

  async function recheck() {
    setBusy(true);
    try {
      const r = await api.checkAlerts();
      alert(`${r.alerts_created} new alert(s) created.`);
      await refresh();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Re-check failed.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold">Budgets &amp; alerts</h1>
        <button
          onClick={recheck}
          disabled={busy}
          className="rounded bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-300 hover:bg-slate-50 disabled:opacity-50"
        >
          Re-check budgets now
        </button>
      </div>
      <p className="text-slate-600">
        Set a monthly overall budget, or a per-service budget for a month. Spend is
        checked after every CSV upload: 80% of budget raises a warning, 100% raises
        an exceeded alert.
      </p>
      {error && <p className="text-sm text-red-600">{error}</p>}

      <form onSubmit={create} className="flex flex-wrap items-end gap-4 rounded-lg border bg-white p-6">
        <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
          Month
          <input
            type="month"
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            required
            className="rounded border border-slate-300 px-3 py-2"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
          Service (blank = overall)
          <input
            type="text"
            value={service}
            onChange={(e) => setService(e.target.value)}
            placeholder="EC2"
            className="rounded border border-slate-300 px-3 py-2"
          />
        </label>
        <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
          Budget amount (USD)
          <input
            type="number"
            min="0.01"
            step="0.01"
            value={amount}
            onChange={(e) => setAmount(e.target.value)}
            required
            placeholder="1200.00"
            className="rounded border border-slate-300 px-3 py-2"
          />
        </label>
        <button
          type="submit"
          disabled={busy}
          className="rounded bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600 disabled:opacity-50"
        >
          Add budget
        </button>
      </form>

      <section className="rounded-lg border bg-white p-6">
        <h2 className="mb-4 text-lg font-bold">Budgets</h2>
        {budgets.length === 0 ? (
          <p className="text-slate-500">No budgets yet.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2">Period</th>
                <th className="py-2">Scope</th>
                <th className="py-2 text-right">Budget</th>
                <th className="py-2 text-right">Actual</th>
                <th className="py-2 text-right">Used</th>
                <th className="py-2 text-right">Status</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody>
              {budgets.map((b) => (
                <tr key={b.id} className="border-b last:border-0">
                  <td className="py-2">{monthLabel(b.period)}</td>
                  <td className="py-2 font-medium">{b.service ?? 'Overall'}</td>
                  <td className="py-2 text-right">{money(b.amount)}</td>
                  <td className="py-2 text-right">{b.actual !== undefined ? money(b.actual) : '—'}</td>
                  <td className="py-2 text-right">{b.usage_percent !== undefined ? `${b.usage_percent}%` : '—'}</td>
                  <td className="py-2 text-right">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                        b.status === 'exceeded'
                          ? 'bg-red-100 text-red-800'
                          : b.status === 'warning'
                            ? 'bg-amber-100 text-amber-800'
                            : 'bg-green-100 text-green-800'
                      }`}
                    >
                      {b.status ?? 'ok'}
                    </span>
                  </td>
                  <td className="py-2 text-right">
                    <button onClick={() => remove(b.id)} className="text-sm text-red-600 hover:underline">
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      <section className="rounded-lg border bg-white p-6">
        <h2 className="mb-4 text-lg font-bold">Alerts</h2>
        <AlertList
          alerts={alerts}
          onAcknowledge={(id) =>
            setAlerts((prev) =>
              prev.map((a) => (a.id === id ? { ...a, acknowledged_at: new Date().toISOString() } : a))
            )
          }
        />
      </section>
    </div>
  );
}
