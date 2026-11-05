import type { Metadata } from 'next';
import Link from 'next/link';
import './globals.css';

export const metadata: Metadata = {
  title: 'Cloud Cost Tracker',
  description: 'Understand and monitor cloud infrastructure expenses from AWS billing CSVs.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <header className="border-b bg-white">
          <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <Link href="/" className="text-lg font-bold">
              Cloud Cost Tracker
            </Link>
            <nav className="flex gap-4 text-sm font-medium text-slate-600">
              <Link href="/" className="hover:text-slate-900">Dashboard</Link>
              <Link href="/upload" className="hover:text-slate-900">Upload</Link>
              <Link href="/compare" className="hover:text-slate-900">Compare</Link>
              <Link href="/budgets" className="hover:text-slate-900">Budgets &amp; Alerts</Link>
            </nav>
          </div>
        </header>
        <main className="mx-auto max-w-6xl px-4 py-8">{children}</main>
        <footer className="mx-auto max-w-6xl px-4 pb-8 text-xs text-slate-400">
          Costs come from manually uploaded AWS billing CSVs — no live AWS billing
          integration. See sample-data/sample-aws-billing.csv to try it out.
        </footer>
      </body>
    </html>
  );
}
