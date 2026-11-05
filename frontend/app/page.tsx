'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, type MonthlyReport, type TrendsReport } from '@/lib/api';
import { money, monthLabel, pct, prevMonth } from '@/lib/format';
import SpendCard from '@/components/SpendCard';
import ServiceBreakdown from '@/components/ServiceBreakdown';
import MonthlyChart from '@/components/MonthlyChart';
import MonthPicker from '@/components/MonthPicker';

export default function DashboardPage() {
  const [month, setMonth] = useState('2026-10');
  const [report, setReport] = useState<MonthlyReport | null>(null);
  const [prev, setPrev] = useState<MonthlyReport | null>(null);
  const [trends, setTrends] = useState<TrendsReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    Promise.all([
      api.monthly(month),
      api.monthly(prevMonth(month)).catch(() => null),
      api.trends(3).catch(() => null),
    ])
      .then(([r, p, t]) => {
        if (cancelled) return;
        setReport(r);
        setPrev(p);
        setTrends(t);
      })
      .catch((e: unknown) => {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Failed to load the dashboard.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [month]);

  if (loading) return <p className="text-slate-500">Loading spend data…</p>;
  if (error || !report) {
    return (
      <div className="rounded-lg border bg-white p-6">
        <h1 className="text-xl font-bold">No billing data yet</h1>
        <p className="mt-2 text-slate-600">
          {error ?? 'Upload an AWS billing CSV to see your spend.'}
        </p>
        <Link
          href="/upload"
          className="mt-4 inline-block rounded bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600"
        >
          Upload a billing CSV
        </Link>
      </div>
    );
  }

  const momDelta = prev ? report.total - prev.total : null;
  const momPct = prev && prev.total > 0 ? ((report.total - prev.total) / prev.total) * 100 : null;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold">Spend overview</h1>
          <p className="text-slate-500">{monthLabel(report.month)} · all figures in {report.currency}</p>
        </div>
        <MonthPicker value={month} onChange={setMonth} label="Month" />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <SpendCard title="Total spend" value={money(report.total)} sub={monthLabel(report.month)} />
        <SpendCard
          title="vs previous month"
          value={momDelta === null ? 'n/a' : `${momDelta >= 0 ? '+' : ''}${money(momDelta)}`}
          sub={momPct === null ? 'No previous month' : `${pct(momPct)} vs ${monthLabel(prevMonth(month))}`}
          tone={momPct !== null && momPct > 20 ? 'up' : 'default'}
        />
        <SpendCard
          title="Top service"
          value={report.top_services[0]?.service ?? '—'}
          sub={report.top_services[0] ? money(report.top_services[0].total) : undefined}
        />
      </div>

      {trends && trends.increasing_cost_services.length > 0 && (
        <section className="rounded-lg border border-amber-300 bg-amber-50 p-4">
          <h2 className="font-bold text-amber-900">Costs rising — review these services</h2>
          <ul className="mt-2 space-y-1 text-sm text-amber-900">
            {trends.increasing_cost_services.map((s) => (
              <li key={s.service}>
                <strong>{s.service}</strong>: {s.reason} — {money(s.previous_total)} →{' '}
                {money(s.latest_total)} in {s.latest_month}.
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="rounded-lg border bg-white p-6">
        <h2 className="mb-4 text-lg font-bold">Monthly trend by service</h2>
        {trends ? (
          <MonthlyChart months={trends.months} series={trends.series} />
        ) : (
          <p className="text-slate-500">Trend data unavailable.</p>
        )}
      </section>

      <section className="rounded-lg border bg-white p-6">
        <h2 className="mb-4 text-lg font-bold">Spend by service — {monthLabel(report.month)}</h2>
        <ServiceBreakdown services={report.by_service} />
      </section>
    </div>
  );
}
