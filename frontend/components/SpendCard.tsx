import type { ReactNode } from 'react';

export default function SpendCard({
  title,
  value,
  sub,
  tone = 'default',
}: {
  title: string;
  value: string;
  sub?: ReactNode;
  tone?: 'default' | 'up' | 'down' | 'alert';
}) {
  const border =
    tone === 'alert'
      ? 'border-red-300 bg-red-50'
      : tone === 'up'
        ? 'border-amber-300 bg-amber-50'
        : 'border-slate-200 bg-white';
  return (
    <div className={`rounded-lg border p-4 shadow-sm ${border}`}>
      <p className="text-sm font-medium text-slate-500">{title}</p>
      <p className="mt-1 text-2xl font-bold text-slate-900">{value}</p>
      {sub ? <div className="mt-1 text-sm text-slate-600">{sub}</div> : null}
    </div>
  );
}
