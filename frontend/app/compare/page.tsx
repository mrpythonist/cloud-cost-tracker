'use client';

import { useState } from 'react';
import { api, type CompareReport } from '@/lib/api';
import { money, monthLabel, pct } from '@/lib/format';
import MonthPicker from '@/components/MonthPicker';

export default function ComparePage() {
  const [from, setFrom] = useState('2026-09');
  const [to, setTo] = useState('2026-10');
  const [report, setReport] = useState<CompareReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function run() {
    setBusy(true);
    setError(null);
    try {
      setReport(await api.compare(from, to));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Comparison failed.');
      setReport(null);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Compare months</h1>
      <div className="flex flex-wrap items-end gap-4 rounded-lg border bg-white p-6">
        <MonthPicker value={from} onChange={setFrom} label="From month" />
        <MonthPicker value={to} onChange={setTo} label="To month" />
        <button
          onClick={run}
          disabled={busy}
          className="rounded bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600 disabled:opacity-50"
        >
          {busy ? 'Comparing…' : 'Compare'}
        </button>
      </div>
      {error && <p className="text-sm text-red-600">{error}</p>}

      {report && (
        <section className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="rounded-lg border bg-white p-4">
              <p className="text-sm text-slate-500">{monthLabel(report.from)}</p>
              <p className="text-2xl font-bold">{money(report.from_total)}</p>
            </div>
            <div className="rounded-lg border bg-white p-4">
              <p className="text-sm text-slate-500">{monthLabel(report.to)}</p>
              <p className="text-2xl font-bold">{money(report.to_total)}</p>
            </div>
            <div
              className={`rounded-lg border p-4 ${
                report.delta > 0 ? 'border-amber-300 bg-amber-50' : 'border-green-300 bg-green-50'
              }`}
            >
              <p className="text-sm text-slate-500">Change</p>
              <p className="text-2xl font-bold">
                {report.delta >= 0 ? '+' : ''}
                {money(report.delta)} ({pct(report.delta_percent)})
              </p>
            </div>
          </div>

          <div className="rounded-lg border bg-white p-6">
            <h2 className="mb-4 text-lg font-bold">Per-service change</h2>
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-slate-500">
                  <th className="py-2">Service</th>
                  <th className="py-2 text-right">{monthLabel(report.from)}</th>
                  <th className="py-2 text-right">{monthLabel(report.to)}</th>
                  <th className="py-2 text-right">Change $</th>
                  <th className="py-2 text-right">Change %</th>
                </tr>
              </thead>
              <tbody>
                {report.per_service.map((s) => (
                  <tr key={s.service} className="border-b last:border-0">
                    <td className="py-2 font-medium">{s.service}</td>
                    <td className="py-2 text-right">{money(s.from_total)}</td>
                    <td className="py-2 text-right">{money(s.to_total)}</td>
                    <td className={`py-2 text-right font-medium ${s.delta > 0 ? 'text-amber-700' : s.delta < 0 ? 'text-green-700' : ''}`}>
                      {s.delta >= 0 ? '+' : ''}
                      {money(s.delta)}
                    </td>
                    <td className="py-2 text-right">{pct(s.delta_percent)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}
