'use client';

import { useEffect, useState } from 'react';
import { api, type BillingUpload, type UploadSummary } from '@/lib/api';
import { money } from '@/lib/format';

export default function UploadPage() {
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<UploadSummary | null>(null);
  const [history, setHistory] = useState<BillingUpload[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.uploads().then(setHistory).catch(() => setHistory([]));
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      const summary = await api.uploadCsv(file);
      setResult(summary);
      setHistory(await api.uploads());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Upload failed.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold">Upload AWS billing CSV</h1>
      <p className="text-slate-600">
        Accepts AWS Cost and Usage Report (CUR) exports (UsageStartDate, ProductCode,
        UnblendedCost, …) or a simple <code>date,service,cost</code> file. Max 10 MB.
        Malformed rows are skipped and reported — they never fail the whole upload.
      </p>

      <form onSubmit={submit} className="rounded-lg border bg-white p-6">
        <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
          Billing CSV file
          <input
            type="file"
            accept=".csv,text/csv"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="rounded border border-slate-300 px-3 py-2"
          />
        </label>
        {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
        <button
          type="submit"
          disabled={!file || busy}
          className="mt-4 rounded bg-amber-500 px-4 py-2 font-medium text-white hover:bg-amber-600 disabled:opacity-50"
        >
          {busy ? 'Parsing…' : 'Upload and parse'}
        </button>
      </form>

      {result && (
        <section className="rounded-lg border bg-white p-6">
          <h2 className="text-lg font-bold">Parse report</h2>
          <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt className="text-slate-500">Rows imported</dt><dd className="font-semibold">{result.rows_imported}</dd></div>
            <div><dt className="text-slate-500">Rows skipped</dt><dd className="font-semibold">{result.rows_skipped}</dd></div>
            <div><dt className="text-slate-500">Total cost</dt><dd className="font-semibold">{money(result.total_cost)}</dd></div>
            <div><dt className="text-slate-500">Months covered</dt><dd className="font-semibold">{result.months.join(', ')}</dd></div>
            <div><dt className="text-slate-500">Budget alerts created</dt><dd className="font-semibold">{result.alerts_created}</dd></div>
          </dl>
          {result.skipped_rows.length > 0 && (
            <div className="mt-4">
              <h3 className="font-semibold">Skipped rows</h3>
              <ul className="mt-2 space-y-1 text-sm text-slate-600">
                {result.skipped_rows.map((s, i) => (
                  <li key={i}>Line {s.line}: {s.reason}</li>
                ))}
              </ul>
            </div>
          )}
        </section>
      )}

      <section className="rounded-lg border bg-white p-6">
        <h2 className="text-lg font-bold">Upload history</h2>
        {history.length === 0 ? (
          <p className="mt-2 text-slate-500">No uploads yet.</p>
        ) : (
          <table className="mt-4 w-full text-sm">
            <thead>
              <tr className="border-b text-left text-slate-500">
                <th className="py-2">File</th>
                <th className="py-2">Format</th>
                <th className="py-2 text-right">Imported</th>
                <th className="py-2 text-right">Skipped</th>
                <th className="py-2 text-right">Total</th>
                <th className="py-2 text-right">Months</th>
              </tr>
            </thead>
            <tbody>
              {history.map((u) => (
                <tr key={u.id} className="border-b last:border-0">
                  <td className="py-2 font-medium">{u.original_filename}</td>
                  <td className="py-2">{u.format}</td>
                  <td className="py-2 text-right">{u.rows_imported}</td>
                  <td className="py-2 text-right">{u.rows_skipped}</td>
                  <td className="py-2 text-right">{money(u.total_cost, u.currency)}</td>
                  <td className="py-2 text-right">{u.months_covered.join(', ')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}
