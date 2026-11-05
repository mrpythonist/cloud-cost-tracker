import { money } from '@/lib/format';
import type { ServiceSpend } from '@/lib/api';

const BAR_COLORS = ['#f59e0b', '#0ea5e9', '#22c55e', '#a78bfa', '#f43f5e', '#64748b'];

/**
 * Per-service spend: horizontal bars (plain divs — no chart dependency)
 * plus a table with totals and share of the month.
 */
export default function ServiceBreakdown({ services }: { services: ServiceSpend[] }) {
  if (services.length === 0) {
    return <p className="text-slate-500">No spend recorded for this month yet.</p>;
  }
  const max = Math.max(...services.map((s) => s.total));
  return (
    <div>
      <div className="space-y-3">
        {services.map((s, i) => (
          <div key={s.service}>
            <div className="flex justify-between text-sm">
              <span className="font-medium text-slate-800">{s.service}</span>
              <span className="text-slate-600">
                {money(s.total)} · {s.share_percent}%
              </span>
            </div>
            <div className="mt-1 h-3 w-full overflow-hidden rounded bg-slate-100">
              <div
                className="h-full rounded"
                style={{
                  width: `${(s.total / max) * 100}%`,
                  backgroundColor: BAR_COLORS[i % BAR_COLORS.length],
                }}
              />
            </div>
          </div>
        ))}
      </div>
      <table className="mt-6 w-full text-sm">
        <thead>
          <tr className="border-b text-left text-slate-500">
            <th className="py-2">Service</th>
            <th className="py-2 text-right">Total</th>
            <th className="py-2 text-right">Share</th>
            <th className="py-2 text-right">Rows</th>
          </tr>
        </thead>
        <tbody>
          {services.map((s) => (
            <tr key={s.service} className="border-b last:border-0">
              <td className="py-2 font-medium">{s.service}</td>
              <td className="py-2 text-right">{money(s.total)}</td>
              <td className="py-2 text-right">{s.share_percent}%</td>
              <td className="py-2 text-right">{s.row_count}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
