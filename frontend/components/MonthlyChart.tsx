import { money, monthLabel } from '@/lib/format';

/**
 * Grouped monthly spend chart rendered as plain SVG bars — no chart
 * library required. `series` is service => { month => total }.
 */
export default function MonthlyChart({
  months,
  series,
}: {
  months: string[];
  series: Record<string, Record<string, number>>;
}) {
  const services = Object.keys(series);
  if (months.length === 0 || services.length === 0) {
    return <p className="text-slate-500">Upload billing data to see the trend.</p>;
  }
  const max = Math.max(1, ...services.flatMap((s) => months.map((m) => series[s][m] ?? 0)));
  const W = 640;
  const H = 260;
  const pad = 36;
  const groupW = (W - pad * 2) / months.length;
  const barW = Math.max(8, (groupW - 16) / services.length);
  const COLORS = ['#f59e0b', '#0ea5e9', '#22c55e', '#a78bfa', '#f43f5e'];

  return (
    <div>
      <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label="Monthly spend by service">
        {[0.25, 0.5, 0.75, 1].map((f) => {
          const y = H - pad - (H - pad * 2) * f;
          return (
            <g key={f}>
              <line x1={pad} x2={W - pad} y1={y} y2={y} stroke="#e2e8f0" />
              <text x={pad - 6} y={y + 4} textAnchor="end" fontSize="11" fill="#64748b">
                {money(max * f)}
              </text>
            </g>
          );
        })}
        {months.map((month, mi) => {
          const gx = pad + mi * groupW;
          return (
            <g key={month}>
              {services.map((service, si) => {
                const v = series[service][month] ?? 0;
                const h = ((H - pad * 2) * v) / max;
                return (
                  <rect
                    key={service}
                    x={gx + 8 + si * barW}
                    y={H - pad - h}
                    width={barW - 2}
                    height={Math.max(h, v > 0 ? 2 : 0)}
                    fill={COLORS[si % COLORS.length]}
                    rx={2}
                  >
                    <title>{`${service} · ${monthLabel(month)}: ${money(v)}`}</title>
                  </rect>
                );
              })}
              <text x={gx + groupW / 2} y={H - 12} textAnchor="middle" fontSize="12" fill="#334155">
                {month}
              </text>
            </g>
          );
        })}
      </svg>
      <div className="mt-2 flex flex-wrap gap-4 text-sm text-slate-600">
        {services.map((s, i) => (
          <span key={s} className="flex items-center gap-1.5">
            <span
              className="inline-block h-3 w-3 rounded-sm"
              style={{ backgroundColor: COLORS[i % COLORS.length] }}
            />
            {s}
          </span>
        ))}
      </div>
    </div>
  );
}
